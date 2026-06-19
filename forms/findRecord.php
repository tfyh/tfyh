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
use tfyh\data\Config;
use tfyh\data\DatabaseConnector;
use tfyh\data\WordIndex;
use tfyh\util\Form;
use tfyh\util\I18n;

/**
 * The form to find an arbitrary record. Based on the Tfyh_form class, please read instructions there to
 * better understand this PHP-code part.
 */
// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$i18n = I18n::getInstance();
$config = Config::getInstance();
$runner = Runner::getInstance();
$todo = ($runner->done == 0) ? 1 : $runner->done;
$formErrors = "";
$findResultHtml = $i18n->t("xcuBnc|I°m afraid there is noth...");

$rebuild = isset($_GET["rebuild"]) ? intval($_GET["rebuild"]) : 0;
if ($rebuild == 1) {
    $word_index = new WordIndex();
    $word_index->rebuild();
}

$formDefinition = [
    1 => "R;search,;\n".
        "R;submit;" . $i18n->t("QcKAAP|Find"),
    2 => ""
];

// === APPLICATION LOGIC ==============================================================
// ======== Start with form filled in last step: check of the entered values.
if ($runner->done > 0) {
    $formFilled = new Form(Config::getInstance()->invalidItem,
        $formDefinition[$runner->done]);
    $formFilled->validate(); // (includes password rule check)
    $formErrors = $formFilled->formErrors;
    $validatedEntries = $formFilled->getEntered(false);
    // application logic, step by step
    if (strlen($formErrors) == 0) { // do nothing if form errors occurred.
        // do nothing. This avoids any change if form errors occured.
        $find = $validatedEntries["search"];
        if (strlen($find) < WordIndex::MIN_WORD_LENGTH)
            $formErrors = $i18n->t("f8WSn3|Please provide at least ...");
        else {
            $wordIndex = new WordIndex();
            $wordIndex->find($find, true, true);
            $findResultHtml = "<h4>" . $i18n->t("rSEEGA|results for °%1°", $find) . ":</h4>";
            $t = 0;
            $r = 0;
            foreach ($wordIndex->findResult as $tableName => $records) {
                $t ++;
                $recordItem = $config->getItem(".tables." . $tableName);
                $tableLabel = $recordItem->label();
                $findResultHtml .= "<h5>" . $tableLabel . "</h5><ol>";
                foreach ($records as $uid => $record) {
                    $findResultHtml .= "<li>" . $record["@short"] . "</br>";
                    $findResultHtml .= "<a target='_blank' href='../../tfyh/pages/viewRecord.php?uid=" . $uid . "&table=" .
                        $tableName . "'>";
                    $findResultHtml .= $record["@in_fields"] . "</a> - " . $record["@bold_where_is"] . "</li>";
                    $r ++;
                }
                $findResultHtml .= "</ol>";
            }
            $findResultHtml .= "<h5>" . $i18n->t("MMIW9a|%1 records in %2 tables ...", $r, $t) . "</h5>";
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
    $formToFill = new Form(Config::getInstance()->invalidItem, $formDefinition[$todo]);
}

// === PAGE OUTPUT ===================================================================
echo $runner->pageStart();

// page heading, identical for all workflow steps
echo "<h3><br><br>" . $i18n->t("XZ0WtK|Search the database") . "</h3>";

echo Form::formErrorsToHtml($formErrors);
if ($todo < 2) {
    echo $formToFill->getHtml();
} else {
    echo $findResultHtml;
}
if ($rebuild != 1)
    echo "<p>" . $i18n->t("") . "<a href='?rebuild=1'>" .
        $i18n->t("8HBvnC|Rebuild the index to inc...") . "</a></p>";

echo "</div>";
$runner->endScript();

    
