<?php
/**
 * tools-for-your-hobby
 * https://www.tfyh.org
 * Copyright  2023-2025  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except
 * in compliance with the License. You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software distributed under the License
 * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 * or implied. See the License for the specific language governing permissions and limitations under
 * the License.
 */

namespace tfyh\data;
include_once "../_Data/Indices.php";

use DateTimeImmutable;

use tfyh\api\PreModificationCheck;

include_once "../_Api/PreModificationCheck.php";

use tfyh\control\LoggerSeverity;
use tfyh\control\Runner;
use tfyh\control\Sessions;
use tfyh\control\Users;

include_once "../_Control/LoggerSeverity.php";
include_once "../_Control/Runner.php";
include_once "../_Control/Sessions.php";
include_once "../_Control/Users.php";

// internationalisation support needed to reflect validation or database storage errors and for formatting
// to HTML for user display
use tfyh\util\I18n;
use tfyh\util\Language;

include_once "../_Util/I18n.php";
include_once "../_Util/Language.php";

/**
 * Class Record
 *
 * Implements the PreModificationCheck interface and represents a record structure
 * that interacts with configurable data tables, handling operations such as copying
 * common fields, parsing rows, and enforcing user-based permissions.
 */
class Record implements PreModificationCheck
{

    /**
     * A record definition may contain a pseudo-table, i.e. a table that is not part of the data tables.
     * These pseudo-tables are used to store common fields, e.g. the user_id field, or the creation date.
     * The pseudo-tables are copied to the data tables, and the pseudo-fields are removed.
     * @return void
     */
    public static function copyCommonFields(): void
    {
        // copy common fields
        $tablesRoot = Config::getInstance()->getItem(".tables");
        foreach ($tablesRoot->getChildren() as $recordItem) {
            // collect what to copy and what to remove in this table
            $pseudoColumns = [];
            $toCopy = [];
            foreach ($recordItem->getChildren() as $fieldItem) {
                if (str_starts_with($fieldItem->name(), "_")) {
                    if (!$tablesRoot->hasChild($fieldItem->name()))
                        Config::getInstance()->logger->log(LoggerSeverity::ERROR,
                            "Record->copyCommonFields()", "The common field set is missing: " . $fieldItem->name());
                    else {
                        $commonFieldItem = $tablesRoot->getChild($fieldItem->name());
                        if (!is_null($commonFieldItem))
                            $toCopy[] = $commonFieldItem;
                        $pseudoColumns[] = $fieldItem;
                    }
                }
            }
            // both below copy and removal cannot be performed within the column loop, because that
            // will raise a concurrent modification exception in kotlin.
            // copy common fields
            foreach ($toCopy as $commonFieldItem)
                $recordItem->copyChildren($commonFieldItem, true);
            // remove pseudo-fields
            foreach ($pseudoColumns as $pseudoColumn) {
                // beware of the sequence. The child can no more be removed after being destroyed, because it loses its name.
                $recordItem->removeChild($pseudoColumn);
                $pseudoColumn->destroy();
            }
        }
        // remove pseudo-tables. Again, be aware of concurrent modification
        $pseudoTables = [];
        foreach ($tablesRoot->getChildren() as $recordItem)
            if (str_starts_with($recordItem->name(), "_"))
                $pseudoTables[] = $recordItem;
        foreach ($pseudoTables as $pseudoTable)
                $pseudoTable->destroy();
    }

    /**
     * Parse a record as strings (a String map) into a record of native values. Returns all $row-fields matching
     * the record definition, but not more, i.e. not the full record.
     */
    /**
     * @param array $row the record as a map of field names and Strings.
     * @param string $tableName the name of the table the record belongs to. This is used to get the table definition
     *                          from the configuration.
     * @param Language|null $language the language to use for parsing. If null, the application's language is used.'
     * @return array the record as a map of field names and native values.
     */
    public static function parseRow(array $row, string $tableName, Language $language = null): array
    {
        if (is_null($language))
            $language = Config::getInstance()->language();
        $item = Config::getInstance()->getItem(".tables." . $tableName);
        $record = new Record($item);  // temporary object
        $record->parse($row, $language);
        return $record->values(); // may be by reference, because $record is linked to nothing
    }

