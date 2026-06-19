<?php

namespace tfyh\util;

use tfyh\control\LoggerSeverity;
use tfyh\control\Runner;
use tfyh\control\Sessions;
include_once "../_Control/LoggerSeverity.php";
include_once "../_Control/Runner.php";
include_once "../_Control/Sessions.php";

use tfyh\data\Codec;
use tfyh\data\Config;
use tfyh\data\Findings;
use tfyh\data\Formatter;
use tfyh\data\Item;
use tfyh\data\Parser;
use tfyh\data\ParserName;
use tfyh\data\ParserConstraints;
use tfyh\data\Validator;
include_once "../_Data/Codec.php";
include_once "../_Data/Config.php";
include_once "../_Data/Formatter.php";
include_once "../_Data/Item.php";
include_once "../_Data/Parser.php";
include_once "../_Data/ParserName.php";
include_once "../_Data/ParserConstraints.php";
include_once "../_Data/Validator.php";

/**
 * This class provides a form segment for a web file. <p>The definition must be a CSV-file, all entries
 * without line breaks, with the first line being always
 * "tags;required;name;value;label;type;class;size;maxlength" and the following lines the respective values.
 * The usage has a lot of options and parameters, please see the tfyh-PHP framework description for
 * details.</p>
 */
class FormBuilder
{

    private array $formDefinition;
    private array $labels;
    private array $validity;
    public string $fsId;
    /**
     * Pass the select options to this String programmatically EITHER as array, e.g. [ "y=yes", "n=no",
     * "d=dunno" ] and use 'select $options' as form layout, OR as per field array, e.g. [ "field1" => [
     * "y=yes", "n=no", "d=dunno" ],"field2" => [ "1=one", "2=two", "3=more" ]] and use 'select
     * $named_options' as form layout.
     */
    public string $selectOptions;
    /**
     * Pass the radio options to this String programmatically as an array, e.g. [ "y=yes", "n=no", "d=dunno" ]
     * and use 'radio $options' as form layout.
     */
    public string $radioOptions;
    private string $formName;
    private int $index;
    private I18n $i18n;

    /**
     * Build a form based on the definition provided in $formDefinitionCsv or the csv file at layout/[formNem].
     * @param string $formDefinitionCsv - optional. If provided, the form definition is taken from this String.
     */
    public function __construct(string $formDefinitionCsv = "")
    {
        $runner = Runner::getInstance();
        $this->formName = substr($runner->userRequestedFile, 0, strpos($runner->userRequestedFile, "."));
        $this->index = ($runner->done == 0) ? 1 : $runner->done;
        $this->fsId = $runner->fsId;
        $this->i18n = I18n::getInstance();
        $this->init($formDefinitionCsv);
    }

