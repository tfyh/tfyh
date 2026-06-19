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
namespace tfyh\util;

use tfyh\control\LoggerSeverity;
use tfyh\control\Sessions;
include_once "../_Control/LoggerSeverity.php";
include_once "../_Control/Sessions.php";

use tfyh\data\Codec;
use tfyh\data\Config;
use tfyh\data\DatabaseConnector;
use tfyh\data\Item;
use tfyh\data\Record;
include_once "../_Data/Codec.php";
include_once "../_Data/Config.php";
include_once "../_Data/DatabaseConnector.php";
include_once "../_Data/Item.php";
include_once "../_Data/Record.php";

/**
 * This class provides a list segment for a web file. <p>The definition must be a CSV-file, all entries
 * without line breaks, with the first line being always "id;permission;name;select;from;where;options" and
 * the following lines the respective values. The values in select, from, where and options are combined to
 * create the needed SQL-statement to retrieve the list elements from the database.</p><p>options
 * are<ul><li>sort=[-]column[.[-]column]: order by the respective column in ascending or descending (-)
 * order</li><li>filter=column.value: filter the column for the given value, always using the LIKE operator
 * with '*' before and after the value</li><li>link=[link]: link the first column to the given url e.g.
 * '../_forms/changeUser.php?id=id' replacing the column name at the end (here: id) by the respective
 * value.</li></ul></p> <p>The list is always displayed as a table grid. It will show the default sorting if
 * no sorting option is provided.</p>
 */

class ListHandlerKernel
{
    /**
     * Definition of all lists in the configuration file. Will be read once upon construction from $file_path.
     */
    protected array $listDefinitions;
    /**
     * One list definition is the current. The index points to it, and the private variables are shorthands to it
     */
    protected int $currentListIndex;

    /**
     * the list set chosen
     */
    protected string $set;
    protected string $name;
    protected string $tableName;
    protected array $columns;
    protected Item $recordItem;
    protected Record $record;
    protected int $rowsSqlCount;

    private string $label;
    private string $description;

    /**
     * the list set chosen (lists file name)
     */
    private string $listSetPermissions;
    /**
     * the list of sort options using the format [-]column[.[-]column]
     */
    private string $oSortsList;
    /**
     * the column of the filter option for this list
     */
    private string $oFilter;
    /**
     * the value of the filter option for this list
     */
    private string $oFValue;
    /**
     * the maximum number of rows in the list
     */
    private int $maxRows;

    /**
     * Filter for duplicates, only return the first of multiple. Table must be sorted for that column
     */
    private string $firstOfBlock;

    protected I18n $i18n;

