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

use tfyh\control\Runner;
use tfyh\data\DatabaseConnector;
use tfyh\data\Formatter;
use tfyh\data\ParserName;
use tfyh\util\Form;
use tfyh\util\FormBuilder;
use tfyh\util\I18n;
use tfyh\util\ListHandler;

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$i18n = I18n::getInstance();
$dbc = DatabaseConnector::getInstance();
$runner = Runner::getInstance();
$todo = ($runner->done == 0) ? 1 : $runner->done;
$formErrors = "";
$formInfo = "<p>" . $i18n->t("o6WA71|In the first step, pleas...") . "</p>";
$mails_formatted = "";

// there are two ways to select a distribution list, either via the get-value "distributionList" or via the
// POST-value "distributionList".
$distributionList = (isset($_GET["distributionList"])) ? intval($_GET["distributionList"]) : "";

// === APPLICATION LOGIC ==============================================================
// ======== Start with form filled in last step: check of the entered values.
if ($runner->done > 0) {
    $formFilled = new FormBuilder();
    $formFilled->read_entered();
    $formErrors = $formFilled->checkValidity();
    // application logic, step by step
    if (strlen($formErrors) == 0) { // do nothing if form errors occurred.
        if ($runner->done == 1) {
            // get the list set for selection
            $list = new ListHandler("mailRead", $distributionList);
            if ($list->count() == 0)
                $runner->displayError($i18n->t("hfr2Zo|Invalid mail distributio..."),
                    $i18n->t("xS4f0M|The selected mailing lis..."), $userRequestedFile);
            // get mailto list
            if (!$runner->users->isAllowedItem($list->getPermission()))
                $runner->displayError($i18n->t("2D9pBg|Invalid mail distributio..."),
                    $i18n->t("QvmvUh|Mails to the selected ma..."), $userRequestedFile);
            $count_of_mails = 25;
            $formInfo = "<p>" . $i18n->t("NAOKEm|Here are the last %1 mai...", $count_of_mails, $distributionList) .
                "</p>";
            $mails_list = $dbc->findAll("Mails", ["distributionList" => $distributionList], 1000);
            $mails_to_skip = ($mails_list === false) ? $count_of_mails : count($mails_list) - $count_of_mails;
            $todo = 2;
            $i = 1;
            foreach ($mails_list as $mail_listed) {
                if ($i > $mails_to_skip) {
                    $mail_from_user = $dbc->find($runner->users->userTableName,
                        $runner->users->userIdFieldName, $mail_listed[$runner->users->userIdFieldName]);
                    $mailfrom = $mail_from_user[$runner->users->userFirstNameFieldName] . " " .
                        $mail_from_user[$runner->users->userLastNameFieldName];
                    $mailto = $distributionList;
                    $subject = $mail_listed["subject"];
                    $body = str_replace("\n", "<br>", $mail_listed["message"]);
                    $mails_formatted = "<p>#" . $i . " <b>" . $i18n->t("1393xk|Sent:") . "</b> " .
                        Formatter::format($mail_listed["sent"], ParserName::DATETIME) . "<br /><b>" .
                        $i18n->t("yhYGfZ|From:") . "</b> " . $mailfrom . "<br /><b>" .
                        $i18n->t("6KXRE6|To:") . "</b> " . $mailto . "<br /><b>" .
                        $i18n->t("XMHPag|Subject:") . "</b> " . $subject . "</p><br />" . $body . "<br />" .
                        $i18n->t("hv0TyN|Attachment:") . " " . $mail_listed["Attachment"] . " " .
                        $i18n->t("RyaYQ8|(Can be made available o...") . "<hr>\n" . $mails_formatted;
                }
                $i++;
            }
        }
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

echo "<h3>" . $i18n->t("99XIGm|Read previousl sent mail...") . "</h3>";
echo "<p>" . $i18n->t("sN71rx|Please select the distri...") . "</p>";
echo Form::formErrorsToHtml($formErrors);
echo $formInfo;
if ($todo == 1)
    echo $formToFill->get_html();
elseif ($todo == 2)
    echo $mails_formatted; // enable file upload
echo "<!-- END OF form -->\n</div>";
$runner->endScript();