    public Item $item;
    private PreModificationCheck $preModificationCheck;
    private array $actualValues;
    private array $writePermissions;
    private array $writePermissionsOwn;
    private array $readPermissions;
    private array $readPermissionsOwn;
    private bool $userPermissionsAreSet = false;

    /**
     * Constructor. Sets the configuration. Use the applications PreModificationCheck or set it to null to
     * do no application-specific semantic checks prior to a database writing.
     * @param Item $item the record's configuration. The configuration must be a table definition, i.e. a record
     *                  with the name of the table and the column handling '.
     * @param PreModificationCheck|null $preModificationCheck  the application specific semantic checks to perform
     *                                                         prior to a database write. If null, no semantic checks
     *                                                         are performed.
     */
    public function __construct(Item $item, PreModificationCheck $preModificationCheck = null)
    {
        $this->item = $item;
        $this->preModificationCheck = (is_null($preModificationCheck)) ? $this : $preModificationCheck;
        $this->actualValues = [];
    }

    /**
     * Return true, if the record is "owned", i.e. either the session user's user record or a record with the
     * session user's id in it (uuid or user_id).
     */
    private function isOwn(): bool
    {
        $user = Users::getInstance();
        $userTableName = $user->userTableName;
        $userIdFieldName = $user->userIdFieldName;
        $userUuid = Sessions::getInstance()->userUuid();
        $userShortUuid = substr($userUuid, 0, 11);
        $userId = Sessions::getInstance()->userId();
        // special case user table record: the field to use is always the user id field
        if ($this->item->name() == $userTableName)
            return ($this->value($userIdFieldName) == $userId);
        // other records: check for userId and Uuid fields and their matching to the session user's values
        $isOwn = false;
        foreach ($this->item->getChildren() as $childItem) {
            if (str_contains($childItem->columnHandling(), "p")) {
                $fieldReference = str_replace("$userTableName.", $childItem->valueReference(), "");
                if (ParserName::isList($childItem->type->parser)) {
                    $valueArray = is_array($this->value($childItem->name())) ? $this->value($childItem->name()) : [];
                    // for uuids it is enough to match the short UUID, i.e. the first 11 characters
                    if (($fieldReference == "uuid") &&
                        (in_array($userShortUuid, $valueArray) || in_array($userUuid, $valueArray)))
                        $isOwn = true;
                    else if (($fieldReference == $userIdFieldName) && in_array($userId, $valueArray))
                        $isOwn = true;
                } else {
                    if (($fieldReference == "uuid") &&
                        str_starts_with($userUuid, $this->value($childItem->name())))
                        $isOwn = true;
                    if (($fieldReference == $userIdFieldName) &&
                        (Sessions::getInstance()->userId() == intval($this->value($userIdFieldName))))
                        $isOwn = true;
                }
            }
        }
        // return result
        return $isOwn;
    }

    /**
     * Set the per-field permissions for the session user. Do this before calling filter().
     */
    private function setPermissions(): void
    {
        $writeForbiddenForUser = ["role", "user_id", "workflows", "concessions"];
        $readForbiddenForOwn = ["uuid"];
        $users = Users::getInstance();
        $isUserTable = $this->item->name() == $users->userTableName;
        foreach ($this->item->getChildren() as $childItem) {
            $writePermissions = $childItem->nodeWritePermissions;
            $this->writePermissions[$childItem->name()] = $users->isAllowedItem($writePermissions);
            if ($isUserTable)
                $this->writePermissionsOwn[$childItem->name()] = !str_contains($writePermissions, "system")
                    && !in_array($childItem->name(), $writeForbiddenForUser);
            else
                $this->writePermissionsOwn[$childItem->name()] = false;
            $this->readPermissions[$childItem->name()] = $users->isAllowedItem($childItem->nodeReadPermissions);
            $this->readPermissionsOwn[$childItem->name()] = !in_array($childItem->name(), $readForbiddenForOwn);
        }
        $this->userPermissionsAreSet = true;
    }