    /**
     * Build a list set based on the definition provided in the configuration (in item '.lists.[set]')' or create a
     * single list dynamically ($set = "@dynamic"). Provide the name of the list to use in $nameOrDefinition or,
     * if dynamic, a definition for a set with a single list. Use the count() function to see how many list definitions
     * were successful.
     * @param string $set the list set to use, or "@dynamic" for a dynamic list
     * @param string $nameOrDefinition the name of the list to use, or a definition for a set with a single list
     * @param array $args an array of arguments to replace in the definition, e.g. ["{my_name}" => "John"]
     */
    public function __construct(string $set, string $nameOrDefinition = "", array $args = [])
    {
        $this->i18n = I18n::getInstance();
        $this->set = $set;
        $this->tableName = "";
        $this->currentListIndex = -1;
        $this->listSetPermissions = "";

        // read definitions from file or string
        if ($set == "@dynamic") {
            $this->listDefinitions = Codec::csvToMap($nameOrDefinition);
            $this->name = $this->listDefinitions[0]["name"];
        } else {
            $this->readSet($set);
            $this->name = $nameOrDefinition;
        }

        // parse definitions for all lists of set and get the current onw.
        for ($i = 0; $i < count($this->listDefinitions); $i++) {
            // join permissions for the entire set
            if (!str_contains($this->listSetPermissions, $this->listDefinitions[$i]["permission"]))
                $this->listSetPermissions .= $this->listDefinitions[$i]["permission"] . ",";
            // replace arguments only for the current list
            if ((strcasecmp($this->listDefinitions[$i]["name"], $this->name) === 0)) {
                $this->currentListIndex = $i;
                $this->label = $this->listDefinitions[$i]["label"] ?? $this->name;
                $this->description = $this->listDefinitions[$i]["description"] ?? "";
                foreach ($this->listDefinitions[$i] as $key => $value)
                    foreach ($args as $template => $used) {
                        // list arguments are values which may be user-defined to avoid SQL infection ";"
                        // is not allowed in these
                        $usedSecure = (str_contains($used, ";")) ? $this->i18n->t("KtXJLq|{invalid parameter with ...") : $used;
                        // replace the template String by the value to use
                        $this->listDefinitions[$i][$key] = str_replace($template, $usedSecure, $this->listDefinitions[$i][$key]);
                    }
            }
        }

        // Parse the current list's definition
        $logger = Config::getInstance()->logger;
        $currentListDefinition = $this->listDefinition();
        if (count($currentListDefinition) > 0) {
            $config = Config::getInstance();
            $current = $this->currentListIndex;
            $this->tableName = $this->listDefinitions[$current]["table"];
            $this->recordItem = $config->getItem(".tables." . $this->tableName);
            if (! $this->recordItem->isValid())
                $logger->log(LoggerSeverity::ERROR, "ListHandlerKernel __construct()",
                    "List of '" . $this->set . "' asks for undefined table: " . $this->tableName);
            $this->record = new Record($this->recordItem);
            $this->parseOptions($this->listDefinitions[$current]["options"]);
            $columnsParsingErrors = "";
            $this->columns = [];
            foreach (explode(",", $this->listDefinitions[$current]["select"]) as $column)
                if ($this->recordItem->hasChild($column))
                    $this->columns[] = $column;
                else
                    $columnsParsingErrors .= "Invalid column name $column in list definition, ";
            if (strlen($columnsParsingErrors) > 0)
                $logger->log(LoggerSeverity::ERROR, "ListHandlerKernel __construct()",
                    "List of '" . $this->set . "' with definition errors: " . $columnsParsingErrors);
        } else {
            // or log an error
            $logger->log(LoggerSeverity::ERROR, "ListHandlerKernel __construct()",
                "Undefined list of set '" . $this->set . "' called: " . $nameOrDefinition);
        }
    }

    /**
     * Parse the list set configuration
     * @param string $set the list set to read. It will be looked up in the configuration at the node: .lists.[set]
     * @return void
     */
    private function readSet(string $set): void {
        $this->listDefinitions = [];
        $setItem = Config::getInstance()->getItem(".lists.$set");
        foreach ($setItem->getChildren() as $listItem) {
            $listDefinition["name"] = $listItem->name();
            $listDefinition["permission"] = $listItem->nodeReadPermissions();
            $listDefinition["label"] = $listItem->label();
            $listDefinition["select"] = $listItem->getChild("select")->valueStr();
            $listDefinition["table"] = $listItem->getChild("table")->valueStr();
            $listDefinition["where"] = $listItem->getChild("where")->valueStr();
            $listDefinition["options"] = $listItem->getChild("options")->valueStr();
            $this->listDefinitions[] = $listDefinition;
        }
    }

    /**
     * Parse the options' String containing the sort and filter options, e.g. "sort=-name&filter=doe" or
     * "sort=ID&link=id=../forms/changePlace.php?id=". Sets: oSortsList, oFilter, oFValue, firstOfBlock,
     * maxRows
     * @param string $optionsList the options String to parse
     * @return void
     */
    private function parseOptions(string $optionsList): void
    {
        $options = explode("&", $optionsList);
        $this->oSortsList = "";
        $this->oFilter = "";
        $this->oFValue = "";
        $this->firstOfBlock = "";
        $this->maxRows = 0; // 0 = no limit.
        foreach ($options as $option) {
            $option_pair = explode("=", $option, 2);
            if (strcasecmp("sort", $option_pair[0]) === 0)
                $this->oSortsList = $option_pair[1];
            if (strcasecmp("filter", $option_pair[0]) === 0)
                $this->oFilter = $option_pair[1];
            if (strcasecmp("fvalue", $option_pair[0]) === 0)
                $this->oFValue = $option_pair[1];
            if (strcasecmp("firstofblock", $option_pair[0]) === 0)
                $this->firstOfBlock = $option_pair[1];
            if (strcasecmp("maxrows", $option_pair[0]) === 0)
                $this->maxRows = intval($option_pair[1]);
        }
    }