    /**
     * Initialise the form. Separate function to keep aligned with the twin code, in which external
     * initialisation of a form is used.
     * @param string $formDefinitionCsv the form definition as csv String.
     * @return void
     */
    private function init(string $formDefinitionCsv): void
    {
        if (strlen($formDefinitionCsv) > 0)
            $formDefinition = Codec::csvToMap($formDefinitionCsv);
        elseif ($this->index <= 1) {
            $formDefinition = Codec::csvFileToMap("layouts/" . $this->formName);
        } else {
            $formDefinition = Codec::csvFileToMap("layouts/" . $this->formName . "_" . $this->index);
        }

        // To be able to reference the field definition by its name, create a named array.
        $iht = 0;
        $ins = 0;
        $this->formDefinition = [];
        foreach ($formDefinition as $fieldDefinition) {

            // check whether i18n replacement is needed
            // NB: "value" is the form definition default and may be replaced
            // by programmatic presetting or previous forms step to provide
            // a string for display.
            if ($this->i18n->isValidI18nReference($fieldDefinition["value"]))
                $fieldDefinition["value"] = $this->i18n->t($fieldDefinition["value"]);
            if ($this->i18n->isValidI18nReference($fieldDefinition["label"]))
                $fieldDefinition["label"] = $this->i18n->t($fieldDefinition["label"]);

            // when creating the named array, take care of special form definition options.
            $helpText = (str_starts_with($fieldDefinition["name"], "_help_text"));
            $noInput = (str_starts_with($fieldDefinition["name"], "_no_input"));
            $subscriptions = (str_starts_with($fieldDefinition["name"], "#Name"));
            $workflows = (str_starts_with($fieldDefinition["name"], "@Name"));
            $concessions = (str_starts_with($fieldDefinition["name"], "\$Name"));
            // make sure all help text definitions have different names in a named array. Their
            // name within the definition is always "_help_text". They will become "_help_text2",
            // "_help_text2" etc. Same with "_no_input".
            if ($helpText) {
                $iht++;
                $this->formDefinition[$fieldDefinition["name"] . $iht] = $fieldDefinition;
            } elseif ($noInput) {
                $ins++;
                $this->formDefinition[$fieldDefinition["name"] . $ins] = $fieldDefinition;
            } elseif ($subscriptions) {
                $this->expandService("subscriptions", $fieldDefinition, '#');
            } elseif ($workflows) {
                $this->expandService("workflows", $fieldDefinition, '@');
            } elseif ($concessions) {
                $this->expandService("concessions", $fieldDefinition, '$');
            } else {
                $this->formDefinition[$fieldDefinition["name"]] = $this->readOptions($fieldDefinition);
            }
        }
        $this->validity = [];
        if (Runner::getInstance()->debugOn) {
            Runner::getInstance()->logger->log(LoggerSeverity::DEBUG, "Form->init()",
                " - Form " . $this->formName . " (" . $this->fsId . "|" . $this->index . ")");
        }
    }

    /**
     * Expand a service (subscription, workflow, concession) definition in a field per service. Service names
     * are already unique and must be used unchanged.
     * @param string $serviceName the service name, e.g. "subscriptions" or "workflows"
     * @param array $fieldDefinition the field definition
     * @param string $identifier the identifier to be replaced by the service name.
     * @return void
     */
    private function expandService(string $serviceName, array $fieldDefinition, string $identifier): void
    {
        $servicesSet = Codec::csvFileToMap("../Config/access/" . $serviceName);
        foreach ($servicesSet as $service) {
            $fieldDefinitionService = $fieldDefinition;
            // replace in all field definition values all workflow keys by their workflow
            // values
            foreach ($fieldDefinition as $key => $value)
                if (str_contains($value, $identifier))
                    foreach ($service as $sKey => $sValue)
                        $fieldDefinitionService[$key] = str_replace($identifier . $sKey, $sValue,
                            $this->i18n->t($fieldDefinitionService[$key]));
            $this->formDefinition[$fieldDefinitionService["name"]] = $fieldDefinitionService;
        }
    }