    /**
     * Apply the permissions to $record. That will remove all fields from the record provided for which the session user
     * has no permission. If the record is returned empty, that means there is no write permission at all
     * for $record. The $value type (String, parsed, validated asf.) does not matter. NB: This does not change the
     * actual values of $this. Calls setPermissions() first if that was not done before.
     * @param array $record the record to filter.
     * @param bool $forWrite true, if the record is to be written to the database.
     * @return void
     */
    public function filter(array &$record, bool $forWrite): void
    {
        if (!$this->userPermissionsAreSet)
            $this->setPermissions();
        if ($this->isOwn()) {
            if ($forWrite) {
                foreach ($this->writePermissionsOwn as $name => $value)
                    if (!$value) unset($record[$name]);
            } else {
                foreach ($this->readPermissionsOwn as $name => $value)
                    if (!$value) unset($record[$name]);
            }
        } else {
            if ($forWrite) {
                foreach ($this->writePermissions as $name => $value)
                    if (!$value) unset($record[$name]);
            } else {
                foreach ($this->readPermissions as $name => $value)
                    if (!$value) unset($record[$name]);
            }
        }
    }

    /**
     * Get the value of a field. Return the default if the actual value is empty. Returns false if $name is not a name
     * of a record field. That should actually never happen.
     * @param string $name the name of the field to get the value of.
     * @return bool|DateTimeImmutable|float|int|string|array the value of the field.
     */
    public function value(string $name): bool|DateTimeImmutable|float|int|string|array
    {
        $field = $this->item->getChild($name);
        if (is_null($field))
            return false;
        if (!isset($this->actualValues[$name])
            || ParserConstraints::isEmpty($this->actualValues[$name], $field->type()->parser()))
            return $field->defaultValue();
        return $this->actualValues[$name];
    }

    /**
     * Get the value as csv-String (not quoted). Uses the default if the actual value is empty.
     * @param string $name the name of the field to get the value of.
     * @return string the value of the field.
     */
    private function valueCsv(string $name): string
    {
        $field = $this->item->getChild($name);
        if (is_null($field)) return "";
        return Formatter::format($this->value($name), $field->type()->parser(), Language::CSV);
    }

    /**
     * Parse a map as was produced by Csv decomposition, form entering, or database read into this record's actual
     * values. This applies no validation. See the Findings class to get the parsing process findings. Returns
     * a list of changes applied to the valuesActual array as text, per change a line, if $logChanges == true,
     * else an empty String.
     * @param array $map the map to parse.
     * @param Language $language the language to use for parsing.
     * @param bool $logChanges true, if the changes should be logged.
     * @return string a list of changes applied to the valuesActual array as text, per change a line, if $logChanges == tru
     */
    public function parse(array $map, Language $language, bool $logChanges = false): string
    {
        Findings::clearFindings();
        $changesLog = "";
        $currentValues = ($logChanges) ? $this->actualValues : [];
        $this->actualValues = []; // clear the actual values but keep the never changing uid for reference
        if (isset($currentValues["uid"]))
            $this->actualValues["uid"] = $currentValues["uid"];
        foreach ($map as $fieldName => $entryString) {
            if ($this->item->hasChild($fieldName)) {
                $field = $this->item->getChild($fieldName);
                if (!is_null($entryString) && !is_null($field)) {
                    $currentValue = $currentValues[$fieldName] ?? ParserConstraints::empty($field->type()->parser());
                    $newValue = Parser::parse($entryString, $field->type()->parser(), $language);
                    // add to the actual values always only if different from the default.
                    if (!Validator::isEqualValues($newValue, $field->defaultValue()))
                        $this->actualValues[$fieldName] = $newValue;
                    if ($logChanges && !Validator::isEqualValues($newValue, $currentValue)) {
                        // log the change
                        $loggedCurrent = Formatter::format($currentValue, $field->type()->parser(), $language);
                        if (strlen($loggedCurrent) > 50)
                            $loggedCurrent = substr($loggedCurrent, 0, 50);
                        $loggedNew = $this->formatValue($field, $language);
                        if (strlen($loggedNew) > 50)
                            $loggedNew = substr($loggedNew, 0, 50) . " ...";
                        $changesLog .= $field->name() . ": $loggedCurrent => $loggedNew\n";
                        // if this is a change action from non-default to default, the default must be added
                        // to the actual values to ensure that it is written to the database.
                        $this->actualValues[$fieldName] = $newValue;
                    }
                }
            }
        }
        return $changesLog;
    }

