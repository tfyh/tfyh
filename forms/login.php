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
use tfyh\control\Monitor;
use tfyh\control\Runner;
use tfyh\control\Sessions;
include_once "../_Control/LoggerSeverity.php";
include_once "../_Control/Runner.php";
include_once "../_Control/Sessions.php";

use tfyh\data\Codec;
use tfyh\data\Config;
use tfyh\data\DatabaseConnector;
include_once "../_Data/Codec.php";
include_once "../_Data/Config.php";
include_once "../_Data/DatabaseConnector.php";

use tfyh\util\Form;
use tfyh\util\I18n;
include_once "../_Util/Form.php";
include_once "../_Util/I18n.php";

/**
 * The login form for all activities on this application except registration.
 */
// ===== initialize
$userRequestedFile = __FILE__;
include_once "../_Control/init.php";
$i18n = I18n::getInstance();
$config = Config::getInstance();
$dbc = DatabaseConnector::getInstance();
$runner = Runner::getInstance();
$fsId = $runner->fsId;
$todo = ($runner->done == 0) ? 1 : $runner->done;
$formErrors = "";
$formResult = "";
$monitor = Monitor::getInstance();

// ===== Identify the different login options
// a "goto" parameter indicates the landing page after login. The default is "../pages/webApp.php"
$deeplink = "";
if (isset($_SESSION["get_parameters"][$fsId]["goto"]) && (strlen($_SESSION["get_parameters"][$fsId]["goto"]) > 0))
    $deeplink = $_SESSION["get_parameters"][$fsId]["goto"];
// use a different role for test purposes
$useAsRole = "";
if (isset($_SESSION["get_parameters"][$fsId]["as"]) && (strlen($_SESSION["get_parameters"][$fsId]["as"]) > 0))
    $useAsRole = $_SESSION["get_parameters"][$fsId]["as"];
// the page may be called by redirection after an execution error. This error shall be displayed
$onError = false;
if (isset($_SESSION["get_parameters"][$fsId]["onerror"]) && (intval($_SESSION["get_parameters"][$fsId]["onerror"]) == 1))
    $onError = true;
// a login token conveys as GET parameter user authentication information as well as the login credentials.
$loginToken = "";
if (isset($_SESSION["get_parameters"][$fsId]["token"])  && (strlen($_SESSION["get_parameters"][$fsId]["token"]) > 0))
    $loginToken = $_SESSION["get_parameters"][$fsId]["token"];

$i18n = I18n::getInstance();
$formDefinition = [
    1 => "R;*account;\n" . "r;*password;\n" . "R;submit;",
    2 => "R;_no_input;" . Codec::encodeCsvEntry($i18n->t("CLnsaZ|Please enter the one-tim..."), ",") . "\n" .
        "r;*one_time_password;\n" . "R;submit;" . $i18n->t("NRGTZC|Save"),
    3 => ""
];

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messages

// ======== if a login token is provided, try to log in via that token.
if (strlen($loginToken) > 0) {
    // a successful token login will always redirect to a start page. This only returns on errors.
    $formErrors .= $runner->loginByToken($_SESSION["get_parameters"][$fsId]["token"]);
    if (strlen($formErrors) == 0)
        $todo = 3;
}

// ======== else start with form filled in last step: check of the entered values.
else if ($runner->done > 0) {
    $formFilled = new Form(Config::getInstance()->getItem(".tables.persons"),
        $formDefinition[$runner->done]);
    $formFilled->validate();
    $formErrors = $formFilled->formErrors;
    $validatedEntries = $formFilled->getEntered();
    // application logic, step by step
    if (strlen($formErrors) == 0) { // do nothing if form errors occurred.
        if ($runner->done == 1) {
            // Step 1, user login with credentials.
            if (strlen($validatedEntries["password"]) < 5) {
                $formErrors .= $runner->provideOneTimePassword($validatedEntries['account']);
                if (strlen($formErrors) == 0)
                    $todo = 2;
            } else {
                $formErrors .= $runner->loginByCredentials($validatedEntries["account"], $validatedEntries["password"]);
                if (strlen($formErrors) == 0)
                    $todo = 3;
            }
            if ($_SESSION["login_failures"] > 0) {
                $maxErrorsPerHour = $config->getItem(".framework.sessions.max_errors_per_hour")->value();
                $formErrors .= $i18n->t(
                    "cGim1i|Login error. Already %1 ...",
                    $_SESSION["login_failures"]) . "</p>";
                $runner->logger->log(LoggerSeverity::WARNING, "login.php",
                    "Wrong password at login.");
                // try and eroor will become slower and slower.
                sleep(2 * $_SESSION["login_failures"]);
            }
        } elseif ($runner->done === 2) {
            // step 2: user has got a token mall, verify token.
            $formErrors .= $runner->loginByOneTimePassword($validatedEntries["one_time_password"]);
            if (strlen($formErrors) == 0)
                $todo = 3;
        }

        if ($todo === 3) {
            // step 3: user is verified and the session was started.Transfer user now to session
            if (strlen($useAsRole) > 0) {
                if (!$runner->menu->isAllowedRoleChange(Sessions::getInstance()->userRole(), $useAsRole))
                    $runner->displayError($i18n->t("z7eGHS|Role not allowed."),
                        $i18n->t("xf8bTS|The user may not use the...", $useAsRole), $userRequestedFile);
                else {
                    $_SESSION["User_test_role"] = $useAsRole; // remember the role change
                    $runner->sessions->modifyUserRole($useAsRole);
                }
            }
            // now redirect to the deeplink or the user's home page.
            if (strlen($deeplink) > 0)
                header("Location: ../" . str_replace("%2F", "/", $deeplink));
            elseif (strlen($runner->tokenTarget) > 0)
                header("Location: ../" . $runner->tokenTarget);
            else
                header("Location: ../pages/home.php");
        }
    }
}

// ==== continue with the definition and eventually initialisation of form to fill for the next step
if (isset($formFilled) && ($todo == $runner->done)) {
    // redo the 'done' form, if the $to do == $done, i.e. the validation failed.
    $formToFill = $formFilled;
} else {
    // if it is the start or all is fine, use a form for the coming step.
    $formToFill = new Form(Config::getInstance()->getItem(".tables.persons"),
        $formDefinition[$todo]);
}

// === PAGE OUTPUT ===================================================================
echo $runner->pageStart();

// limit the form width
echo "<div style='max-width: 25em; padding-top: 3em'>";
// page heading, identical for all workflow steps
echo "<h3>" . $i18n->t("XPLjLc|Login for registered use...") . "</h3>";
// redirection to the login page due to an error. Show the reason.
if ($onError && ($runner->done == 0))
    echo Form::formErrorsToHtml(explode(";", file_get_contents("../Run/lastError.txt"))[2]);
else
    echo Form::formErrorsToHtml($formErrors);
echo $formResult;
echo $formToFill->getHtml();

// ======== start with the display of either the next form, or the error messages.
if ($todo == 1) { // step 1.
    echo "<p><a href='resetPassword.php'>" .
             $i18n->t("SjwGi5|Password forgotten?") . "</a></p>";
}
// step 2, step 3: no special texts for output
// end of form box
echo "</div>";
// Help texts and page footer
echo "</div>";
$runner->endScript();

