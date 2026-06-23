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

namespace Util;

use Control\Runner;
use Data\Codec;
use Data\Config;
use Data\Findings;
use Data\Formatter;
use Data\Indices;
use Data\Item;
use Data\Parser;
use Data\ParserName;
use Data\ParserConstraints;
use Data\Property;
use Data\PropertyName;
use Data\Record;
use Data\Type;
use Data\Validator;

/**
 * This class provides a form segment for a web file. <p>The definition must be a CSV-file, all entries
 * without line breaks, with the first line being always
 * "tags;modifier;name;value;label;type;class;size;maxlength" and the following lines the respective values.
 * The usage has a lot of options and parameters, please see the tfyh-PHP framework description for
 * details.</p>
 */
class Form
{

    public String $formErrors;
    private array $inputFields;
    private string $fsId;
    // formDefinition and tableName are only remembered to generate the information for the JavaScript FormHandler, which
    // will use it to initialise its shadow form. Both the server side PHP code and the JavaScript code share the same
    // functions to access the form contents
    private String $formDefinition;
    private String $tableName;

    private String $blockCloseTag = "";

    /**
     * Format any form error message to red-coloured html.
     * @param String $formErrors the error message to format. If empty, only an empty String is returned.
     * @param bool $centered whether to center the error message.
     * @return string the formatted error message.
     */
    public static function formErrorsToHtml(String $formErrors, bool $centered = false): string {
        if (strlen($formErrors) == 0)
            return "";
        else {
            $i18n = I18n::getInstance();
            if (! $centered)
                return '<div class="w3-container"><p><span style="color:#A22;"><b>' . $i18n->t("NLNSFH|Error:") .
                    " </b> " . $formErrors . "</span></p></div>";
            else
                return '<p style="text-align:center"><span style="color:#A22;"><b>' . $i18n->t("NLNSFH|Error:") .
                    " </b> " . $formErrors . "</span></p>";
        }
    }

    /**
     * Build a form based on the definition provided in $formDefinitionCsv or the
     * csv file at layout/[formNem].
     * @param Item $item the configuration item of the record to be edited
     * @param string $formDefinitionCsv the optional non-default form definition in csv format.
     */
    public function __construct(Item $item, string $formDefinitionCsv = "")
    {
        $this->fsId = Runner::getInstance()->fsId;
        if (!isset($_SESSION["forms"][$this->fsId]))
            $_SESSION["forms"][$this->fsId] = [];
        $this->init($item, $formDefinitionCsv);
    }