    /**
     * Get all record's values as a map of parsed values.
     */
    public function values(): array
    {
        $values = [];
        foreach ($this->item->getChildren() as $child)
            $values[$child->name()] = $this->value($child->name());
        return $values;
    }

    /**
     * Validate the actual values of the record against its constraints and validation rules. Skips field without an
     * actual value. See the Findings class to get the validation process findings.
     */
    public function validate(): void
    {
        Findings::clearFindings();
        foreach ($this->item->getChildren() as $child)
            if (isset($this->actualValues[$child->name()]))
                $this->actualValues[$child->name()] = $child->validate($this->actualValues[$child->name()]);
    }

    /**
     * Format a record's value as String. If the input_type is "password", this will return 10 stars "**********"
     * @param Item $column the column (record field) to format.
     * @param Language $language the language to use for formatting.
     * @return string the formatted value.
     */
    private function formatValue(Item $column, Language $language): string
    {
        return (isset($this->actualValues[$column->name()]))
            ? Formatter::format($this->actualValues[$column->name()], $column->type()->parser(), $language)
            : "";
    }

    /**
     * Provide a String to display, i.e. resolve all referencing, convenience shortcut using the name.
     * @param String $columnName the name of the column (field) to format.
     * @param string $historyFieldName the name of the history field, used to provide a link to the history of the
     *                                 record, if the field shall be displayed.
     * @param Language $language the language to use for formatting.
     * @return String the formatted value.
     */
    public function valueToDisplayByName(String $columnName, string $historyFieldName, Language $language): String {
        $column = $this->item->getChild($columnName);
        return (is_null($column) || ! $column->isValid()) ? "?$columnName?" : $this->valueToDisplay($column, $historyFieldName, $language);
    }

    /**
     * Provide a String to display, i.e. resolve all referencing.
     * @param Item $column the cnfiguration item of the column (field) to format.
     * @param string $historyFieldName the name of the history field, used to provide a link to the history of the
     *                                  record, if the field shall be displayed.
     * @param Language $language the language to use for formatting.
     * @return String the formatted value.
     */
    private function valueToDisplay(Item $column, string $historyFieldName, Language $language): string
    {
        $columnName = $column->name();
        $value = $this->value($columnName);
        $type = $column->type();
        $i18n = I18n::getInstance();
        $reference = $column->valueReference();
        $valueToDisplay = "";
        if (strcasecmp($type->name(), "micro_time") == 0) {
            if (floatval($value) >= ParserConstraints::FOREVER_SECONDS)
                $valueToDisplay .= $i18n->t("2xog20|never");
            else
                $valueToDisplay = Formatter::microTimeToString($value, $language);
        } elseif (strcasecmp($columnName, $historyFieldName) == 0) {
            $tableName = $column->parent()->name();
            $uid = $this->valueCsv("uid");
            $valueToDisplay = "<a href='../_pages/viewRecordHistory.php?table=$tableName&uid=$uid'>" .
                $i18n->t("UcNTLA|show versions") . "</a>";
        } elseif (strlen($reference) > 0) {
            $elements = (is_array($value)) ? $value : [$value];
            $indices = Indices::getInstance();
            $userIdFieldName = Config::getInstance()->getItem(".framework.users.user_id_field_name")->valueStr();
            foreach ($elements as $element) {
                $valueToDisplay .= ", ";
                if (str_ends_with($reference, "uuid")) {
                    $elementToDisplay = $indices->getNameForUuid($element, explode(".", $reference)[0]);
                    if (str_starts_with($type->name(), "uuid_or_name") && ($elementToDisplay == $indices->missingNotice))
                        $valueToDisplay .= $element;
                    else
                        $valueToDisplay .= $elementToDisplay;
                } elseif (str_ends_with($reference, $userIdFieldName))
                    $valueToDisplay .= $indices->getUserName($element);
                elseif (str_starts_with($reference, ".")) {
                    $referencedList = Config::getInstance()->getItem($reference);
                    $valueToDisplay .= ($referencedList->hasChild($element))
                        ? $referencedList->getChild($element)->label() : $element;
                } elseif ($column->name() == "password_hash") {
                    $valueToDisplay .= (strlen($value) > 2) ? substr($value, 0, 20) . "..." : $indices->missingNotice;
                }
            }
            if (strlen($valueToDisplay) > 0)
                $valueToDisplay = mb_substr($valueToDisplay, 2);
        } else
            $valueToDisplay = $this->formatValue($column, $language);
        return $valueToDisplay;
    }

