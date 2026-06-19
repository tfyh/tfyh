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

namespace tfyh\forms;

use tfyh\control\LoggerSeverity;
use tfyh\control\Runner;
use tfyh\data\Config;
use tfyh\data\DatabaseConnector;
use tfyh\data\Formatter;
use tfyh\data\Ids;
use tfyh\data\Indices;
use tfyh\data\Parser;
use tfyh\data\ParserName;
use tfyh\data\Record;
use tfyh\util\Form;
use tfyh\util\I18n;
use tfyh\util\Language;

/**
 * The form to edit an arbitrary record. This is the generic container, while the form is provided by the Form class.
 */
// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$i18n = I18n::getInstance();
$config = Config::getInstance();
$dbc = DatabaseConnector::getInstance();
$runner = Runner::getInstance();
$todo = ($runner->done == 0) ? 1 : $runner->done;
$formErrors = "";
$formResult = "";

// === APPLICATION LOGIC ==============================================================
$uid = $_SESSION["get_parameters"][$runner->fsId]["uid"] ?? "";
$tableName = $_SESSION["get_parameters"][$runner->fsId]["table"] ?? "";
$insert = false;

$rowSql = [];
$recordItem = $config->getItem(".tables")->getChild($tableName);

// check table name and exit on errors
if (strlen($tableName) == 0)
    $runner->displayError($i18n->t("6yuDuU|Parameters missing"),
        $i18n->t("1UW7zW|Please provide a table n...", $userRequestedFile), $userRequestedFile);
elseif (! $config->getItem(".tables")->hasChild($tableName))
    $runner->displayError($i18n->t("wULfQO|Invalid table name"),
        $i18n->t("o6XxMP|The table name °%1° is n...", $tableName), $userRequestedFile);

// check uid and create new record if uid == "new"
if (strlen($uid) == 0)
    $runner->displayError($i18n->t("QrQ9UO|Missing record uid"),
        $i18n->t("Nbl5vE|Please provide the uniqu...", $tableName), $userRequestedFile);
elseif ($uid === "new") {
    foreach ($recordItem->getChildren() as $column)
        $rowSql[$column->name()] = Formatter::format($column->defaultValue(), $column->type()->parser(), Language::SQL);
    $rowSql["uid"] = ($runner->done == 1) ? Indices::getInstance()->getNewUid() : "new";
    if ($recordItem->hasChild("uuid"))
        $rowSql["uuid"] = ($runner->done == 1) ? Ids::generateUuid() : "new";
    if ($recordItem->hasChild("valid_from"))
        $rowSql["valid_from"] = date("Y-m-d");
    $rowSql["created_on"] = microtime(true);
    $rowSql["created_by"] = $runner->sessions->userId();
    $insert = true;
}
else {
    $dbc = DatabaseConnector::getInstance();
    $rowSql = $dbc->find($tableName, "uid", $uid);
    if ($rowSql === false)
        $runner->displayError($i18n->t("TTKxOm|Record not found."),
            $i18n->t("98iCZZ|Page °%1° must be called...", $userRequestedFile,
                $uid), $userRequestedFile);
    else {
        $formLayout = $recordItem->recordEditForm();
    }
}

// create a record and parse the current values into it.
$record = new Record($recordItem);
$record->parse($rowSql, Language::SQL);