    /* ---------------------------------------------------------------------- */
    /* --------------------- INITIALIZATION --------------------------------- */
    /* ---------------------------------------------------------------------- */
    /**
     * Initialise the form. Separate function to keep aligned with the
     *  twin code, in which external initialisation of a form is used.
     * @param Item $recordItem the configuration item of the record  to be edited
     * @param string $formDefinition the optional non-default form definition in csv format.
     * @return void
     */
    private function init(Item $recordItem, string $formDefinition): void
    {
        // hierarchy of form definitions: 1. explicitly provided, 2.
        // part of the record's properties, 3. auto-generated from the record's properties.
        if (strlen($formDefinition) == 0) {
            $formDefinition = $recordItem->recordEditForm();
            if (strlen($formDefinition) == 0) {
                $recordToEdit = new Record($recordItem);
                $formDefinition = $recordToEdit->defaultEditForm();
            }
        } elseif (!str_starts_with($formDefinition, "rowTag;names;labels"))
            $formDefinition = "rowTag;names;labels\n" . $formDefinition;
        $definitionRows = Codec::csvToMap($formDefinition);
        // remember the definition to provide it to the JavaScript FormHandler
        $this->formDefinition = $formDefinition;
        $this->tableName = $recordItem->name();
        $this->inputFields = [];
        // * = required, . = hidden, ! = read-only, ~ = display value like bold label, § = display headline
        // > = validity period start, < = validity period end
		$modifiers = ["*", ".", "!", "~", "§", ">", "<"];
		$i = 0;
        // collect previously entered fields in a multistep form
        if (isset($_SESSION["forms"][$this->fsId]))
            foreach ($_SESSION["forms"][$this->fsId] as $inputName => $field)
                $this->inputFields[$inputName] = $field;
        $formFieldsItem = Config::getInstance()->getItem(".framework.form_fields");
		foreach ($definitionRows as $definitionRow) {
            $rowTag = str_replace("R", "<div class='w3-row' style='margin-top:0.6em'>",
                str_replace("r", "<div class='w3-row'>", $definitionRow["rowTag"]));
            $names = Parser::parse($definitionRow["names"], ParserName::STRING_LIST, Language::CSV);
            $labels = Parser::parse($definitionRow["labels"], ParserName::STRING_LIST, Language::CSV);
			$columnTag = "<div class='w3-col l" . count($names) . "'>";
			$c = 0;
			foreach ($names as $name) {
                if (strlen($name) > 0) {
                    $inputName = $name;
                    $modifier = mb_substr($name, 0, 1);
                    if (in_array($modifier, $modifiers))
                        $inputName = mb_substr($name, 1);
                    else
                        $modifier = "";
                    if ((strlen($inputName) == 0) || str_starts_with($inputName, "_"))
                        $inputName .= "_$i";

                    $propertyName = PropertyName::valueOfOrInvalid($inputName);
                    $isProperty = $propertyName !== PropertyName::INVALID;
                    $property = Property::$descriptor[$propertyName->value] ?? Property::$invalid;
                    $isActualValue = ($propertyName === PropertyName::ACTUAL_VALUE);
                    // the item to be modified is either a property, a child of the
                    // config item of this form or a generic form field
                    $item = ($isProperty) ? $recordItem : (($recordItem->hasChild($inputName)) ?
                        $recordItem->getChild($inputName) : $formFieldsItem->getChild($inputName));
                    $itemType = (!is_null($item)) ? $item->type() : Type::get("none");
                    if (($modifier === "~") && !$isProperty && is_null($item))
                        // read-only data can get always the same name. This makes then unique.
                        $inputName = $inputName . "_" . $i;
                    $defaultLabel = ($isProperty) ? $property->label() : ((!is_null($item)) ? $item->label() : "");

                    // multistep forms need to cache presets and entries. Each step the form
                    // recall this init() function
                    if (!isset($_SESSION["forms"][$this->fsId][$inputName]))
                        $_SESSION["forms"][$this->fsId][$inputName] = [];

                    // The input field array holds all information, even across multiple steps
                    $id = "input-" . $this->fsId . "-" . $inputName;
                    $this->inputFields[$inputName] = [
                        "openTag" => ($c === 0) ? $rowTag . $columnTag : $columnTag,
                        "closeTag" => ($c === (count($names) - 1)) ? "</div></div>" : "</div>",
                        "html" => "",
                        "modifier" => $modifier,
                        "name" => $inputName,
                        "label" => (isset($labels[$c]) && (strlen($labels[$c]) > 0))
                            ? $labels[$c] : $defaultLabel,
                        "type" => ($isProperty) ? ((Property::isValue($inputName))
                            ? $itemType : Type::get("string")) : $itemType,
                        "inputType" => ($isProperty && !$isActualValue)
                            ? "text" : ((!is_null($item)) ? $item->inputType() : "text"),
                        "id" => $id,
                        "size" => "95%",
                        "options" => [],
                        "preset" => (isset($_SESSION["forms"][$this->fsId][$inputName]["preset"]))
                            ? $_SESSION["forms"][$this->fsId][$inputName]["preset"] : "",
                        "entered" => (isset($_SESSION["forms"][$this->fsId][$inputName]["entered"]))
                            ? $_SESSION["forms"][$this->fsId][$inputName]["entered"] : "",
                        "parsed" => ParserConstraints::empty($itemType->parser()),
                        "findings" => "",
                        "isProperty" => $isProperty,
                        "property" => $property,
                        "item" => $item
                    ];
                    $this->readOptions($this->inputFields[$inputName]);

                } else {
                    // empty names create empty block in the form
                    $inputName = "_" . $i;
                    $id = "input-" . $this->fsId . "-" . $inputName;
                    $this->inputFields[$inputName] = [
                        "openTag" => ($c === 0) ? $rowTag . $columnTag : $columnTag,
                        "closeTag" => ($c === (count($names) - 1)) ? "</div></div>" : "</div>",
                        "html" => "", "modifier" => "~", "name" => $inputName, "label" => "",
                        "type" => Type::get("string"), "inputType" => "text", "id" => $id, "size" => "95%",
                        "options" => [], "preset" => "", "entered" => "", "parsed" => "", "findings" => "",
                        "isProperty" => false, "property" => Property::$invalid,
                        "item" => Config::getInstance()->invalidItem
                    ];
                }
                $c++;
                $i++;
			}
		}
    }