    /**
     * Format the record's values as a map of names and formatted Strings. See the Findings class
     * to get the formatting process errors and warnings. The $fields array selects the columns o be formatted,
     * if set and not empty. Set $includeDefaults == false to select only those values which are different from their
     * default.
     * @param Language $language the language to use for formatting.
     * @param bool $includeDefaults true, if the default values shall be included in the result.
     * @param array $fields the names of the columns to format. If empty, all columns are formatted.
     * @return array the formatted values.
     */
    public function format(Language $language, bool $includeDefaults, array $fields = []): array
    {
        if (count($fields) == 0)
            foreach ($this->item->getChildren() as $child)
                $fields[] = $child->name();
        Findings::clearFindings();
        $formatted = [];
        foreach ($fields as $field)
            if (($this->item->hasChild($field)) && ($includeDefaults || isset($this->actualValues[$field])))
                $formatted[$field] = $this->formatValue($this->item->getChild($field), $language);
        return $formatted;
    }

    /**
     * Format the record's values as a map of names and referenced Strings. See the Findings class
     * to get the formatting process errors and warnings. The $fields array selects the columns o be formatted,
     * if set and not empty. Set $includeDefaults == false to select only those values which are different from their
     * default.
     * @param Language $language the language to use for formatting.
     * @param bool $includeDefaults true, if the default values shall be included in the result.
     * @param array $fields the names of the columns to format. If empty, all columns are formatted.
     * @return array the formatted values.
     */
    public function formatToDisplay(Language $language, bool $includeDefaults, array $fields = []): array
    {
        if (count($fields) == 0)
            foreach ($this->item->getChildren() as $child)
                $fields[] = $child->name();
        Findings::clearFindings();
        $historyFieldName = Config::getInstance()->getItem(".framework.database_connector.history")->valueStr();
        $formatted = [];
        foreach ($fields as $field) {
            if (($this->item->hasChild($field)) && ($includeDefaults || isset($this->actualValues[$field]))) {
                $child = $this->item->getChild($field);
                $formatted[$child->name()] = $this->valueToDisplay($child, $historyFieldName, $language);
            }
        }
        return $formatted;
    }

    /**
     * Return the record as html-table: key, value, type. The history field is providing a history link.
     * @param Language $language the language to use for formatting.
     * @param bool $includeNullValues true, if null values shall be included in the result.
     * @return string the html-table.
     */
    public function toHtmlTable(Language $language, bool $includeNullValues = true): string
    {
        $i18n = I18n::getInstance();
        $historyFieldName = Config::getInstance()->getItem(".framework.database_connector.history")->valueStr();
        $html = "<table><tr><th>" . $i18n->t("sC5sYJ|property") . "</th><th>" .
            $i18n->t("o474TC|value") . "</th></tr>";
        $nullValues = "";
        foreach ($this->item->getChildren() as $columnItem) {
            $column = $columnItem->name();
            $value = $this->value($column);
            $type = $columnItem->type();
            if (ParserConstraints::isEmpty($value, $type->parser))
                $nullValues .= "; " . $columnItem->label();
            elseif (!isset($this->actualValues[$column]))
                $nullValues .= "; " . $columnItem->label();
            else {
                $technicallyDisplay = "(" . $type . ")";
                if (strcasecmp($type, "micro_time") == 0)
                    $technicallyDisplay = "(" . $value . ")";
                else if (strcasecmp($column, $historyFieldName) == 0)
                    $technicallyDisplay = "";
                elseif ((strlen($columnItem->valueReference()) > 0)) {
                    $formatted = Formatter::format($value, $type->parser(), Config::getInstance()->language());
                    $technicallyDisplay = "(" . ((strlen($formatted) > 12) ? substr($formatted, 0, 11) . "..." : $formatted) . ")";
                }
                $valueToDisplay = $this->valueToDisplay($columnItem, $historyFieldName, $language);
                $html .= "<tr><td>" . $columnItem->label() . "</td><td>" . $valueToDisplay . " " . $technicallyDisplay . "</td></tr>\n";
            }
        }
        if ($includeNullValues && (strlen($nullValues) > 2))
            $html .= "<tr><td>" . $i18n->t("eiCoTk|empty data fields") . "</td><td>" .
                substr($nullValues, 2) . "</td></tr>\n";
        return $html . "</table>";
    }