    /**
     * Get the entire list definition array of the current list, arguments are replaced. If there is no current list,
     * return an empty array
     */
    public function listDefinition(): array {
        return $this->listDefinitions[$this->currentListIndex] ?? [];
    }

    /**
     * Get the count of list definitions
     */
    public function count(): int { return count($this->listDefinitions); }

    public function noValidCurrentList(): bool {
        return (($this->currentListIndex < 0) ||
            (count($this->listDefinitions[$this->currentListIndex]) <= 1));
    }
    public function getName(): string { return $this->name; }
    public function getLabel(): string { return $this->label ?? $this->name; }
    public function getDescription(): string { return $this->description ?? ""; }
    public function getSetPermission(): string  { return $this->listSetPermissions; }
    public function getPermission(): string { return $this->listDefinition()["permission"]; }
    public function getAllListDefinitions(): array { return $this->listDefinitions; }

    /**
     * Build the database request, i.e. an SQL-statement for the implementation and a filter and sorting for the
     * JavaScript and kotlin implementations (TODO).
     * @param string $oSortsList the list sorting options
     * @param string $oFilter the list filter options
     * @param string $oFValue the list filter value
     * @param int $maxRows the maximum number of rows to return
     * @return string the SQL-statement
     */
    private function buildDatabaseRequest(string $oSortsList, string $oFilter, string $oFValue, int $maxRows): string
    {
        $osl = (strlen($oSortsList) == 0) ? $this->oSortsList : $oSortsList;
        $of = (strlen($oFilter) == 0) ? $this->oFilter : $oFilter;
        $ofv = (strlen($oFValue) == 0) ? $this->oFValue : $oFValue;
        $mxr = ($maxRows == -1) ? $this->maxRows : $maxRows;
        $limit = ($mxr > 0) ? "LIMIT 0, " . $mxr : "";

        // interpret sorts
        $orderBy = "";
        if (strlen($osl) > 0) {
            $oSorts = explode(".", $osl);
            if (count($oSorts) > 0) {
                $orderBy = "ORDER BY ";
                foreach ($oSorts as $oSort) {
                    $sortMode = " ASC,";
                    if (strcasecmp(substr($oSort, 0, 1), "-") === 0) {
                        $sortMode = " DESC,";
                        $oSort = substr($oSort, 1);
                    }
                    if (str_starts_with($oSort, '#'))
                        $orderBy .= "CAST(`" . $this->tableName . "`.`" . substr($oSort, 1) .
                            "` AS UNSIGNED) " . $sortMode;
                    else
                        $orderBy .= "`" . $this->tableName . "`.`" . $oSort . "`" . $sortMode;
                }
                $orderBy = mb_substr($orderBy, 0, mb_strlen($orderBy) - 1);
            }
        }

        // interpret filter
        $where = $this->listDefinitions[$this->currentListIndex]["where"];
        if (str_contains($where, "\$mynumber"))
            $where = str_replace("\$mynumber", Sessions::getInstance()->userId(), $where);
        if ((strlen($of) > 0) && (strlen($ofv) > 0)) {
            $where = "WHERE (" . $where . ") AND (`" . $this->tableName . "`.`" . $of . "` LIKE '" .
                str_replace('*', '%', $ofv) . "')";
        } else {
            $where = "WHERE " . $where;
        }

        // identify selected fields
        $select = "";
        foreach ($this->columns as $column)
            $select .= ", `" . $this->tableName . "`.`" . $column . "`";
        $select = mb_substr($select, 2);

        // assemble SQL-statement
        return "SELECT $select FROM `" . $this->tableName . "` $where $orderBy $limit;";
    }

