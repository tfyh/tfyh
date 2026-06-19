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

namespace tfyh\pages;

use tfyh\control\Runner;
use tfyh\data\Config;
use tfyh\util\I18n;
use tfyh\util\ListHandler;

/**
 * Page display file. lists available for data analysis
 */

// ===== initialize

$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$runner = Runner::getInstance();
$i18n = I18n::getInstance();
$config = Config::getInstance();

// ===== build selection table
$selection = "<ul>";
$analyse = new ListHandler("analyse", "");
foreach ($analyse->getAllListDefinitions() as $l) {
    if ($runner->users->isAllowedItem($l["permission"])) {
        $listName = $l["name"];
        $tableName = $l["table"];
        $recordItem = $config->getItem(".tables." . $tableName);
        $tableLabel = $recordItem->label();
        $linkListRecords = "<a href='../../tfyh/pages/showList.php?name=$listName&set=analyse'>" .
            $i18n->t("tIUwtU|show") . "</a>";
        $linkAddRecord = "<a href='../../tfyh/forms/editRecord.php?table=$tableName&uid=new'>" .
            $i18n->t("DBX9sP|new") . "</a>";
        $linkShowTrash = "<a href='../../tfyh/pages/showList.php?set=moved&name=trash&listparameter=$tableName'>" .
            $i18n->t("KX00ye|waste basket") . "</a>";
        $linkShowChanges = "<a href='../../tfyh/pages/showList.php?set=moved&name=changes&listparameter=$tableName'>" .
            $i18n->t("g4L7FG|changed") . "</a>";
        $linkShowArchived = "<a href='../../tfyh/pages/showList.php?set=moved&name=archive&listparameter=$tableName'>" .
            $i18n->t("F5yg2s|archived") . "</a>";
        $selection .= "<li><b>$tableLabel</b> ($tableName)<br> " . $i18n->t("uRHqUh|actions") .
            ": $linkListRecords | $linkAddRecord | $linkShowTrash | $linkShowChanges | $linkShowArchived</li>\n";
    }
}
$selection .= "</ul>";

// ===== start page output
echo $runner->pageStart();

echo "<h3>" . $i18n->t("Q3Kiql|Analyse or modify tables...") . "</h3>";
echo $selection;

echo "<!-- END OF Content -->\n</div>";
$runner->endScript();