    /**
     * Helper to create an edit form for the record.
     * @param Item $columnItem the column (record field) to be entered in the form.
     * @param int $i the index of the column in the form definition. The first column is at index 0, the second at 1,
     * @return String the form field definition for the column.
     */
    private function addEditFormField(Item $columnItem, int $i): String {
        $modifier = $columnItem->inputModifier();
        $cName = $columnItem->name();
        if (($i % 2) == 0)
            return "r;" . $modifier . $cName;
        else
            return "," . $modifier . $cName . ";\n";
    }
    /**
     * Create a form definition based on the Records columns.
     */
    public function defaultEditForm(): string
    {
        $defaultForm = "rowTag;names;labels\n";
        // system fields
        $defaultForm .= "r;§systemFields;" . I18n::getInstance()->t("KKbFTN|System fields") . "\n";
        $i = 0;
        foreach ($this->item->getChildren() as $columnItem)
            if (str_contains($columnItem->nodeHandling(), "s"))
                $defaultForm .= $this->addEditFormField($columnItem, $i++);
        // close the form line if the last field was left-hand side
        if (($i % 2) != 0) $defaultForm .= ",;\n";

        // period fields (only versioned records)
        if ($this->item->hasChild("valid_from")) {
            $defaultForm .= "R;§validityFields;" . I18n::getInstance()->t("hfCAVH|Period validity") . "\n";
            $defaultForm .= "r;valid_from,invalid_from;\n";
        }

        // content fields
        $defaultForm .= "R;§contentFields;" . I18n::getInstance()->t("nHAnn0|Record content") . "\n";
        $i = 0;
        foreach ($this->item->getChildren() as $columnItem) {
            $handling = $columnItem->nodeHandling();
            if (!str_contains($handling, "s") // system fields marker
                && !str_contains($handling, "v") // period validity fields marker
                && !str_contains($handling, "x")) // extended fields marker
                $defaultForm .= $this->addEditFormField($columnItem, $i++);
        }
        if (($i % 2) != 0) $defaultForm .= ",;\n";

        // extra fields
        $defaultForm .= "R;§extraFields;" . I18n::getInstance()->t("d0z4Oi|Expert fields") . "\n";
        $i = 0;
        foreach ($this->item->getChildren() as $columnItem)
            if (str_contains($columnItem->nodeHandling(), "x"))  // extended fields marker
                $defaultForm .= $this->addEditFormField($columnItem, $i++);
        if (($i % 2) != 0) $defaultForm .= ",;\n";

        return $defaultForm . "R;submit;" . I18n::getInstance()->t("Er1g83|Save changes") . "\n";
    }

    /**
     * Get a String representing the $row by using its template
     * @param string $templateName the name of the template to use.
     * @param array $row the row, i.e. the record as formatted String to use for the template fill in.
     * @return string the template filled in with the $row.
     */
    public function rowToTemplate(string $templateName, array $row): string
    {
        return $this->toTemplateOrFields($templateName, false, $row);
    }

    /**
     * Get a String representing the record's values by using its template
     * @param string $templateName  the name of the template to use.
     * @return string the template filled in with the record's values.
     */
    public function recordToTemplate(string $templateName): string
    {
        return $this->toTemplateOrFields($templateName, false);
    }

    /**
     * Get an array (field name => count of usages) of all fields used by this template
     * @param string $templateName the name of the template to use.
     * @return array the array of fields used by the template.
     */
    public function templateFields(string $templateName): array
    {
        return $this->toTemplateOrFields($templateName, true);
    }