    /**
     * Read the parameter list for a select field from the database and extend numeric size definitions by
     * 'em' as unit.
     * @param array $fieldDefinition the field definition
     * @return array the field definition with extended size definition.
     */
    private function readOptions(array $fieldDefinition): array
    {
        // expand select options.
        if (str_starts_with(trim(strtolower($fieldDefinition["type"])), "select")) {
            // Todo select use: still needed? removed Dec 2024
            if (str_starts_with(trim(strtolower($fieldDefinition["type"])), "select list:")) {
                // select from a list of lists, e.g. to select a mail distribution list.
                $lookup = explode(":", trim($fieldDefinition["type"]));
                if (count($lookup) == 2) {
                    $list = new ListHandler($lookup[1], 1, []);
                    $listDefinitions = $list->getAllListDefinitions();
                    if (count($listDefinitions) == 0)
                        Runner::getInstance()->displayError("!#" . $this->i18n->t("3VwQtV|Configuration error."),
                            $this->i18n->t("CBjPta|List configuration not f..."), __FILE__);
                    // for list option listing check the entries against allowances.
                    $selectString = "";
                    foreach ($listDefinitions as $listDefinition) {
                        $list_name = $listDefinition["name"];
                        $list_id = intval($listDefinition["id"]);
                        $test_list = new ListHandler($lookup[1], $list_id, []);
                        if (Runner::getInstance()->users->isAllowedItem($test_list->getPermission()))
                            $selectString .= $list_id . "=" . $list_name . ";";
                    }
                    if (strlen($selectString) == 0)
                        $selectString = $this->i18n->t("lIzc0X|noListForThisRole") . "=" . $this->i18n->t("yGorGi|noListForThisRole");
                    $selectString = "select " . $selectString;
                    $fieldDefinition["type"] = mb_substr($selectString, 0, mb_strlen($selectString) - 1);
                } // select from a list which has the value in column 1 and the displayed String in column 2.
                elseif (count($lookup) == 3) {
                    $selectString = "";
                    if (!strpos($lookup[2], "+")) {
                        $list_id = intval($lookup[2]);
                    } else {
                        $list_id = intval(mb_substr($lookup[2], 0, mb_strlen($lookup[2]) - 1));
                        $selectString .= "-1=" . $this->i18n->t("WjRsQw|(empty)") . ";";
                    }
                    $list = new ListHandler($lookup[1], $list_id, []);
                    $listedOptions = $list->getRows("csv");
                    $keyColumn = $list->getColumnName(0);
                    $valueColumn = $list->getColumnName(1);
                    foreach ($listedOptions as $listedOption)
                        $selectString .= $listedOption[$keyColumn] . "=" . $listedOption[$valueColumn] . ";";
                    if (strlen($selectString) == 0)
                        $selectString = $this->i18n->t("bNnasv|noValues") . "=" . $this->i18n->t("8qQEHT|noValues");
                    $selectString = "select " . $selectString;
                    $fieldDefinition["type"] = mb_substr($selectString, 0, mb_strlen($selectString) - 1);
                } else {
                    $fieldDefinition["type"] = $this->i18n->t("P0YpJt|select config_error");
                }
            }
        }
        // add unit "em" if the size has no unit. For textarea this is in maxlength.
        if (strval(intval($fieldDefinition["size"])) == $fieldDefinition["size"]) {
            $fieldDefinition["size"] = $fieldDefinition["size"] . "em";
        }
        if (str_starts_with(trim(strtolower($fieldDefinition["type"])), "select use:")) {
            if (strval(intval($fieldDefinition["maxlength"])) == $fieldDefinition["maxlength"]) {
                $fieldDefinition["maxlength"] = $fieldDefinition["maxlength"] . "em";
            }
        }
        return $fieldDefinition;
    }

    /**
     * @return int the index of the form.
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * Return the html code of the help text.
     */
    public function getHelpHtml(): string
    {
        $form = "<h5><br />" . $this->i18n->t("Fiwrt1|Please note") . "</h5><ul>";
        $l = 0;
        foreach ($this->formDefinition as $f) {
            // the fo definition contains both the form and some help text,
            // usually displayed within
            // a right border frame. Only the help text shall be returned in
            // this function
            if (str_starts_with($f["name"], "_help_text")) {
                $form .= $f["tags"] . $f["label"] . "\n";
                $l++;
            }
        }
        $form .= "</ul>";
        return ($l > 0) ? $form : "";
    }

    /**
     * split type definition into 'select' and options
     * @param array $f the field definition
     * @return array the options array
     */
    private function splitOptionsArray(array $f): array {
        // split type definition into 'select' and options
        $options = substr($f["type"], strpos($f["type"], " ") + 1);
        if (strcasecmp($options, "\$options") == 0)
            $options = $this->selectOptions;
        elseif (strcasecmp($options, "\$named_options") == 0)
            $options = $this->selectOptions[$f["name"]];
        return explode(";", $options);
    }

