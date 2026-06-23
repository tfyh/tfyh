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

use Control\Runner;
use Data\Codec;
use Data\Config;
use Data\Findings;
use Data\Record;
use Util\Form;
use Util\FormBuilder;
use Util\I18n;

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$i18n = I18n::getInstance();
$runner = Runner::getInstance();
$todo = ($runner->done == 0) ? 1 : $runner->done;
$formErrors = "";
$importResult = "";

// === APPLICATION LOGIC ==============================================================
// ======== Start with form filled in last step: check of the entered values.
if ($runner->done > 0) {
    $formFilled = new FormBuilder();
    $formFilled->read_entered();
    $formErrors = $formFilled->checkValidity();
    $enteredData = $formFilled->getEntered();
    $tableName = ($runner->done == 1) ? $enteredData["table_name"] : $_SESSION["io_table"];
    $temporaryFilePath = "../../var/Run/io/" . $_SESSION["io_file"];

    function modify(String $tableName, String $temporaryFilePath, bool $verifyOnly): string
    {
        // the following statement application specific. A record class which implements a
        // modifyRecord function like needs to be implemented in all apps that are using this form.
        $r = 0;
        $allOk = true;
        $importResult = "";
        $recordItem = Config::getInstance()->getItem(".tables." . $tableName);
        $rowsCsv = Codec::csvFileToMap($temporaryFilePath);
        $record = new Record($recordItem);

        foreach ($rowsCsv as $rowCsv) {
            $mode = (isset($record["uid"])) ? (((count($rowCsv)) == 1) ? 3 : 2) : 1;
            $modifyResult = $record->modify($rowCsv, $mode, Config::getInstance()->language(), $verifyOnly);
            if (str_starts_with($modifyResult, "!")) {
                $allOk = false;
                $importResult .= "#" . $r . ": " . Findings::getFindings(true) . "<br>";
            } else {
                $importResult .= "#" . $r . ": ok.<br>";
            }
        }
        if ($allOk)
            $importResult = "ok";
        return $importResult;
    }

    // application logic, step by step
    if (strlen($formErrors) == 0) { // do nothing if form errors occurred.
        if ($runner->done == 1) {
            $_SESSION["io_table"] = $tableName;
            // step 1 form was filled. Values were valid
            if (strlen($_FILES['userfile']["name"]) < 1) {
                // Special case upload error. Userfile cannot be checked after
                // being entered, must be checked
                // after upload was tried.
                $formErrors .= $i18n->t("mYd52x|No file specified. Pleas...");
            } else {
                $tmp_upload_file = file_get_contents($_FILES['userfile']["tmp_name"]);
                if (!$tmp_upload_file)
                    $formErrors .= $i18n->t("xJsUjf|Unknown error during upl...");
                else {
                    $_SESSION["io_file"] = $_FILES['userfile']["name"];
                    $_SESSION["io_table"] = $enteredData["table_name"];
                    file_put_contents($temporaryFilePath, $tmp_upload_file);
                    // do import verification
                    $importResult = modify($tableName, $temporaryFilePath, true);
                    if ($importResult == "ok")
                        $todo = $runner->done + 1;
                    else
                        $formErrors .= $importResult;
                }
            }
        }
    } elseif ($runner->done == 2) {
        // step 2 form was filled. Values were valid. Now execute import.
        $importResult = modify($tableName, $temporaryFilePath, false);
        if ($importResult == "ok")
            $todo = $runner->done + 1;
        else
            $formErrors .= $importResult;
    }
}

// ==== continue with the definition and eventually initialisation of form to fill for the next step
if (isset($formFilled) && ($todo == $formFilled->getIndex())) {
    // redo the 'done' form, if the $to do == $done, i.e. the validation failed.
    $formToFill = $formFilled;
} else {
    // if it is the start or all is fine, use a form for the coming step.
    $formToFill = new FormBuilder();
}

// === PAGE OUTPUT ===================================================================
echo $runner->pageStart();

// page heading, identical for all workflow steps
echo "<h3>" . $i18n->t("17392l|Import table") . "</h3>";
echo "<p>" . $i18n->t("p4vCuA|This form is import a ta...") . "</p>";

if ($todo == 1) { // step 1. Texts for output
    echo "<p>" . $i18n->t("UTmyA8|For importing an ID must...") . "</p>";
    echo "<p>" . $i18n->t("nBgz5e|Tables to be imported mu...") . "</p>";
    echo "<p>" . $i18n->t("A0zktK|Tables to be imported th...") . "</p>";
    echo Form::formErrorsToHtml($formErrors);
    echo $formToFill->get_html(true); // enable file upload
    echo $formToFill->getHelpHtml();
} elseif ($todo == 2) { // step 2. Texts for output
    echo "<p>" . $i18n->t("Fg6pWu|The file upload was succ...") . "</p>";
    echo "<p>" . $i18n->t("FFjdFF|In the next step, the ta...") . "</p>";
    // no form errors possible at this step. just a button clicked.
    echo $importResult;
    echo $formToFill->get_html();
    echo $formToFill->getHelpHtml();
} elseif ($todo == 3) { // step 3. Texts for output
    echo $importResult;
    echo "<p>" . $i18n->t("KLYCCf|The file import was carr...") . "</p>";
}

// Help texts and page footer for output.
echo "</div>";
$runner->endScript();
