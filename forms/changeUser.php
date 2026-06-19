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
include_once "../_Control/Runner.php";
include_once "../_Control/LoggerSeverity.php";

use tfyh\data\Codec;
use tfyh\data\Config;
use tfyh\data\DatabaseConnector;
use tfyh\data\Record;
include_once "../_Data/Codec.php";
include_once "../_Data/Config.php";
include_once "../_Data/DatabaseConnector.php";
include_once "../_Data/Record.php";

use tfyh\util\Form;
use tfyh\util\I18n;
use tfyh\util\FormBuilder;
use tfyh\util\Language;
include_once "../_Util/Form.php";
include_once "../_Util/I18n.php";
include_once "../_Util/FormBuilder.php";
include_once "../_Util/Language.php";

/**
 * The form for user profile editing. Based on the Form class, please read instructions there to
 * better understand this PHP-code part.
 */

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../_Control/init.php";
$i18n = I18n::getInstance();
$dbc = DatabaseConnector::getInstance();
$config = Config::getInstance();
$runner = Runner::getInstance();
$done = $runner->done;
$todo = ($done == 0) ? 1 : $done;
$formErrors = "";
$formResult = "";
$recordItem = $config->getItem(".tables." . $runner->users->userTableName);
$record = new Record($recordItem);

$i18n = I18n::getInstance();
$formDefinition = [
    1 => "R;~user_id,;\n" . "r;first_name,last_name;\n" . "r;email,gender;\n" .
        "r;role,;\n" . "r;password,password_repeat;\n" . "r;password_delete;\n" .
        "R;submit;" . $i18n->t("hbyYEk|Store"),
    2 => ""
];

// === APPLICATION LOGIC ==============================================================

// ===== a dummy for a password which is not the right one. Must nevertheless be valid to
// pass all checks further down.
$keepPassword = "keuk3HVpxHASrcRn6Mpf";

// This page requires an id to be set for the user to update. If not set, or the id is 0, a new user will be
// created.
$isNewUser = false;
if (! isset($_SESSION["get_parameters"][$runner->fsId]["uid"]) || (strlen($_SESSION["get_parameters"][$runner->fsId]["uid"]) == 0))
    $runner->displayError($i18n->t("jA4eJf|Not allowed"), $i18n->t("NiYUV5|You have to provide the ..."),
            $userRequestedFile);

$now = microtime(true);
if (strcasecmp($_SESSION["get_parameters"][$runner->fsId]["uid"], "new") != 0) {
    // get the user record. technically there may be multiple, because of versioning.
    $matching = ["uid" => $_SESSION["get_parameters"][$runner->fsId]["uid"]
    ];
    $userToUpdate = DatabaseConnector::getInstance()->findAllSorted($runner->users->userTableName, $matching, 1,
            "=", "invalid_from", false);
    if ($userToUpdate === false)
        $runner->displayError($i18n->t("FHyizx|Not found"), 
                $i18n->t("BGJkSQ|The user record for ID °...", $_SESSION["get_parameters"][$runner->fsId]["uid"]),
                $userRequestedFile);
    else
        $userToUpdate = $userToUpdate[0];
} else {
    // insert a new user.
    $isNewUser = true;
    $emptyUserRow = $runner->users->getEmptyUserRow($keepPassword);
    $emptyUserInserted = false;
    while ($emptyUserInserted === false) {
        // get the highest user_id and increase by one for the new user
        $idToUpdate = $runner->users->getHighestUserId() + 1;
        $idIsUsed = $dbc->find($runner->users->userTableName, $runner->users->userIdFieldName,
            $idToUpdate);
        if ($idIsUsed === false) {
            // insert if this id is free.
            $emptyUserRow[$runner->users->userIdFieldName] = "$idToUpdate";
            $emptyUserInserted = $record->modify($emptyUserRow, 1, Language::CSV);
        }
    }
    // read the inserted user back to get all system fields.
    $userToUpdate = $dbc->find($runner->users->userTableName, "uid", $emptyUserRow["uid"]);
    $_SESSION["get_parameters"][$runner->fsId]["uid"] = $emptyUserRow["uid"];
}

$idToUpdate = $userToUpdate[$runner->users->userIdFieldName];
$userNameDisplay = $userToUpdate["first_name"] . " " . $userToUpdate["last_name"];