    /**
     * For forms which are not created by this code, but rather provided by the server side, read the options,
     * and the preset and link the form input to the field. => only in JavaScript
     */
    public function parseProvided() {}

    /**
     * Set all autocomplete triggers. => only in JavaScript
     */
    public function setAutocomplete() {}

    /**
     * Set all special input triggers. => only in JavaScript
     */
    public function setSpecialInputTrigger() {}

    /**
     * Read the options into the input field as they are provided by the respective value_reference property.
     * @param array $inputField 1st level array of the input field definition.
     * @return void
     */
    private function readOptions(array &$inputField): void {
        $valueReference = ($inputField["item"]) ? $inputField["item"]->valueReference() : "";
        if (strlen($valueReference) == 0)
            return;

        $inputOptions = [];
        if (str_starts_with($valueReference, "[")) {
            // a predefined configured list
            $options = Parser::parse($valueReference, ParserName::STRING_LIST, Language::CSV);
            foreach ($options as $option) {
                if (str_contains($option, "="))
                    $inputOptions[explode("=", $option)[0]] = explode("=", $option)[1];
                else
                    $inputOptions[$option] = $option;
            }
        } else if (str_starts_with($valueReference, ".")) {
            // an item catalogue
            $headItem = Config::getInstance()->getItem($valueReference);
			foreach ($headItem->getChildren() as $child)
                $inputOptions[$child->name()] = $child->label();
		} else {
            // a table
            $tableName = explode(".", $valueReference)[0];
			$indices = Indices::getInstance();
			$indices->buildIndexOfNames($tableName);
            $inputOptions = $indices->getNames($tableName);
		}
        $inputField["options"] = $inputOptions;
    }

    /**
     * Resolve the entered value as name into an id as defined in the value reference. Returns the id on success and
     * the original value on failure.
     * @param array $inputField 1st level array of the input field definition.
     * @return String the resolved id or the original value.
     */
    private function resolve(array $inputField): String {
        $valueReference = ($inputField["item"]) ? $inputField["item"]->valueReference() : "";
        // TODO, currently item catalogs use no auto-completion
        $toResolve = $inputField["entered"];
        if ((strlen($valueReference) > 0) && ! str_starts_with($valueReference, ".")) {
            // a table as reference
            $tableName = explode(".", $valueReference)[0];
            $referenceField = explode(".", $valueReference)[1];
            $indices = Indices::getInstance();
            $resolved = "";
            if ($referenceField == "uuid") {
                // resolve a name to an uuid
                $values = (ParserName::isList($inputField["type"]->parser())) ? $inputField["parsed"] : [ $toResolve ];
                foreach ($values as $value) {
                    $uuid = $indices->getUuid($tableName, $value);
                    $resolved .= ", " . ((strlen($uuid) == 0) ? $value : substr($uuid, 0,11));
                }
                if (strlen($resolved) > 2)
                    $resolved = mb_substr($resolved, 2);
            }
            return $resolved;
        }
        // for uuid_or_name type fields, a reference may validly not resolvable
        return $toResolve;
    }

    /* ---------------------------------------------------------------------- */
    /* --------------------- PRESET VALUES ---------------------------------- */
    /* ---------------------------------------------------------------------- */
    /**
     * Preset all values of the form with those of the provided row. Strings are shown in the form as is,
     * they must be formatted, and ids resolved. Only dates are reformatted from the local format to the
     * browser-expected YYYY-MM-DD.
     * @param array $row associative array of the row data, all as Strings.
     * @return void
     */
    public function presetWithStrings(array $row): void {
        foreach ($this->inputFields as $fieldName => $fieldDefinition) {
            if (isset($row[$fieldName]))
                $this->presetWithString($fieldName, $row[$fieldName]);
        }
    }
    /**
     * Preset a single value of the form with a formatted and resolved value. Only dates are reformatted from
     * the local format to the browser-expected YYYY-MM-DD.
     * @param string $fieldName the name of the input field.
     * @param string $formattedValue the formatted value to preset.
     * @return void
     */
    private function presetWithString (string $fieldName, string $formattedValue): void {
        if (isset($this->inputFields[$fieldName])) {
            // reformat Date and DateTime to iso compatible
            $parser = $this->inputFields[$fieldName]["type"]->parser();
            $isDateOrDateTime = ($parser === ParserName::DATE) || ($parser === ParserName::DATETIME);
            $language = ($isDateOrDateTime) ? Language::CSV : Config::getInstance()->language();
            $valueStr = ($isDateOrDateTime)
                ? Formatter::format(Parser::parse($formattedValue, $parser, $language), $parser, Language::CSV)
                : $formattedValue;
            // the input field properties "preset" and "entered" are duplicated
            // into the session super-global for caching. The Form class uses the
            // input field properties for processing to be consistent with the JavaScript
            // implementation
            $this->inputFields[$fieldName]["preset"] = $valueStr;
            $_SESSION["forms"][$this->fsId][$fieldName]["preset"] = $valueStr;
        }
    }

