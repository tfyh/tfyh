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
use tfyh\util\Form;
use tfyh\util\FormBuilder;
use tfyh\util\I18n;
use tfyh\util\MailHandler;
use tfyh\util\TokenHandler;

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$i18n = I18n::getInstance();
$dbc = DatabaseConnector::getInstance();
$runner = Runner::getInstance();
$todo = ($runner->done == 0) ? 1 : $runner->done;
$formErrors = "";
$formResult = "";

// === APPLICATION LOGIC ==============================================================
if ($runner->done > 0) {
    $formFilled = new FormBuilder();
    $formFilled->read_entered();
    $formErrors = $formFilled->checkValidity();
    $enteredData = $formFilled->getEntered();
    // application logic, step by step
    if (strlen($formErrors) == 0) { // do nothing if form errors occurred.
        if ($runner->done == 1) {
            // user identification
            // ----------------------------------------------------------------------
            // Check the account information (mail or userId) identify user
            if (filter_var($enteredData["Account"], FILTER_VALIDATE_EMAIL) !== false)
                $userToUpdate = $dbc->find($runner->users->userTableName, $runner->users->userMailFieldName,
                    $enteredData["Account"]);
            else {
                $userToUpdate = false;
                $formErrors .= $i18n->t("sOovvb|Deleting the permanent p...") . " ";
            }

            // check entered password or send token
            // ------------------------------------
            if (!$userToUpdate) {
                // user was not matched in database
                $formErrors .= $i18n->t("ZQSvHw|The user could not be id...");
            } else {
                // user was matched in the database. Send token.
                // user has no permanent password, send a token.
                $userToUpdateId = $userToUpdate[$runner->users->userIdFieldName];
                $mailUser = MailHandler::stripAddressPrefix($enteredData["Account"]);
                $tokenHandler = new TokenHandler("../../var/Run/OmeTimePasswords.txt");
                $token = $tokenHandler->getNewToken($userToUpdateId);
                $mailHandler = new MailHandler(Config::getInstance()->getItem(".app.mailer"));
                // Compile Mail to user.
                $subject = $i18n->t("SUuG3E|One-time password for de...", $token);
                $body = "<p>" . $i18n->t("dF0DWJ|Hello %1 %2,",
                        $userToUpdate[$runner->users->userFirstNameFieldName],
                        $userToUpdate[$runner->users->userLastNameFieldName]) .
                    "</p>";
                if ($token == "---")
                    $body .= "<p>" .
                        $i18n->t("3dNfLl|No more one time passwor...") .
                        "<p>";
                else {
                    $body .= "<p>" . $i18n->t("Nz8G7x|The one-time password °%...", $token,
                            strval($tokenHandler->tokenValidityPeriod / 60));
                    $body .= " " . $i18n->t("43SSV8|It does not matter wheth...") . "<p>";
                    $body .= "<p>" . $i18n->t("JmUHGQ|Afterwards, logging in w...") . "<p>";
                }
                $body .= $mailHandler->mailSubscript . $mailHandler->mailFooter;
                $send_success = $mailHandler->send_mail($mailHandler->systemMailSender,
                    $mailHandler->systemMailSender, $mailUser, "", "", $subject, $body);
                if ($send_success) {
                    $formResult .= "<b>" . $i18n->t("PeJo45|The one-time password wa...", $mailUser) . "</b>";
                    $runner->logger->log(LoggerSeverity::INFO, "resetPassword.php",
                        "One-time password for password reset sent to user " . $userToUpdateId);
                    $_SESSION["Registering_user"] = $userToUpdate;
                    $todo = $runner->done + 1;
                } else
                    $formErrors .= $i18n->t("ULBtgs|The one-time password co...") . " <br>";
            }
        } elseif ($runner->done === 2) {
            // user has no permanent password, verify token.
            $tokenHandler = new TokenHandler("../../var/Run/OmeTimePasswords.txt");
            $userId = $tokenHandler->getUserId($enteredData["Token"]);
            if ($userId == -1)
                $formErrors .= $i18n->t("K7CeYe|The one-time password is...");
            elseif ($userId == -2)
                $formErrors .= $i18n->t("MMoUVY|Too many sessions open f...");
            else {
                // password changes will change the last modified time stamp because they have
                // no impact on the user's data
                $userRecord = [];
                $userRecord[$runner->users->userIdFieldName] = $userId;
                $userRecord["password_hash"] = "-";
                $update_result = $dbc->update($runner->users->userTableName,
                    $runner->users->userIdFieldName, $userRecord);
                if (strlen($update_result) > 0)
                    $formErrors .= $i18n->t("ceZU15|The deletion of the pass...");
                else
                    $todo = $runner->done + 1;
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

echo "<h3>" . $i18n->t("6Eagok|Delete password") . "</h3>";
echo "<p>" .
         $i18n->t(
                "eaMVsh|Delete the permanent pas...") .
         "</p>";
echo $formResult;
echo Form::formErrorsToHtml($formErrors);
echo $formToFill->get_html();

// ======== start with the display of either the next form or the error messages.
// no special output for steps 1 and 2.
if ($todo == 3) { // step 3.
    echo $i18n->t("1SeWJN|After deleting the passw...");
}

echo $formToFill->getHelpHtml();
echo "</div>";
$runner->endScript();