    /**
     * Return the html code of this form based on its definition. Will not return the help text.
     * @param bool $isFileUpload true if the form shall be used for file upload.
     * @param string $getParameter the parameter string to be appended to the form action.
     * @return string the html code of the form.
     */
    public function get_html(bool $isFileUpload = false, string $getParameter = ""): string
    {
        if (count($this->formDefinition) == 0)
            return "";
        // start the form.
        if (strlen($getParameter) > 0)
            $getParameter = "&" . $getParameter;
        if ($isFileUpload)
            $form = '		<form enctype="multipart/form-data" action="?fSeq=' . $this->fsId . $this->index .
                $getParameter . '" method="post">' . "\n";
        else
            $form = '		<form action="?fSeq=' . $this->fsId . $this->index . $getParameter .
                '" method="post">' . "\n";
        // ---------------------------------------------------------
        // Buld form as a table of input fields. Tags define columns
        // ---------------------------------------------------------
        foreach ($this->formDefinition as $f) {
            // start the input field with the label
            $mandatoryFlag = (strlen($f["required"]) > 0) ? "*" : "";
            // horizontal radio buttons
            $inlineLabel = (strcasecmp("radio", $f["type"]) === 0) ||
                (strcasecmp("checkbox", $f["type"]) === 0) || (strcasecmp("input", $f["type"]) === 0) ||
                (strlen($f["label"]) === 0);

            // the form definition contains both the form and some help text, usually displayed
            // within a right border frame. The help text shall not be returned in this function
            $helpText = isset($f["name"]) && (str_starts_with($f["name"], "_help_text"));
            $noInput = isset($f["name"]) && (str_starts_with($f["name"], "_no_input"));

            // provide border and label styling. Include a case of invalid input.
            $styleStr = "";
            $validityLabelStyleOpen = "";
            $validityLabelStyleClose = "";
            if (!isset($this->validity) && $this->validity[$f["name"]] === false) {
                $styleStr = 'style="' . $styleStr . ';border:1px solid #A22;border-radius: 0px;" ';
                $validityLabelStyleOpen = "<span style=\"color:#A22;\">";
                $validityLabelStyleClose = "</span>";
            } elseif (str_contains($f["type"], "textarea"))
                $styleStr .= ' "cols="' . $f["maxlength"] . '" rows="' .
                    ((isset($f["size"]) && (intval($f["size"]) > 0)) ? $f["size"] : 4) . '" ';
            elseif (strlen($f["size"]) > 0)
                $styleStr = 'style="width:' . $f["size"] . ';" ';

            // show label for input
            if (!$helpText) {
                if ($inlineLabel) // radio and checkbox
                    $form .= $f["tags"];
                else // includes "_no_input"
                    $form .= $f["tags"] . $validityLabelStyleOpen . $mandatoryFlag . $f["label"] .
                        $validityLabelStyleClose . "<br>\n";
            }
            // now provide the previously entered or programmatically provided value. Wrap with
            // htmlSpecialChars to prevent from XSS
            // https://stackoverflow.com/questions/1996122/how-to-prevent-xss-with-html-php
            // $_SESSION["forms"][$this->fs_id][$f["name"]] reflects the previously entered String
            $forDisplay = (isset($_SESSION["forms"][$this->fsId][$f["name"]]) &&
                is_string($_SESSION["forms"][$this->fsId][$f["name"]])) ? htmlspecialchars(
                $_SESSION["forms"][$this->fsId][$f["name"]], ENT_QUOTES, 'UTF-8') : false;
            // if there is no previously entered field, but a default value set by the form, use
            // this
            // default.
            if (($forDisplay === false) && isset($f["value"])) {
                if (str_starts_with($f["value"], "\$now"))
                    // special case date of now
                    $forDisplay = date("Y-m-d");
                else
                    // all other cases
                    $forDisplay = $f["value"];
            }

            // compile all attribute definitions
            $typeStr = (strlen($f["type"]) > 0) ? 'type="' . $f["type"] . '" ' : "";
            $nameStr = (strlen($f["name"]) > 0) ? 'name="' . $f["name"] . '" ' : "";
            $idStr = 'id="cFormInput-' . $f["name"] . '" ';
            // do not use the name for the Submit button as id
            if (str_contains(strtolower($f["type"]), "submit"))
                $idStr = 'id="cFormInput-submit" ';
            // set default first
            $classStr = 'class="formInput" ';
            if (strlen($f["class"]) > 0) {
                // special case: dedicated ID attribute within the class field
                if (str_starts_with($f["class"], '#'))
                    $idStr = 'id="' . substr($f["class"], 1) . '" ';
                else
                    $classStr = 'class="' . $f["class"] . '" ';
            }
            $disabledFlag = (strcmp($f["required"], "!") == 0) ? "disabled" : "";

            // do not use invalid values for preset
            if (isset($this->validity[$f["name"]]) && ($this->validity[$f["name"]] === false))
                $forDisplay = null;
            // special case: select field.
            if (str_contains($f["type"], "select")) {
                // ---------------------------
                // special case: select field.
                // ---------------------------
                $classStr = 'class="formSelector" ';
                $form .= "<select " . $nameStr . $styleStr . $classStr . $idStr . $disabledFlag . ">\n";

                // code all options as defined
                $optionsArray = $this->splitOptionsArray($f);
                foreach ($optionsArray as $option) {
                    $nvp = explode("=", $option);
                    $selected = (strcasecmp($nvp[0], $forDisplay) == 0) ? "selected " : "";
                    $form .= '<option ' . $selected . ' value="' . trim($nvp[0]) . '">' . trim($nvp[1]) .
                        "</option>\n";
                }
                $form .= "</select>\n";
            } elseif ((str_contains($f["type"], "radio"))) {
                // --------------------------------------------------------
                // special case: radio group (similar to select field case)
                // --------------------------------------------------------
                // split type definition into 'radio' and options
                $options = substr($f["type"], strpos($f["type"], " ") + 1);
                if (strcasecmp($options, "\$options") == 0)
                    $optionsArray = $this->radioOptions;
                else
                    $optionsArray = explode(";", $options);
                // code all options as defined
                $o = 1;
                foreach ($optionsArray as $option) {
                    $nvp = explode("=", $option);
                    $checked = ((strcasecmp($nvp[0], $forDisplay) === 0)) ? "checked " : "";
                    $form .= '<label class="cb-container">' . $f["label"] . "\n";
                    // no style or class definitions allowed for radio selections
                    $form .= '<input type="radio" ' . $nameStr . $styleStr . 'value="' . $nvp[0] . '" ' .
                        $checked . str_replace('" ', '-' . $o++ . '" ', $idStr) . $disabledFlag . '>' .
                        $nvp[1];
                    $form .= '<span class="cb-radio"></span></label>' . "\n";
                }
            } elseif (str_contains($f["type"], "checkbox")) {
                // -----------------------------
                // special case: checkbox input
                // -----------------------------
                // In case of a checkbox, set checked for value "on" and set the class to checked-on
                // or off to keep track of the state. This is needed due to the CSS styles using the
                // ::after
                // property which cannot be queried.
                $checked = ((strlen($forDisplay) > 0) && (strcmp($forDisplay, "false") != 0)) ? 'checked class="checked-on" ' : 'class="checked-off" ';
                $form .= '<label class="cb-container">' . $f["label"] . "\n";
                // no class definitions allowed for checkboxes
                $form .= '<input ' . $typeStr . $nameStr . $styleStr . $checked . $idStr . $disabledFlag .
                    '>';
                $form .= '<span class="cb-checkmark"></span></label>';
            } elseif (str_contains($f["type"], "textarea")) {
                // -----------------------------
                // special case: text area input
                // -----------------------------
                if ($forDisplay === false)
                    $forDisplay = "";
                $boxSize = ' cols="' . $f["maxlength"] . '" rows="' . $f["size"] . '"';
                $form .= '<textarea ' . $nameStr . $boxSize . $classStr . $idStr . $disabledFlag . '>' .
                    $forDisplay . '</textarea><br>' . "\n";
            } elseif (!$helpText && !$noInput && (strlen($f["name"]) > 0)) {
                // -----------------------------
                // default input type
                // -----------------------------
                $form .= "<input " . $typeStr . $nameStr . $styleStr . $classStr;
                if (strlen($f["maxlength"]) > 0)
                    $form .= 'maxlength="' . $f["maxlength"] . '" ';
                // set value.
                if (strlen($forDisplay) > 0)
                    $form .= 'value="' . $forDisplay . '" ';
                $form .= $idStr . $disabledFlag . ">\n";
                // add the inline label.
                if ($inlineLabel)
                    $form .= "&nbsp;" . $validityLabelStyleOpen . $mandatoryFlag . $f["label"] .
                        $validityLabelStyleClose . "\n";
            }
        }
        // ----------------------------
        // Table for form is completed.
        // ----------------------------
        $form .= "	</form>\n";
        return $form;
    }