    /* ---------------------------------------------------------------------- */
    /* --------------------- DISPLAY FORM AS HTML --------------------------- */
    /* ---------------------------------------------------------------------- */
    /**
     * Return an HTML code of this form based on its definition. Error noticing and
     * step repetition are different in PHP than in JavaScript, because posting a
     * form ends the http transaction. Entered values are kept in the super-global
     * $_SESSION variable, and the error display must be handled by the caller.
     * @param bool $is_file_upload
     *           set true, to enable file-upload. You shall then set a hidden <input type="hidden"
     *           name="MAX_FILE_SIZE" value="30000" /> or similar and use the file upload input such as
     *           name="userfile" type="file". The $_FILES['userfile'] then provides all you need to access
     *           the file.
     */
    public function getHtml (bool $is_file_upload = false): string {
        if (count($this->inputFields) === 0)
            return "<p>Empty form template.</p>"; // no i18n needed, programming error indication

        // start the form. The form has no action in itself and shall not reload
        // when submitting. Therefore, it is implemented as "div" rather than
        // "form"
        $runner = Runner::getInstance();
        $formIndex = ($runner->done == 0) ? 1 : $runner->done;
        $encType = ($is_file_upload) ? 'enctype="multipart/form-data"' : "";
        $formId = $this->fsId . "-" . $this->tableName;
        $form = "<form class='tfyhForm' id='$formId' $encType action='?fSeq=" . $this->fsId . $formIndex . "' method='post'>\n";
        $this->blockCloseTag = "";
        foreach ($this->inputFields as $fieldDefinition)
        	$form .= $this->getFieldHtml($fieldDefinition);
        $form .= $this->blockCloseTag;
        $form .= "</form>\n";
        // provide the definition to the JavaScript FormHandler
        $form .= "<span id='formDefinition-$formId' style='display:none'>" .
            Codec::htmlSpecialChars($this->formDefinition) . "</span>";
        return $form;
    }