// ======== Start with form filled in last step: check of the entered values.
$newPeriod = false;
$closePeriod = false;
if (($runner->done > 0) && (count($rowSql) > 1)) {
    $formFilled = new Form($recordItem);
    $formFilled->validate();
    $formErrors = $formFilled->formErrors;
    $validatedEntries = $formFilled->getEntered(false);
    // system fields are disabled within the form and therefore not posted. Add them here.
    if ($insert)
        // merge the preset values into the array for insert
        $validatedEntries = array_merge($rowSql, $validatedEntries);
    else
        // and only the uid for update
        $validatedEntries["uid"] = $uid;
    // add the validated entries to the record
    // $changes = $record->parse($validatedEntries, Language::SQL, true);

    // special handling of validity period changes
    // application logic, step by step
    if (strlen($formErrors) == 0) { // do nothing if form errors occurred.
        if ($runner->done == 1) {

            // cover special case of validity period change
            $newPeriod = array_key_exists("valid_from", $validatedEntries);
            $closePeriod = array_key_exists("invalid_from", $validatedEntries);
            if (!$insert && $newPeriod) {
                // This closes the current period at the start date of the new period
                $rowClose = [];
                $rowClose["uid"] = $rowSql["uid"];
                $validFromParsed = Parser::parse($validatedEntries["valid_from"], ParserName::DATE, $config->language());
                $rowClose["invalid_from"] = Formatter::format($validFromParsed, ParserName::DATE, Language::SQL);
                $formErrors .= $dbc->update($tableName, "uid", $rowClose);
                if (strlen($formErrors) == 0) {
                    // and inserts a copy of the record with a new uid and a new valid_from date
                    // the uid is not part of the $_POST array, because the field is disabled in the form.
                    $rowSql["uid"] = Indices::getInstance()->getNewUid();
                    $rowSql["valid_from"] = $rowClose["invalid_from"];
                    unset($rowSql["invalid_from"]);
                    $insertResult = $dbc->insertInto($tableName, $rowSql);
                    if (!is_int($insertResult))
                        $formErrors .= $i18n->t("4IcuWD|The validity was changed...")
                            . $insertResult;
                    else $uid = $rowSql["uid"];
                }
            } else if (!$insert && $closePeriod) {
                // This closes the current period at the invalid_from date provided.
                $rowClose = [];
                $rowClose["uid"] = $rowSql["uid"];
                $invalidFromParsed = Parser::parse($validatedEntries["invalid_from"], ParserName::DATE, $config->language());
                $rowClose["invalid_from"] = Formatter::format($invalidFromParsed, ParserName::DATE, Language::SQL);
                $formErrors .= $dbc->update($tableName, "uid", $rowClose);
            }

            // modify the record and report the changes
            else if (count($validatedEntries) > 0) {
                // store the record. (This will unfortunately apply the same parsing again.)
                $changeResult = $record->modify($validatedEntries, ($insert) ? 1 : 2, $config->language());
                if (str_starts_with($changeResult, "!")) {
                    $formErrors .= "<br />" . $i18n->t("5pH8pE|Database update command ...") . " " . substr($changeResult, 1);
                    $runner->logger->log(LoggerSeverity::INFO, "editRecord",
                        "Edit record failed. Reason: " . $changeResult);
                }
                else $formResult = $changeResult;
            } else {
                $formResult .= $i18n->t("JjCWn9|No modified data has bee...") . "</p>";
            }
            $todo = $runner->done + 1;
        }
    }
}

// ==== continue with the definition and eventually initialisation of form to fill for the next step
if (isset($formFilled) && ($todo == $runner->done)) {
    // redo the 'done' form, if the $to do == $done, i.e. the validation failed.
    $formToFill = $formFilled;
} else {
    // if it is the start or all is fine, use a form for the coming step.
    $formToFill = new Form($recordItem);
    $preset = $record->formatToDisplay(Config::getInstance()->language(), true);
    $formToFill->presetWithStrings($preset);
}

// === PAGE OUTPUT ===================================================================
echo $runner->pageStart();

// page heading, identical for all workflow steps

echo "<h3>" . $i18n->t("s0Odak|Edit a %1-record", $tableName) . "</h3>";

echo Form::formErrorsToHtml($formErrors);
if ($todo == 1) { // step 1. No special texts for output
    // Delete user only as delete of persons' record available
    $form = $formToFill->getHtml();
    echo $formToFill->getHtml();
} else {
    if (strlen($formErrors) > 0)
        echo "<p><b>" . $i18n->t("ao5elo|The record has not been ...") . "</b></p>";
    else
        echo "<p><b>" . $i18n->t("H3YhVM|The record has been chan...") . "</b></p>";
    echo "<p>$formResult</p>";
    $uid = strval($record->value("uid"));
    echo "<p><a href='../../tfyh/pages/viewRecord.php?table=$tableName&uid=$uid'>" .
        $i18n->t("Bz2C2z|View record") . "</a></p>";
    if ($newPeriod)
        echo "<p><a href='../../tfyh/forms/editRecord.php?table=$tableName&uid=$uid'>" .
            $i18n->t("0U5jy1|Edit record for new peri...") . "</a></p>";
}
echo $runner->user2js();
echo "</div>";
$runner->endScript();