    /**
     * Provide a list with all data retrieved. The list contains rows of name-to-value pairs, all Strings, as
     * provided by the database
     * @param string $oSortsList the list sorting options
     * @param string $oFilter the list filter options
     * @param string $oFValue the list filter value
     * @param int $maxRows the maximum number of rows to return
     * @return array|string the list of rows, or an error message if the list is not valid.
     */
    private function getRowsSql(string $oSortsList = "", string $oFilter = "", string $oFValue = "",
                               int    $maxRows = -1): array|string
    {
        $rowsSql = [];
        if ($this->noValidCurrentList())
            return "<p>" . $this->i18n->t("3MjQY3|Application configuratio...") . "</p>";

        // normal operation
        $osl = (strlen($oSortsList) == 0) ? $this->oSortsList : $oSortsList;
        $of = (strlen($oFilter) == 0) ? $this->oFilter : $oFilter;
        $mxr = ($maxRows == -1) ? $this->maxRows : $maxRows;
        $ofv = (strlen($oFValue) == 0) ? $this->oFValue : $oFValue;

        // assemble SQL statement and read data
        $sql = $this->buildDatabaseRequest($osl, $of, $ofv, $mxr);
        $dbc = DatabaseConnector::getInstance();
        $res = $dbc->customQuery($sql, $this);
        if ($res === false)
            return $rowsSql;

        // check the firstOfBlock pivoting filter
        $firstOfBlockCol = -1;
        for ($i = 0; $i < count($this->columns); $i++)
            if (strcasecmp($this->firstOfBlock, $this->columns[$i]) == 0)
                $firstOfBlockCol = $i;
        $firstOfBlockFilter = ($firstOfBlockCol >= 0);
        if ((strlen($this->firstOfBlock) > 0) && !$firstOfBlockFilter)
            return I18n::getInstance()->t("5jD9Z0|List definition error. F...");

        // get all rows
        $lastFirstValue = null;
        $this->rowsSqlCount = 0;
        if (isset($res->num_rows) && (intval($res->num_rows) > 0)) {
            $fetchedRow = $res->fetch_row();
            while ($fetchedRow) {
                $this->rowsSqlCount++;
                $filtered = ($firstOfBlockFilter && !is_null($lastFirstValue) && isset($fetchedRow[$firstOfBlockCol]) &&
                    (strcmp(strval($fetchedRow[$firstOfBlockCol]), $lastFirstValue) == 0));
                if (!$filtered) {
                    $namedRow = array();
                    $c = 0;
                    foreach ($this->columns as $column)
                        $namedRow[$column] = $fetchedRow[$c++];
                    $rowsSql[] = $namedRow;
                    if ($firstOfBlockFilter && isset($fetchedRow[$firstOfBlockCol]))
                        $lastFirstValue = strval($fetchedRow[$firstOfBlockCol]);
                }
                $fetchedRow = $res->fetch_row();
            }
        }

        // TODO permissions check. Use $this->record

        return $rowsSql;
    }

    /**
     * Get an array of records as native values.
     * @param string $oSortsList the list sorting options
     * @param string $oFilter the list filter options
     * @param string $oFValue the list filter value
     * @param int $maxRows the maximum number of rows to return
     * @return array|string the array of records, or an error message if the list is not valid.
     */
    public function getRowsNative(string $oSortsList = "", string $oFilter = "", string $oFValue = "",
                            int    $maxRows = -1): array|string
    {
        $rowsSql = $this->getRowsSql($oSortsList, $oFilter, $oFValue, $maxRows);
        if (! is_array($rowsSql))
            return $rowsSql;
        $processedRows = [];
        foreach ($rowsSql as $rowSql) {
            $this->record->parse($rowSql, Language::SQL);
            $processedRows[] = $this->record->values();
        }
        return $processedRows;
    }

    /**
     * get an array of rows according to the format
     * @param string $format "csv" = csv-formatted, e.g. for the api, "localized" = local
     *  language formatted values, "referenced" = local language formatted values with references resolved.
     * @param string $oSortsList the list sorting options
     * @param string $oFilter the list filter options
     * @param string $oFValue the list filter value
     * @param int $maxRows the maximum number of rows to return
     * @return array|string the list of rows, or an error message if the list is not valid.
     */
    public function getRows(string $format, string $oSortsList = "", string $oFilter = "", string $oFValue = "",
                            int    $maxRows = -1): array|string
    {
        $config = Config::getInstance();
        $rowsSql = $this->getRowsSql($oSortsList, $oFilter, $oFValue, $maxRows);
        if (! is_array($rowsSql))
            return $rowsSql;
        $processedRows = [];
        foreach ($rowsSql as $rowSql) {
            $this->record->parse($rowSql, Language::SQL);
            if ($format == "csv")
                $processedRows[] = $this->record->format(Language::CSV, true, $this->columns);
            else if ($format == "localized")
                $processedRows[] = $this->record->format($config->language(), true, $this->columns);
            else if ($format == "referenced")
                $processedRows[] = $this->record->formatToDisplay($config->language(), true, $this->columns);
        }
        return $processedRows;
    }

}