    /**
     * Read all values into the array of the super-global $_SESSION for this form object as they were provided
     * via the POST method. This function will use all entered data of the form object, i.e. empty form
     * inputs will delete a previously set value within a field. No validation applies at this point.
     */
    public function read_entered(): void
    {
        $this->labels = [];
        foreach ($this->formDefinition as $f) {
            $this->labels[$f["name"]] = $f["label"];
            $value = (isset($_POST[$f["name"]])) ? $_POST[$f["name"]] : "";
            // trim value to avoid preceding or trailing blanks
            $value = trim($value);
            // TODO proper parsing
            if ($this->isDate($f))
                $_SESSION["forms"][$this->fsId][$f["name"]] = Formatter::format(
                    Parser::parse($value, ParserName::DATE, Config::getInstance()->language()), ParserName::DATE);
            elseif ($this->isField($f))
                $_SESSION["forms"][$this->fsId][$f["name"]] = $value;
        }
    }

    /**
     * Check whether this field is a form field
     * @param array $fieldDefinition the field definition to check against
     * @return bool true if the field is a form field, false otherwise.
     */
    private function isField(array $fieldDefinition): bool
    {
        return (strcasecmp("submit", $fieldDefinition["type"]) !== 0) && (strlen($fieldDefinition["name"]) > 0) &&
            (!str_starts_with($fieldDefinition["name"], "_help_text")) &&
            (!str_starts_with($fieldDefinition["name"], "_no_input"));
    }