    /**
     * Get the html representation of a single field.
     * @param array $f the input field definition.
     * @return string the html code of the input field.
     */
    private function getFieldHtml(array $f): string {

        // start the input field with the label
        $mandatoryStr = ($f["modifier"] == "*") ? "*" : "";
        $isInlineLabel = ($f["inputType"] == "radio") ||
            ($f["inputType"] == "checkbox") || ($f["inputType"] == "input") ||
            !isset($f["label"]) || (strlen($f["label"]) == 0);
        $isList = isset($f["type"]) && ParserName::isList($f["type"]->parser());
        $isDateTime = isset($f["type"]) && ($f["type"]->parser() == ParserName::DATETIME);
        $isValidFrom = ($f["modifier"] == ">");
        $isInvalidFrom = ($f["modifier"] == "<");
        $isHeadline = ($f["modifier"] == "§");
        $isTextArea = str_contains($f["inputType"], "textarea");

        // provide border and label styling. Include the case of invalid input.
        $inputErrorStyleStr = "";
        $labelSpanErrorOpen = "";
        $labelSpanErrorClose = "";
        if (strlen($f["findings"]) != 0) {
            $inputErrorStyleStr = "border:1px solid #A22;border-radius: 0;";
            $labelSpanErrorOpen = "<span style=\"color:#A22;\">";
            $labelSpanErrorClose = "</span>";
        }
        // add size styling
        $isSubmit = (str_contains($f["name"], "submit"));
	    $overflowVisible = ($isSubmit) ? "overflow:visible;" : "";
        $sizeStyleStr = (strlen($f["size"]) > 0) ? "width:" . $f["size"] . ';' . $overflowVisible : "";
	    $styleStr = $inputErrorStyleStr . $sizeStyleStr;
	    $styleStr = (strlen($styleStr > 0)) ? "style='" . $styleStr . "' " : "";

    	// start with tags and show label for input
    	$inputOuterDivOpen = "<div id='div-"  . $f["id"] . "' class='formDiv'>";
        $labelForOpen = ($isHeadline || $isTextArea) ? "" : "<label for='" . $f["id"] . "'>";
        $labelForClose = ($isHeadline || $isTextArea) ? "" : "</label>";
        $labelStr = ($isInlineLabel || $isSubmit) ? "" : $labelSpanErrorOpen . $labelForOpen . $mandatoryStr
            . (Formatter::styleToHtml($f["label"]) ?? "") . $labelForClose . $labelSpanErrorClose . "<br>\n";

    	// predefine values for name, style, id and class attributes.
		$nameStr = (strlen($f["name"]) > 0) ? 'name="' . $f["name"] . '" ' : "";
		$typeStr = (strlen($f["inputType"]) > 0) ? 'type="' .
            ((isset($f["inputType"]) && str_starts_with($f["inputType"], "auto")) ? "text" : $f["inputType"]) . '" ' : "";
    	$idStr = ' id="' . $f["id"] . '" ';
        $classStr = (($f["modifier"] === "~") ? "display-bold"
                : ((isset($f["inputType"]) && str_starts_with($f["inputType"], "auto")) ? "formInput autocomplete"
                    : ((isset($f["inputType"]) && ($f["inputType"] == "select")) ? "formSelector"
                        : (($isSubmit) ? "formButton"
                            : "formInput"))));
        if ($isList)
            $classStr = "listInputField " . $classStr;
        else if ($isValidFrom || $isInvalidFrom)
            $classStr = "validityPeriodInputField " . $classStr;
		$classStr = "class='" . $classStr . "' ";
        $disabledStr = (($f["modifier"] === "!") || ($f["modifier"] === "~")) ? "disabled " : "";

		$inputHtml = "";
        // if a value was previously entered, use it instead of the preset. This happens if a form returns with an
        // error message on some (other) erroneous field.
        if ($f["entered"] && (strlen($f["entered"]) > 0))
            $f["preset"] = $f["entered"];
        if ($isHeadline) {
            $labelStr = "<span id='caret-"  . $f["id"] . "'>&#x25be;</span>&nbsp;<span class='formHeadline'>" . $labelStr . "</span>";
            $inputOuterDivOpen = "<div id='div-"  . $f["id"] . "' class='formDivHeadline'>";
            // compile input element
        } else if (str_contains($f["inputType"], "auto")) {
            // special case: autocompletion field. Autocompletion is a JavaScript function.
            // use default input type.
            $inputHtml .= "<input " . $typeStr . $nameStr . $styleStr . $classStr;
            if ($f["preset"] && (strlen($f["preset"]) > 0))
                $inputHtml .= 'value="' . $f["preset"] . '" ';
            $inputHtml .= $idStr . $disabledStr . ">\n";
            // add options
            $inputHtml .= "<span style='display:none;' id=" . $f["id"] . "-options>";
            foreach ($f["options"] as $option => $label)
                $inputHtml .= $option . "=" . Codec::htmlSpecialChars($label) . "\n";
            $inputHtml .= "</span>\n";
        }
        // compile input element
        else if (str_contains($f["inputType"], "select")) {
            // special-special: a list of selections
            if ($isList) {
                // display as String
                $inputHtml .= "<input " . $typeStr . $nameStr . $styleStr . $classStr;
                // set value.
                if (isset($f["preset"]) && (strlen($f["preset"]) > 0))
                    $inputHtml .= 'value="' . $f["preset"] . '" ';
                $inputHtml .= $idStr . $disabledStr . ">\n";
            } else {
                // special-normal: select field.
                $inputHtml .= "<select " . $nameStr . $styleStr . $classStr . $idStr . $disabledStr . ">\n";
                // code all options as defined
                foreach ($f["options"] as $option => $label) {
                    $selected = ($label === $f["preset"]) ? "selected " : "";
                    $inputHtml .= "<option " . $selected . " value='" . trim($option) . "'>"
                        . trim($label) . "</option>\n";
                }
                $inputHtml .= "</select>\n";
            }
        }
        else if ($f["inputType"] && str_contains($f["inputType"], "radio")) {
            // code all options as defined
            foreach ($f["options"] as $option => $label) {
                // wrap into radiobutton frame first.
                $checked = ($label === $f["preset"]) ? "checked " : "";
                $inputHtml .= "<label class='cb-container'>" . $f["label"] . "\n";
                // no class definitions allowed for radio selections
                $inputHtml .= "<input " . $typeStr . $nameStr . $styleStr . $classStr . " value='" . $option
                    . $checked . $idStr . $disabledStr . '>' . $label . "<br><br>\n";
                $inputHtml .= '<span class="cb-radio"></span></label>';
            }

        } else if ($f["inputType"] && str_contains($f["inputType"], "checkbox")) {
            $inputHtml .= '<label class="cb-container"  style="margin-top:0.5em">' . $f["label"] . "\n";
            // no class definitions allowed for checkboxes
            $inputHtml .= '<input ' . $typeStr . $nameStr . $styleStr;
            // In case of a checkbox, set checked for not-empty other than "false".
            if ($f["preset"] && (strlen($f["preset"]) > 0))
                $inputHtml .= "checked ";
            $inputHtml .= $idStr . $disabledStr . "><span class='cb-checkmark'></span></label>";

        } else if ($isTextArea) {
            if ($f["modifier"] == "~")
                $inputHtml .= '<span>' . $f["preset"] . "</span>\n";
            else
                $inputHtml .= '<textarea ' . $nameStr . $styleStr . $classStr . $idStr . $disabledStr . '>'
                    . $f["preset"] . '</textarea><br>' . "\n";

        } else if ($isDateTime) {
            $presetDate = trim(explode(" ", $f["preset"])[0]);
            $presetTime = substr(trim(explode(" ", $f["preset"])[1]), 0, 5);
            $inputHtml .= "<input type='date' name ='" . $f["name"] . "_d' value='$presetDate' id='" .
                $f["id"] . "_d' " . $classStr . ">";
            $inputHtml .= "&nbsp;<input type='time' name ='" . $f["name"] . "_t' value='$presetTime' id='" .
                $f["id"] . "_t' " . $classStr . ">";

        } else if ($isSubmit) {
            $inputHtml .= "<input " . "type='submit' " . $nameStr . "value='" .
                $f["label"] . "' " . $idStr . $classStr . ">";

        } else if ($f["preset"] || ($f["modifier"] !== "~")) {
            // default input type. (For empty values in display-only mode, skip this.)
            $inputHtml .= "<input " . $typeStr . $nameStr . $styleStr . $classStr;
            // set value.
            if (isset($f["preset"]) && (strlen($f["preset"]) > 0))
                $inputHtml .= 'value="' . $f["preset"] . '" ';
            $inputHtml .= $idStr . $disabledStr . ">\n";
            // add the inline label.
            if ($isInlineLabel)
                $inputHtml .= "&nbsp;" . $labelSpanErrorOpen . $mandatoryStr .
                    ($f["label"] ?? "") . $labelSpanErrorClose . "\n";
        } else
            $labelStr = "<b>" . $labelStr . "</b>";

		// compile the input field
    	$fieldHtml = $f["openTag"] . $inputOuterDivOpen . $labelStr . $inputHtml .
            "</div>" . $f["closeTag"];

        // add the block information
        if ($isSubmit) {
            // The Submit button always closes a block
            $fieldHtml = $this->blockCloseTag . $fieldHtml;
            $this->blockCloseTag = "";
        } elseif ($isHeadline) {
            // a new headline closes the previous block and opens a new one
            $fieldHtml = $this->blockCloseTag . $fieldHtml . "<div id='inputBlock-" . $this->fsId . "-" . $f["name"] . "'>";
            $this->blockCloseTag = "</div>";
        }

        return $fieldHtml;
    }

