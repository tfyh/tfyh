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
use Data\Config;
use Data\DatabaseConnector;
use Util\Form;
use Util\I18n;

/**
 * Find a user record.
 */
// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$dbc = DatabaseConnector::getInstance();
$runner = Runner::getInstance();
$todo = ($runner->done == 0) ? 1 : $runner->done;
$formErrors = "";

$usersToShowHtml = "";
$id = (isset($_SESSION["get_parameters"][$runner->fsId]["id"])) ? intval($_SESSION["get_parameters"][$runner->fsId]["id"]) : 0;

$i18n = I18n::getInstance();
$formDefinition = [
    1 => "R;user_id;\n" . "r;first_name;\n" . "r;last_name;\n" .
        "r;search;\n" . "R;submit;" . $i18n->t("6SbTVr|Find"),
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
        $usersFound = [];
        if ($runner->done == 1) {
            $isAllTexts = false;
            $maxRows = 100;
            $matching = [];
            $condition = "";
            $searchStringLower = "";
            if ($id > 0)
                $matching = [$runner->users->userIdFieldName => $id
                ];
            elseif (strlen($validatedEntries["search"]) > 0) {
                // search all entries and all text fields
                // "SearchAll" is a technical term, needs no i18n
                $isAllTexts = true;
                $maxRows = 2000;
                $condition = "1";
                $searchStringLower = strtolower($validatedEntries["search"]);
            } else {
                foreach ($validatedEntries as $key => $value) {
                    if (isset($value) && (strlen($value) > 0)) {
                        $matching[$key] = "%" . $value . "%";
                        $condition .= "LIKE,";
                    }
                }
            }

            // only proceed if something was entered.
            if ($isAllTexts || (count($matching) > 0)) {
                // get all current users
                $matched = $dbc->findAllSorted($runner->users->userTableName, $matching,
                    $maxRows, $condition, $runner->users->userIdFieldName, true);
                // put all values to the array, with numeric auto-incrementing key.
                foreach ($matched as $user) {
                    $filteredUser = [];
                    $textMatched = false;
                    $key_matched = "";
                    foreach ($user as $key => $value) {
                        if (!is_numeric($key)) {
                            // join all text fields of filtered user
                            $filteredUser[$key] = $value;
                            $valueLower = strtolower($value);
                            // if full-text search, check field for search string.
                            if ($isAllTexts && (str_contains($valueLower, $searchStringLower)) &&
                                !(strcasecmp($key, $dbc->historyName()) == 0)) {
                                $textMatched = true;
                                $key_matched .= " in " . $key . ": '" . str_replace($searchStringLower,
                                        "<b>" . $searchStringLower . "</b>", $valueLower) . "'";
                            }
                        }
                    }
                    // add user to filtered list if it was no full-text search,
                    // then the filter was part of the SQL-Statement, or if the
                    // text was found.
                    if ($textMatched)
                        $filteredUser["key_matched"] = $key_matched;
                    if (!$isAllTexts || $textMatched)
                        $usersFound[] = $filteredUser;
                }
                $todo = $runner->done + 1;
            } else {
                $formErrors = $i18n->t("ffkuoc|For the search, at least...");
            }
        }

        // if users were selected, create list output.
        if ($todo == 2) {
            $i = 0;
            foreach ($usersFound as $userFound) {
                $user_name = $userFound[$runner->users->userFirstNameFieldName] . " " .
                    $userFound[$runner->users->userLastNameFieldName];
                if (intval($userFound[$runner->users->userIdFieldName]) > 0)
                    $formResult = $user_name . " (" . $userFound[$runner->users->userIdFieldName] . ")";
                else
                    $formResult = "[" . $user_name . "]";
                if (isset($userFound["key_matched"]))
                    $formResult .= " '<b>" . $validatedEntries["SearchAll"] . "</b>'" . $userFound["key_matched"] . ", ";
                $formResult .= $runner->users->getActionLinks($userFound[$runner->users->userIdFieldName],
                    $userFound["uid"]);
                $usersToShowHtml .= $formResult . "<br />";
                $i++;
            }
            if ($i === 0) {
                $userFoundHtml = "<b>" . $i18n->t("c02HGj|Hints:") . "</b><br>" .
                    $i18n->t("U24lUJ|No matching user found.");
            }
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

// page heading, identical for all workflow steps

// limit the form width
echo "<div style='max-width: 25em; padding-top: 1em'>";
echo "<h3>" . $i18n->t("Find a user") . "</h3>";
echo "<p>" . $i18n->t("c7f8JL|Here you can find a clou...");
echo "<br><b>" . $i18n->t("ruWtay|This page is the entry p...") . "</b> " . $i18n->t("6PGNEG|The display and editing ...");
echo "</p>\n";

echo Form::formErrorsToHtml($formErrors);
if ($todo < 2) { // step 1. No special texts for output
    echo $formToFill->getHtml();
} else {
    echo $usersToShowHtml;
}
echo "</div>";
echo "</div>";
$runner->endScript();

    