    /**
     * Check whether this field is a date type field
     * @param array $fieldDefinition the field definition to check against
     * @return bool true if the field is a date type field, false otherwise.
     */
    private function isDate(array $fieldDefinition): bool
    {
        return (strcasecmp($fieldDefinition["type"], "date") === 0);
    }

    /**
     * preset all values of the form with those of the provided array with array($key, $value) being put to
     * the form object input array ($key, value) if in the form object inputs array such key exists.
     */
    public function presetValues(array $valuesRecord, bool $keepHiddenDefaults = false): void
    {
        foreach ($this->formDefinition as $f) {
            if (isset($valuesRecord[$f["name"]]) && (!$keepHiddenDefaults ||
                    (isset($f["type"]) && (strcasecmp($f["type"], "hidden") != 0)))) {
                $_SESSION["forms"][$this->fsId][$f["name"]] = strval($valuesRecord[$f["name"]]);
            }
        }
    }

    /**
     * Preset a single value of the form object. If the key is not a field name of the form, this will have no
     * effect. Value must be a UTF-8 encoded String. To preset a subscription or a workflow form input, please
     * provide the $key '#', '@' or '$' followed by 'Name' respectively and as $value the bitmask as String-formatted
     * integer (radix 10).
     */
    public function presetValue(string $key, string $valueStr): void
    {
        foreach ($this->formDefinition as $f)
            // only set the value if field name is existing.
            if (strcmp($f["name"], $key) === 0) {
                if ((str_contains($f["type"], "select")) && (str_starts_with($valueStr, '~'))) {
                    // if the value starts with '~' it refers to the index of the option
                    $pos = intval(substr($valueStr, 1)) - 1;
                    $optionsArray = $this->splitOptionsArray($f);
                    $_SESSION["forms"][$this->fsId][$key] = trim(explode("=", $optionsArray[$pos])[0]);
                } else {
                    $_SESSION["forms"][$this->fsId][$key] = $valueStr;
                }
            }
    }

