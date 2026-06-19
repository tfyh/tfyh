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

use tfyh\control\Runner;
include_once "../_Control/Runner.php";

use tfyh\data\Config;
use tfyh\data\DatabaseConnector;
use tfyh\data\PropertyName;
use tfyh\data\WordIndex;
include_once "../_Data/Config.php";
include_once "../_Data/DatabaseConnector.php";
include_once "../_Data/PropertyName.php";
include_once "../_Data/WordIndex.php";

use tfyh\util\Form;
use tfyh\util\I18n;
include_once "../_Util/Form.php";
include_once "../_Util/I18n.php";

/**
 * The form to find an arbitrary record. Based on the Tfyh_form class, please read instructions there to
 * better understand this PHP-code part.
 */
// ===== initialize
$userRequestedFile = __FILE__;
include_once "../_Control/init.php";
$i18n = I18n::getInstance();
$config = Config::getInstance();
$dbc = DatabaseConnector::getInstance();
$runner = Runner::getInstance();
$todo = ($runner->done == 0) ? 1 : $runner->done;
$formErrors = "";
$findResultHtml = $i18n->t("S5CCm3|I°m afraid there is noth...");

$formDefinition = [
    1 => "R;search,;\n".
        "R;submit;" . $i18n->t("jm0ymC|Find"),
    2 => ""
];

$lowerCaseAsciiFind = "";
$findResult = [];

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
        $lowerCaseAsciiFind = WordIndex::toLowerAscii($validatedEntries["search"]);
        $config->rootItem->find($lowerCaseAsciiFind, $findResult);
        $todo = $runner->done + 1;
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
echo "<h3><br><br>" . $i18n->t("xJZxGb|Search the configuration") . "</h3>";

echo Form::formErrorsToHtml($formErrors);
if ($todo < 2) {
    echo $formToFill->getHtml();
} else {
    if (count($findResult) == 0)
        echo $i18n->t("k0uqfc|I°m afraid there was no ...");
    else {
        echo $i18n->t("c8I94h|The following configurat...");
        echo "<ul>";
        $linkPrefix = "<a href='../_pages/configureApp.php?mode=inspect&top=";
        foreach($findResult as $path => $found) {
            $pathElements = explode(".", $path);
            $last = $pathElements[count($pathElements) - 1];
            $linkPath = (PropertyName::valueOfOrInvalid($last) == PropertyName::INVALID)
                ? substr($path, 0, strrpos($path, ".")) : $path;
            $found = WordIndex::boldWhereIs($found, $lowerCaseAsciiFind);
            echo "<li>$linkPrefix$linkPath'>$path</a> => $found</li>";
        }
        echo "</ul>";
    }
}

echo "</div>";
$runner->endScript();

    
