<?php
/**
 * dilbo - digital logbook for Rowing and Canoeing
 * https://www.dilbo.org
 * Copyright:  2023-2025  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except
 * in compliance with the License. You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software distributed under the License
 * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 * or implied. See the License for the specific language governing permissions and limitations under
 * the License.
 */

/**
 * a form to request application support by the support team
 */
// ===== initialize
use tfyh\control\Logger;
use tfyh\control\Runner;
include_once "../_Control/Logger.php";
include_once "../_Control/Runner.php";

use tfyh\data\Config;
include_once "../_Data/Config.php";

use tfyh\util\Form;
use tfyh\util\I18n;
include_once "../_Util/Form.php";
include_once "../_Util/I18n.php";

$userRequestedFile = __FILE__;
include_once "../_Control/init.php";
$i18n = I18n::getInstance();
$config = Config::getInstance();
$runner = Runner::getInstance();
$todo = ($runner->done == 0) ? 1 : $runner->done;
$formErrors = "";
$postResult = "";

$i18n = I18n::getInstance();
$formDefinition = [
    1 => "R;*support_token,club_name;\n" . "R;*full_name;\n" . "r;*reply_to;\n" . "r;include_logs;\n" . "r;request;\n" .
        "R;submit;" . $i18n->t("FVQVIW|Send"),
    2 => ""
];

// === APPLICATION LOGIC ==============================================================

// ======== Start with form filled in last step: check of the entered values.
if ($runner->done > 0) {
    $formFilled = new Form(Config::getInstance()->getItem(".tables.persons"),
        $formDefinition[$runner->done]);
    $formFilled->validate();
    $formErrors = $formFilled->formErrors;
    $validatedEntries = $formFilled->getEntered();
    // application logic, step by step
    if (strlen($formErrors) == 0) { // do nothing if form errors occurred.
        // do nothing. This avoids any change if form errors occurred.
        if ($runner->done == 1) {
            if (isset($validatedEntries["include_logs"]) && (strlen($validatedEntries["include_logs"]) > 0)) {
                $logsZip = "../Log/" . Logger::zipLogs(["web", "config", "api"]);
                $validatedEntries["logs_zip"] = str_replace("=", "_",
                    str_replace("/", "-",
                        str_replace("+", "*", base64_encode(file_get_contents($logsZip)))));
            }
            unset($validatedEntries["include_logs"]);

            // Post the request
            $options = array(
                'http' => array('header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST', 'content' => http_build_query($validatedEntries)
                )
            );
            $context = stream_context_create($options);
            $supportUrl = $config->getItem(".framework.app.support_url")->valueStr();
            $postResult = file_get_contents($supportUrl, false, $context);
            if ($postResult === false)
                $postResult = $i18n->t("hGxTLD|The transfer of your req...");
            $todo = 2;
        }
    }
}

// ==== continue with the definition and eventually initialisation of form to fill for the next step
if (isset($formFilled) && ($todo == $runner->done)) {
    // redo the 'done' form, if the $to do == $done, i.e. the validation failed.
    $formToFill = $formFilled;
} else {
    // if it is the start or all is fine, use a form for the coming step.
    $formToFill = new Form(Config::getInstance()->invalidItem, $formDefinition[$todo]);
    if ($todo == 1)
        $formToFill->presetWithStrings([
            "support_token" => Config::getInstance()->getItem(".app.operations.support_token")->valueStr(),
            "club_name" => Config::getInstance()->getItem(".app.club.clubname")->valueStr()
        ]);
}

// === PAGE OUTPUT ===================================================================
echo $runner->pageStart();

// page heading, identical for all workflow steps

echo "<h3>" . $i18n->t("wzM12X|Send support request or ...") . "</h3>";
echo Form::formErrorsToHtml($formErrors);

// ======== start with the display of either the next form, or the error messages.
if ($todo == 1) {
    echo "<p>" . $i18n->t("JjAfRD|Please let me know your ...") . "</p>";
    echo "<p>" . $i18n->t("RZZubx|I am also happy to recei...") . "</p>";
    // step 1. Show form.
    echo $formToFill->getHtml();
} elseif ($todo == 2) {
    echo "<p>" . $i18n->t("02SBJ9|Thank you for your reque...") . "</p><p>";
    echo $postResult;
    echo "</p><p>" . $i18n->t("4hdqAF|The process is now compl...") . "</p>";
}
echo "</div>";
$runner->endScript();
