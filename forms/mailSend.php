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

use tfyh\control\LoggerSeverity;
use tfyh\control\Runner;
include_once "../_Control/LoggerSeverity.php";
include_once "../_Control/Runner.php";

use tfyh\data\Config;
use tfyh\data\DatabaseConnector;
include_once "../_Data/Config.php";
include_once "../_Data/DatabaseConnector.php";

use tfyh\util\Form;
use tfyh\util\FormBuilder;
use tfyh\util\I18n;
use tfyh\util\ListHandler;
use tfyh\util\MailHandler;
use tfyh\util\TokenHandler;
include_once "../_Util/Form.php";
include_once "../_Util/FormBuilder.php";
include_once "../_Util/I18n.php";
include_once "../_Util/ListHandler.php";
include_once "../_Util/MailHandler.php";
include_once "../_Util/TokenHandler.php";

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../_Control/init.php";
$i18n = I18n::getInstance();
$config = Config::getInstance();
$dbc = DatabaseConnector::getInstance();
$runner = Runner::getInstance();
$todo = ($runner->done == 0) ? 1 : $runner->done;
$formErrors = "";
$formInfo = "";

$isTempSave = isset($_POST["save"]); // this is just refreshing the session
                                     // if validation fails, the same form will be displayed anew
                                     // with error messages
$listParameter = [];
$listIndication = "";
if (isset($_SESSION["get_parameters"][$runner->fsId]["listparameter"])) {
    $listParameter["{listparameter}"] = $_SESSION["get_parameters"][$runner->fsId]["listparameter"];
    $listIndication = $i18n->t("XPbBHm|Used Parameter of list:") . " {listparameter} = " .
             $listParameter["{listparameter}"] . "\n.";
}