    /**
     * Get the values which were entered into the form.
     * @param bool $includeUnchanged if true, all values are returned, even those which were not changed.
     *                              if false, only those which were changed are returned.
     * @return array the values of the form fields. The keys are the field names.
     */
    public function getEntered(bool $includeUnchanged = true): array {
        $entered = [];
        foreach($this->inputFields as $fieldName => $f) {
            $matters = !str_starts_with($fieldName, "_") && ($fieldName != "submit");
            if ($matters && ($includeUnchanged || (isset($f["changed"]) && $f["changed"])))
                $entered[$fieldName] = (isset($f["validated"])) ? $f["validated"]
                    : ((isset($f["entered"])) ? $f["entered"]
                       : ((isset($f["preset"])) ? $f["preset"] : ""));
        }
        return $entered;
	}

    /* ---------------------------------------------------------------------- */
    /* --------------- EVALUATION OF FORM ENTRIES --------------------------- */
    /* ---------------------------------------------------------------------- */
    /**
     * Validate an entry of the form. All rules will be applied as configured and the findings collected. If errors were
     * detected, the formErrors field is updated accordingly.
     * @param String $fieldName the name of the field to be validated.
     * @return bool true, if the field was changed.
     */
    private function validateField(String $fieldName): bool {
        $f = &$this->inputFields[$fieldName];
        $f["changed"] = false;
        if ((isset($f["isProperty"]) && $f["isProperty"]) || isset($f["item"])) {
            // only parse data for which a field exists
            $f["entered"] = $this->readField($fieldName);
            $f["findings"] = "";
            if (($f["modifier"] === "*") && (strlen($f["entered"]) === 0))
                $f["findings"] .= I18n::getInstance()->t(
                        "0qqJ5g|Please enter a value in ...", $f["label"]) . ",";
            $f["changed"] = ($f["preset"] !== $f["entered"])
                && ($f["modifier"] != "!") && ($f["modifier"] != "~");
            if ($f["changed"]) {
                // only validate data if changed.
                $language = Config::getInstance()->language();
                // parse (syntactical check)
                Findings::clearFindings();
                $f["parsed"] = Parser::parse($f["entered"], $f["type"]->parser(), $language);
                // validate: limits and reference resolving
                $item = $f["item"];
                if (strlen($item->valueReference()) > 0)
                    $f["validated"] = $this->resolve($f);
                else
                    $f["validated"] = Formatter::format(Validator::adjustToLimits($f["parsed"], $f["type"], $item->valueMin(),
                        $item->valueMax(), $item->valueSize()), $f["type"]->parser(), $language) ;
                // validate: rule check
                Validator::checkAgainstRule($f["validated"], $item->validationRules());
                $f["findings"] = Findings::getFindings(false);
            }
        }
        $this->formErrors .= $f["findings"];
        return $f["changed"];
    }
    /**
     * read and validate all values entered.
     */
    public function validate (): bool {
        $this->formErrors = "";
        $anyChange = false;
        foreach ($this->inputFields as $fieldName => $f)
            if (isset($f["modifier"]) && ($f["modifier"] !== "!") && ($f["modifier"] !== "~"))
                $anyChange = $this->validateField($fieldName) || $anyChange;
        return $anyChange;
	}