// ======== Start with form filled in last step: check of the entered values.
if ($runner->done > 0) {
    $formFilled = new Form(Config::getInstance()->getItem(".tables.persons"),
        $formDefinition[$runner->done]);
    $formFilled->validate(); // (includes password rule check)
    $formErrors = $formFilled->formErrors;
    $validatedEntries = $formFilled->getEntered(false);
    if (isset($validatedEntries["password"]) && (strlen($validatedEntries["password"]) == 0))
        $formFilled->presetWithStrings([ "password" => $keepPassword, "password_repeat" => $keepPassword ]);
    // application logic, step by step
    if (strlen($formErrors) == 0) { // do nothing if form errors occurred.
        if ($done == 1) {
            // Password was changed, check identity of password and repetition
            if (isset($validatedEntries["password"]) && (strcmp($validatedEntries['password'], $keepPassword) != 0)) {
                // -------------------------------
                // password and repetition must be identical.
                if ($validatedEntries['password'] != $validatedEntries['password_repeat']) {
                    $formErrors .= $i18n->t("xVSLCZ|The passwords must match...") . "<br>";
                    $formFilled->presetWithStrings([ "password" => $keepPassword, "password_repeat" => $keepPassword ]);
                }
            }

            $userUpdated = [];
            $userChanged = (count($validatedEntries) > 0);
            // now copy changed values, except password (will be done later)
            foreach ($validatedEntries as $key => $value)
                $userUpdated[$key] = $value;

            // set the password hash value and delete all password entries.
            if (isset($validatedEntries['password_delete']) && (strcasecmp($validatedEntries['password_delete'], "on") == 0)) {
                $userUpdated["password_hash"] = "-";
            } elseif (isset($validatedEntries['password']) && (strcmp($keepPassword, $validatedEntries["password"]) != 0)) {
                if ($isNewUser)
                    $userUpdated["password_hash"] = password_hash($validatedEntries['password'],
                        PASSWORD_DEFAULT);
                else
                    $formResult = $i18n->t("yR7zed|The password can only be...") . "<br>";
            }
            unset($userUpdated["password"]);
            unset($userUpdated["password_repeat"]);
            unset($userUpdated["password_delete"]);

            // continue, if no errors were detected
            $changes = 0;
            if (strlen($formErrors) == 0) {
                // log the changes for user display
                $todo = $done + 1;
                foreach ($userUpdated as $key => $value) {
                    $changes++;
                    if (strcmp($key, "password_hash") == 0)
                        $formResult .= $i18n->t("b25fYT|The password has been ch...") . "<br>";
                    else {
                        $label = $config->getItem(".tables.persons." . $key)->label();
                        $formResult .= $label . ": '" . Codec::htmlSpecialChars($userToUpdate[$key]) . "' => '" .
                            Codec::htmlSpecialChars($value) . "'<br>";
                    }
                }
            }
            // add the uid to ensure the change can be written. (It will never be part of the entered data
            // because it never changes.)
            $userUpdated["uid"] = $userToUpdate["uid"];

            // update the record in the database.
            $userRecordItem = Config::getInstance()->getItem(".tables." . $runner->users->userTableName);
            $record = new Record($userRecordItem);
            if ($userChanged && ($changes > 0) && !$formErrors) {
                // update the user. Only the fields in $userUpdated are passed to the database, make sure the uid is part of it.
                $changeResult = $record->modify($userUpdated, 2, $config->language());
                if (str_starts_with($changeResult, "!")) {
                    $formErrors .= "<br />" . $i18n->t("5pH8pE|Database update command ...") . " " . $changeResult;
                } else {
                    $runner->logger->log(LoggerSeverity::INFO, "changeUser",
                        "User '" . intval($userUpdated[$runner->users->userIdFieldName]) .
                        "' change by administrator.");
                }
            } elseif (!$formErrors) {
                $formResult .= $i18n->t("JjCWn9|No modified data has bee...") . "</p>";
            }
            $todo = $done + 1;
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
    if ($todo == 1) {
        // preset values on first step.
        $userToUpdate["password"] = $keepPassword;
        $userToUpdate["password_repeat"] = $keepPassword;
        $formToFill->presetWithStrings($userToUpdate);
    }
}

// === PAGE OUTPUT ===================================================================
echo $runner->pageStart();

// page heading, identical for all workflow steps
if ($isNewUser) {
    echo "<h3>" . $i18n->t("jwq0iS|Create a new user") . $i18n->t("a6h3Ou|with the ID %1", $idToUpdate) . "</h3>";
    echo "<p>" . $i18n->t("GNGdV1|This form is to create a...") . "</p>";
} else {
    echo "<h3>" . $i18n->t("Sni5v9|Change the profile of %1", $userNameDisplay) . "</h3>";
    echo "<p>" . $i18n->t("Agxr6E|This form is to change a...") . "</p>";
}
echo Form::formErrorsToHtml($formErrors);

if ($todo == 1) { // step 1. No special texts for output
    echo $formToFill->getHtml();
} else {
    echo "<p><b>" . $i18n->t("gvvK8s|The data change is %1per...", (($formErrors) ? $i18n->t("rT8wtF|not") : "")) . "</b> ";
    echo (($formErrors) ? "" : $i18n->t("9mww2x|The following changes ha...") . "<br />" . $formResult);
    echo "<p><a href='../_pages/viewRecord.php?table=persons&uid=" . $userToUpdate["uid"] . "'>" .
             $i18n->t("CHVtwP|Display changed profile ...") . "</a>";
    echo "<br /><a href='../_forms/changeUser.php?uid=" . $userToUpdate["uid"] . "'>" .
             $i18n->t("gcFhev|Continue to edit user.") . "</a></p>";
}
echo "</div>";
$runner->endScript();