    /**
     * A simple getter of user-entered data as $key => $value.
     */
    public function getEntered()
    {
        return $_SESSION["forms"][$this->fsId];
    }

    /**
     * A simple getter of labels (user visible field descriptions) as $key => $label. Cf. get_entered()
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * Get the definition of a field with the given name and its current value.
     * @param string $name the name of the field to get the definition for.
     * @return array|null the field definition as array or null, if the field does not exist.
     */
    public function getField(string $name): ?array
    {
        if (!isset($this->formDefinition[$name]))
            return null;
        return array_map(function ($value) {
            return $value;
        }, $this->formDefinition[$name]);
    }

    /**
     * Set an input fields validity. If set to false, the input will be marked as invalid when the form
     * is redisplayed.
     */
    public function setInputValidity(string $key, bool $is_valid): void
    {
        $this->validity[$key] = $is_valid;
    }

    /**
     * Check the validity of all inputs within the form. Uses the type declaration in the form to deduct the
     * required data type and the "required" field to decide whether a field must be filled or not. Will also
     * return an error if the value contains a '<' and the word 'script' to prevent from cross-site
     * scripting.
     */
    public function checkValidity(): string
    {
        // TODO new validity check logic to apply.
        $formErrors = "";
        if (!isset($_SESSION["forms"][$this->fsId]) || !is_array($_SESSION["forms"][$this->fsId]))
            return "";
        foreach ($_SESSION["forms"][$this->fsId] as $key => $value) {
            $definition = $this->formDefinition[$key] ?? false;
            if ($definition === false)
                Runner::getInstance()->logger->log(LoggerSeverity::WARNING, Sessions::getInstance()->userId(),
                    "Form data key '$key' does not correspond to a field key of form '" .
                    $this->formName . "', step " . $this->index);
            else {
                // check empty inputs. They always comply to the format if no entry was required.
                if (strlen($value) < 1) {
                    // input is empty
                    if (strlen($definition["required"]) > 0) {
                        // input is required
                        $formErrors .= $this->i18n->t("AdwVBx|Please at °") . $definition["label"];
                        if (strcmp($definition["type"], "checkbox") === 0)
                            $formErrors .= '" ' . $this->i18n->t('iPXyEn|set the tick.') . '<br>';
                        else
                            $formErrors .= '" ' . $this->i18n->t('H6xh5s|enter a value.') . '<br>';
                        $this->validity[$key] = false;
                    }
                } else {
                    // now check provided value on format compliance if the type parameter is set
                    if (isset($definition["type"])) {
                        $type = $definition["type"];
                        if (strcmp($type, "email") === 0) {
                            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $formErrors .= $this->i18n->t('6ytHoP|Please enter a valid ema...', $definition["label"]) .
                                    '<br>';
                                $this->validity[$key] = false;
                            }
                        } elseif (strcmp($type, "date") === 0) {
                            if (ParserConstraints::isEmpty(Parser::parse($value, ParserName::DATE, Config::getInstance()->language()),
                                ParserName::DATE)) {
                                $formErrors .= $this->i18n->t('arUUZe|Please enter a valid dat...', $definition["label"],
                                        $value) . '<br>';
                                $this->validity[$key] = false;
                            }
                        } elseif ((strcmp($type, "password") === 0)) {
                            Validator::checkPassword($value);
                            $errors = Findings::getFindings(false);
                            if (strlen($errors) > 0) {
                                $formErrors .= $this->i18n->t('W37MTN|The password is not secu...', $definition["label"],
                                        $errors) . '<br>';
                                $this->validity[$key] = false;
                            }
                        }
                    }
                }
            }
        }
        return $formErrors;
    }

    /**
     * Expand a shorthand form configuration row, which does only provide
     * 'tags;name;label'. An example for a short definition row is:
     * '_//_r;~uid|value_reference|*text_local_name;'. That will be expanded to
     * 'tags;required;name;label' with a row per field, i.e. '_//_r_3;~;uid;
     * (mew line) _/_3;;value_reference; (mew line) _/_3;*;text_local_name;'
     * @param array $rowDefinition the definition row to expand.
     * @param array $expandedFormConfig the expanded form configuration array.
     * @return void
     */
    private function expandShortDefinitionRow(array $rowDefinition, array &$expandedFormConfig): void
    {
        $names = explode( "|", $rowDefinition["name"]);
        $tags = $rowDefinition["tags"] . "_" . count($names);
        $firstName = true;
        foreach ($names as $name) {
            $shortDefinition = [];
            $shortDefinition["tags"] = ($firstName) ? $tags :
                str_replace("/_R", "", str_replace("/_r", "", $tags));
            $firstName = false;
            $shortDefinition["required"] = (str_starts_with($name, "*") || str_starts_with($name, "!") ||
                str_starts_with($name, "~")) ? substr($name, 0, 1) : "";
            $shortDefinition["name"] = substr($name, strlen($shortDefinition["required"]));
            if (isset($rowDefinition["label"]))
                $shortDefinition["label"] = $rowDefinition["label"];
            $expandedFormConfig[] = $shortDefinition;
        }
    }

    /**
     * Expand a shorthand form configuration row, which does only provide
     * 'tags;name;label'. An example for a short definition row is:
     * '_//_r;~uid|value_reference|*text_local_name;'. That will be expanded to
     * 'tags;required;name;label' with a row per field, i.e. '_//_r_3;~;uid;
     * (mew line) _/_3;;value_reference; (mew line) _/_3;*;text_local_name;'
     * @param String $shortDefinition the definition row to expand.
     * @param Item $recordItem the expanded form configuration array.
     * @return void
     */
    private function expandShortDefinition(String $shortDefinition, Item $recordItem): void {
        $expandedFormConfig = [];
        $formConfig = Codec::csvToMap($shortDefinition);
        foreach ($formConfig as $fieldDefinition)
            $this->expandShortDefinitionRow($fieldDefinition, $expandedFormConfig);
        $formConfig = $expandedFormConfig;
  	    for ($i = 0; $i < count($formConfig); $i++) {
            // set default values first
            $formConfig[$i]["type"] = "text";
            $formConfig[$i]["class"] = "";
            $formConfig[$i]["size"] = $this->getWidth($formConfig[$i]["tags"]);
            $formConfig[$i]["maxlength"] = "256";
            $name = $formConfig[$i]["name"];
            $columnConfig = $recordItem->getChild($name);
            if ($columnConfig->isValid()) {
                $formConfig[$i]["label"] = $columnConfig->label();
                $formConfig[$i]["input_type"] = $columnConfig->inputType();
                $formConfig[$i]["input_modifier"] = $columnConfig->inputType();
                $formConfig[$i]["value_reference"] = $columnConfig->valueReference();
                $formConfig[$i]["data_type"] = $columnConfig->valueType();
            } elseif (strcasecmp($name, "submit") == 0) {
                // special case submit button
                $formConfig[$i]["class"] = "formButton";
                $formConfig[$i]["value"] = $formConfig[$i]["label"];
                unset($formConfig[$i]["size"]);
                unset($formConfig[$i]["label"]);
                unset($formConfig[$i]["maxlength"]);
            }
        }
    }

    /**
     * Get the appropriate relative width depending on the column class (l1 to l6).
     */
    private function getWidth (String $tags): string
    {
        $columnsCnt = "?";
        if (str_starts_with($tags, "_"))
            $columnsCnt = substr($tags, strlen($tags) - 1);
        else {
            $colClassSearchText = "<div class='w3-col l";
            $colClassPosition = strpos($tags, $colClassSearchText);
            if ($colClassPosition !== false) {
                $cCntPos = $colClassPosition + strlen($colClassSearchText);
                $columnsCnt = substr($tags, $cCntPos, 1);
            }
        }
        if (!is_numeric($columnsCnt))
            return '96%';
        return "" . (100 - (intval($columnsCnt) * 2)) . "%";
    }

}