    /**
     * Comment still to be added /(TODO)/
     * @param string $templateName
     * @param bool $getFields
     * @param array|null $row
     * @return string|array
     */
    private function toTemplateOrFields(string $templateName, bool $getFields, array $row = null): string|array
    {
        $recordTemplates = $this->item->value();
        $recordTemplate = "";
        $usedFields = [];
        $currentTemplate = "";
        foreach ($recordTemplates as $templateDefinition) {
            $pair = explode(":", $templateDefinition, 2);
            $nextTemplate = trim(substr($templateDefinition, strpos($templateDefinition, ":") + 1));
            $currentTemplate = (str_starts_with($nextTemplate, "~"))
                ? $currentTemplate . substr($nextTemplate, 1) : $nextTemplate;
            if ((count($pair) > 1) && ($pair[0] == $templateName))
                $recordTemplate = $currentTemplate;
        }
        $recordTemplate = str_replace(" // ", "\n", $recordTemplate);
        if (is_null($row) && !$getFields)
            // the template fields are used to generate the dynamic list for the name indices.
            // the name indices are used for lookup be the to-display-formatting. This will create
            // an endless loop, therefore, the !$useFields exclusive condition.
            $row = $this->formatToDisplay(Config::getInstance()->language(), true);
        $historyFieldName = Config::getInstance()->getItem(".framework.database_connector.history")->valueStr();
        $language = Config::getInstance()->language();
        foreach ($this->item->getChildren() as $child) {
            $token = "{#" . $child->name() . "#}";
            if (str_contains($recordTemplate, $token)) {
                if ($getFields) {
                    if (!isset($usedFields[$child->name()]))
                        $usedFields[$child->name()] = 0;
                    $usedFields[$child->name()] = $usedFields[$child->name()] + 1;
                } else {
                    $text = (is_null($row))
                        ? $this->valueToDisplay($child, $historyFieldName, $language)
                        : ($row[$child->name()] ?? "");
                    if (strlen($text) > 0)
                        $recordTemplate = str_replace($token, $text, $recordTemplate);
                    else {
                        if (str_contains($recordTemplate, "(" . $token . ")"))
                            $recordTemplate = str_replace("(" . $token . ")", "", $recordTemplate);
                        elseif (str_contains($recordTemplate, "[" . $token . "]"))
                            $recordTemplate = str_replace("[" . $token . "]", "", $recordTemplate);
                        elseif (str_contains($recordTemplate, "<" . $token . ">"))
                            $recordTemplate = str_replace("<" . $token . ">", "", $recordTemplate);
                        else
                            $recordTemplate = str_replace($token, "", $recordTemplate);
                        $recordTemplate = trim($recordTemplate);
                    }
                }
            }
        }
        return ($getFields) ? array_keys($usedFields) : $recordTemplate;
    }

    /**
     * This implementation always returns true.
     */
    // TODO: check what this was meant for
    public function isOk(Record $record, int $mode): bool
    {
        return true;
    }