    /**
     * Read a single field as it was posted. Note: this differs from the JavaScript implementation. Fields are
     * posted and read from the $_POST super-global or, for multistage forms, from the $_SESSION super-global cache.
     * @param string $fieldName the name of the field to be read.
     * @return string the value of the field.
     */
	private function readField(string $fieldName) : string {
    	if (!isset($this->inputFields[$fieldName]))
            return "";
        $f = &$this->inputFields[$fieldName];
        if ((($f["type"])->parser() == ParserName::DATETIME) && (isset($_POST[$fieldName . "_d"]))) {
            $date = $_POST[$fieldName . "_d"];
            $time = ($_POST[$fieldName . "_t"] ?? "00:00");
            if (strlen($time) > 0)
                $time .= ":00";
            $this->inputFields[$fieldName]["entered"] = (strlen($date) > 0) ? $date . " " . $time : "";
            $_SESSION["forms"][$this->fsId][$fieldName]["entered"] = $this->inputFields[$fieldName]["entered"];
        } else if (isset($_POST[$fieldName])) {
            // the input field properties "preset" and "entered" are duplicated into the session super-global
            // for caching. The Form class uses the input field properties for processing to be consistent with
            // the JavaScript implementation
            $this->inputFields[$fieldName]["entered"] = $_POST[$fieldName];
            $_SESSION["forms"][$this->fsId][$fieldName]["entered"] = $this->inputFields[$fieldName]["entered"];
        } else
            if (isset($_SESSION["forms"][$this->fsId][$fieldName]["entered"]))
                $this->inputFields[$fieldName]["entered"] = $_SESSION["forms"][$this->fsId][$fieldName]["entered"];
        return
            $this->inputFields[$fieldName]["entered"];
    }

}