// === APPLICATION LOGIC ==============================================================
if ($runner->done > 0) {
    $formFilled = new FormBuilder();
    $formFilled->read_entered();
    $formErrors = $formFilled->checkValidity();
    $enteredData = $formFilled->getEntered();
    if ($isTempSave) {
        $_SESSION["To"] = $enteredData["To"];
        $_SESSION["subject"] = $enteredData["Subject"];
        $_SESSION["message"] = $enteredData["Message"];
        $_SESSION["messageHtmlEncoded"] = htmlentities(mb_convert_encoding($enteredData["Message"],
            'ISO-8859-1', 'UTF-8'));
    }

    // application logic, step by step
    if (strlen($formErrors) === 0) {
        // do nothing. This only prevents any logic from applying if form errors occurred.
        if (($runner->done == 1) && !$isTempSave) {
            // get mailto list
            $list = new ListHandler("mailSend", $enteredData["distributionList"], $listParameter);
            $records = $list->getRows("csv");
            $mailtoAddresses = [];
            foreach ($records as $record)
                $mailtoAddresses[] = $record[$runner->users->userMailFieldName];
            $_SESSION["distributionList"] = $enteredData["distributionList"];
            $_SESSION["mailtoAddresses"] = $mailtoAddresses;
            $_SESSION["subject"] = $enteredData["subject"];
            $_SESSION["message"] = $enteredData["message"];
            $_SESSION["messageHtmlEncoded"] = htmlentities($enteredData["Message"]);
            if (strlen($_SESSION["messageHtmlEncoded"]) == 0)
                // still hit an invalid character, use plain
                $_SESSION["messageHtmlEncoded"] = $_SESSION["message"];
            // copy uploaded attachments and remember their location
            if (file_exists($_FILES['userfile1']["tmp_name"])) {
                $_SESSION["attachment1"] = date("YmdHi", time()) . "_" . $_FILES['userfile1']["name"];
                copy($_FILES['userfile1']["tmp_name"], "../Attachments/" . $_SESSION["attachment1"]);
            } else
                $_SESSION["attachment1"] = "";
            if (file_exists($_FILES['userfile2']["tmp_name"])) {
                $_SESSION["attachment2"] = date("YmdHi", time()) . "_" . $_FILES['userfile2']["name"];
                copy($_FILES['userfile2']["tmp_name"], "../Attachments/" . $_SESSION["attachment2"]);
            } else
                $_SESSION["attachment2"] = "";
            $formInfo = "<p><b>" . $i18n->t("ZmVUvv|Recipient:") . " </b>" . $enteredData["distributionList"] . " " .
                $i18n->t("gEQmZw|(Number: %1)", count($_SESSION["mailtoAddresses"])) . "</p><p><b>" .
                $i18n->t("2yWFRU|Subject:") . "</b> " . $enteredData["subject"] . "</p><p><b>" .
                $i18n->t("OMRZJ7|Message:") . "</b><br />" . str_replace("\n", "<br />",
                    $_SESSION["messageHtmlEncoded"]) . "</p><p><b>" .
                $i18n->t("mlOh7N|Attachment 1:") . "</b><br />" . $_SESSION["attachment1"] . "<br /><b>" .
                $i18n->t("8dh5sK|Attachment 2:") . "</b><br />" . $_SESSION["attachment2"] . "</p><hr /><br />";
            $todo = 2;
        } elseif (($runner->done == 2) || $isTempSave) {

            // check for test mode. If this is a test, replace mailto-list by user mail
            $isTest = ($enteredData["testSend"]);
            if ($isTest) {
                $_SESSION["mailtoAddresses"] = [];
                $_SESSION["mailtoAddresses"][] = $runner->sessions->userMail();
            }

            // check for continued edit mode after the test. If this is a continuation of editing,
            // delete the mailto-list
            $isContinueEdit = (isset($_GET["edit"]) && (intval($_GET["edit"]) == 1));
            if ($isContinueEdit || $isTempSave)
                $_SESSION["mailtoAddresses"] = [];

            // create mails to users. Prepare.
            $mailHandler = new MailHandler($config->getItem(".app.mailer"));
            $successes = 0;
            $i = 0;
            $userFullName = $runner->sessions->userFullName();
            $mailFrom = $userFullName . " " . $mailHandler->mailSubjectAcronym . " <" .
                $mailHandler->systemMailSender . ">";
            $mailReplyTo = " " . $runner->sessions->userMail();

            // create mails one by one. Note: for ($isContinueEdit || $isTempSave) the
            // $_SESSION[ "mailtoAddresses"] is empty, for $isTest it contains the user himself only.
            $messageTemplate = str_replace("\n", "<br />", $_SESSION["messageHtmlEncoded"]);
            foreach ($_SESSION["mailtoAddresses"] as $userMailto) {
                $message = $messageTemplate;
                if (str_contains($message, "{#salutation#}")) {
                    $salutation = (isset($userMailto["gender"])) ?
                        ((strcasecmp("m", substr($userMailto["gender"], 0, 1)) === 0) ?
                            "<p>" . $i18n->t("ihdtPy|Dear 1") . " " :
                            "<p>" . $i18n->t("eEUD9N|Dear 2") . " ") :
                        "<p>" . $i18n->t("BuXeTn|Dear 3") . " ";
                    $salutation .= $userMailto[$runner->users->userFirstNameFieldName] . " " .
                        $userMailto[$runner->users->userLastNameFieldName];
                    $message = str_replace("{#salutation#}", $salutation, $message);
                }
                if (str_contains($message, "{#LoginToken+")) {
                    $messageParts = explode("{#LoginToken+", $message);
                    $tokenParams = explode("#}", $messageParts[1])[0];
                    $messageEnd = explode("#}", $messageParts[1])[1];
                    $plusDays = intval(explode("+", $tokenParams)[0]);
                    $deepLink = (count(explode("+", $tokenParams)) > 1) ? explode("+", $tokenParams)[1] : "../pages/webApp.php";
                    $loginToken =  TokenHandler::createLoginToken($userMailto["EMail"], $plusDays, $deepLink);
                    // add a line feed to ensure that the link itself will not be broken by line feed
                    // insertion (998 characters limit rule).
                    $message = $messageParts[0] . "\n<a href='" . $runner->appRoot .
                        "/_forms/login.php?token=" . urlencode($loginToken) . "'>" . $i18n->t(
                            "ZNvHsT|direct access") . "</a>" . $messageEnd;
                }
                foreach ($userMailto as $key => $value) {
                    if (str_contains($message, "{#" . $key . "#}"))
                        $message = str_replace("{#" . $key . "#}", $value, $message);
                }
                $message .= $mailHandler->mailFooter;
                $thisMailTo = MailHandler::stripAddressPrefix($userMailto["EMail"]);
                $attachment1 = ($_SESSION["attachment1"]) ? "../Attachments/" . $_SESSION["attachment1"] : "";
                $attachment2 = ($_SESSION["attachment2"]) ? "../Attachments/" . $_SESSION["attachment2"] : "";
                $mailWasSent = $mailHandler->send_mail($mailFrom, $mailReplyTo, $thisMailTo, "", "",
                    $mailHandler->mailSubjectAcronym . $_SESSION["subject"], $message, $attachment1,
                    $attachment2);
                if (!$mailWasSent)
                    $formInfo .= $i18n->t("zmsFb6|Sending failed for: °%1°...", $thisMailTo) . "<br />";
                else
                    $successes++;
            }
            $runner->logger->log(LoggerSeverity::INFO, "mailSend.php",
                $successes . " mails were sent using the distribution list: " .
                $_SESSION["distributionList"]);

            // create receipt to sender and remove attachment
            if (!$isContinueEdit && !$isTempSave) {
                $mailDbInsertResult = $i18n->t("M4AobL|No storage, test mode.");
                if (!$isTest) {
                    // move attachment into the sent-directory.
                    // Attachments therefore get a preceding reverse timestamp in the name.
                    rename("../Attachments/" . $_SESSION["attachment1"],
                        "../Attachments/sent/" . date("YmdHis") . "_" . $_SESSION["attachment1"]);
                    rename("../Attachments/" . $_SESSION["attachment2"],
                        "../Attachments/sent/" . date("YmdHis") . "_" . $_SESSION["attachment2"]);
                    // store mail to database for logging purposes
                    $record[$runner->users->userIdFieldName] = $runner->sessions->userId();
                    $record["sent"] = date("Y-m-d H:i:s");
                    $record["distributionList"] = $_SESSION["distributionList"];
                    $record["count"] = $successes;
                    $record["subject"] = $_SESSION["subject"];
                    $record["message"] = $_SESSION["message"];
                    $record["attachment1"] = $_SESSION["attachment1"];
                    $record["attachment2"] = $_SESSION["attachment2"];
                    $mailDbInsertResult = $dbc->insertInto("mails", $record);

                    // trigger showing of result without the sending form.
                    $todo = 3;
                }
                $formInfo .= $i18n->t("RxkkKI|The message was sent to ...", $successes);
                $_SESSION["result"] = $formInfo;
                $mailWasSent = $mailHandler->send_mail($mailFrom, $mailReplyTo,
                    $runner->sessions->userMail(), "", "",
                    $mailHandler->mailSubjectAcronym . $_SESSION["subject"], $formInfo . $listIndication);
            } else { // When testing show form again to be able to do adjustments.
                if ($isTest || $isContinueEdit || $isTempSave) {
                    if ($isTest)
                        $formInfo .= $i18n->t("1yDSbE|After the test mailing t...");
                    elseif ($isContinueEdit)
                        $formInfo .= $i18n->t("YpUO6w|Change checked message.");
                    else
                        $formInfo .= $i18n->t("5dqGwa|The session was updated.");
                    $formInfo .= $i18n->t("N2DEyA|NOTE: Attachments have b...");
                    // prefill form with previous values.
                    $formFilled = new FormBuilder();
                    $preset["distributionList"] = $_SESSION["distributionList"];
                    $preset["subject"] = $_SESSION["subject"];
                    $preset["message"] = $_SESSION["message"];
                    $formFilled->presetValues($preset);
                    // remove attachment, as test sending is not stored permanently.
                    unlink("../Attachments/" . $_SESSION["attachment1"]);
                    unlink("../Attachments/" . $_SESSION["attachment2"]);
                    // trigger new entry.
                    $todo = 1;
                } elseif ($successes == 0) {
                    $formInfo .= $i18n->t("s0IJfr|Because this mail could ...") . "<br />";
                    $todo = 3;
                }
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

echo "<h3>" . $i18n->t("W5VoZs|Send mails to a distribu...") . "</h3>";
echo "<p>" .
         $i18n->t(
                "OSpHxl|The mails are sent indiv...") .
         "</p>";
echo "<p>" . $i18n->t("rHyH4e|ATTENTION: After 10 minu...") . "</p>";
echo Form::formErrorsToHtml($formErrors);
echo $formInfo;
if ($todo < 3)
    echo $formToFill->get_html(true); // enable file upload
if ($todo == 2)
    echo "<p><a href='?f_seq=" . $runner->fsId . "2&edit=1'>" . $i18n->t("c1Kttz|Change the message.") . "</a></p>";
echo "<!-- END OF form -->\n</div>";
$runner->endScript();