    /**
     * Write the row to the database. This will store all actual values, but only these.
     * @param Record $record the record to store.
     * @param int $mode the mode to use for storing.
     * @return bool true, if the store was successful.
     */
    private function store(Record $record, int $mode, bool $verifyOnly): bool
    {
        // prepare
        $runner = Runner::getInstance();
        $logger = $runner->logger;
        $tableName = $record->item->name();
        $i18n = I18n::getInstance();
        Findings::clearFindings();

        // parse, validate and format the record if it is to be inserted or updated.
        if (($mode == 1) || ($mode == 2)) {

            // format it into an SQL writable set
            $formatted = $record->format(Language::SQL, false);
            if ((Findings::countErrors() > 0)) {
                $logger->log(LoggerSeverity::INFO, "modifyRecord, validating", json_encode(Findings::getErrors()));
                return false;
            } else if ($runner->debugOn && (Findings::countWarnings() > 0))
                $logger->log(LoggerSeverity::DEBUG, "modifyRecord, validating", json_encode(Findings::getErrors()));

            // now modify the record, all was fine.
            if (!$verifyOnly) {
                $dbc = DatabaseConnector::getInstance();
                if ($mode == 1) {
                    // add creation timestamps
                    if ($this->item->hasChild("created_on"))
                        $formatted["created_on"] = strval(microtime(true));
                    if ($this->item->hasChild("created_by"))
                        $formatted["created_by"] = strval($runner->sessions->userId());
                    // insert record
                    $insertResult = $dbc->insertInto($tableName, $formatted);
                    if (!is_numeric($insertResult)) {
                        Findings::addFinding(6, $i18n->t("tZT02P|Database error: %1. Fail...", $insertResult, $tableName));
                        return false;
                    }
                } else {
                    // update record
                    $updateResult = $dbc->update($tableName, "uid", $formatted);
                    if (strlen($updateResult) > 0) {
                        Findings::addFinding(6, $i18n->t("Jq2YEP|Database error: %1. Fail...", $updateResult, $tableName));
                        return false;
                    }
                }
            }
            // insert or update: all went fine
            return true;

        } elseif ($mode == 3) {
            // special case persons: you must not delete your own user record.
            if (strcasecmp($tableName, "persons") == 0) {
                if (intval($record->value("user_id")) == Sessions::getInstance()->userId())
                    return $i18n->t("UgRFql|Handling error. You are ...");
            }

            // find the record
            $dbc = DatabaseConnector::getInstance();
            $recordToDelete = $dbc->find($tableName, "uid", $record->value("uid"));
            if ($recordToDelete === false) {
                Findings::addFinding(6, $i18n->t("7lryij|Database error. Unable t...",
                    $record->value("uid"), $tableName));
                return false;
            }

            if (!$verifyOnly) {
                // create a rubbish record
                $historyOfDeleted = $recordToDelete["history"];
                unset($recordToDelete["history"]);
                $trashedRecord = json_encode($recordToDelete);
                // limit size to 64k
                $cut_len = 65535 - 4096;
                while (strlen($trashedRecord) > 65535) { // strlen == byte length
                    foreach ($recordToDelete as $key => $value)
                        if (strlen($value) > $cut_len)
                            $recordToDelete[$key] = substr(strval($value), 0, $cut_len);
                    $trashedRecord = json_encode($recordToDelete);
                    $cut_len = $cut_len - 4096;
                }
                $trashedUid = $recordToDelete["uid"];
                $trashRecord = ["uid" => $trashedUid, "author" => Sessions::getInstance()->userId(),
                    "table" => $tableName, "record" => $trashedRecord, "rhistory" => $historyOfDeleted
                ]; // modified will be added by the database.

                // insert the rubbish record
                $insertResult = $dbc->insertInto("trash", $trashRecord);
                if (!is_numeric($insertResult)) {
                    Findings::addFinding(6, $i18n->t("zhlDnH|Database error: %1. Fail...", $insertResult));
                    return false;
                }

                // delete the record
                $deleteResult = $dbc->delete($tableName, ["uid" => $recordToDelete["uid"]]);
                if (strlen($deleteResult) > 0) {
                    // try to remove the trashRecord if the delete fails, but ignore errors for this rubbish bin cleansing
                    $dbc->delete("trash", ["uid" => $trashedUid]);
                    Findings::addFinding(6, $i18n->t("6nchtK|Database error: %1. Fail...", $deleteResult, $tableName));
                    return false;
                }
                // delete: all went fine.
            }
            return true;
        } else {
            Findings::addFinding(6, $i18n->t("lCOAvM|Invalid mofification mod...", $mode));
            return false;
        }
    }

    /**
     * Modify a record. This will parse the $row, apply all syntactic and application-specific
     * semantic checks, and write the record to the database.
     * @param array $row The record, all values as Strings, not yet parsed.
     * @param int $mode 1 = insert, 2 = update, 3 = delete
     * @param Language $language the language to be used for String parsing
     * @param bool $verifyOnly set true to run all checks, without the database write action
     * @return String a String containing a change information on success, else a String describing the errors which
     * occurred starting with an exclamation mark ("!").
     */
    public function modify(array $row, int $mode, Language $language, bool $verifyOnly = false): string
    {

        // parse the provided record
        Findings::clearFindings();
        $changesToActualValues = $this->parse($row, $language, true);
        // do semantic checks and modifications
        $this->preModificationCheck->isOk($this, $mode);
        // for insert and update validate it against the record configuration
        if (($mode == 1) || ($mode == 2))
            $this->validate();
        // and modify
        $modificationSuccess = $this->store($this, $mode, $verifyOnly);
        if (!$modificationSuccess)
            return "!" . Findings::getFindings(false);
        return ($mode == 2) ? $changesToActualValues : "";
    }

}
