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
use Data\Record;
use Util\I18n;
use Util\Language;

/**
 * Generic record display file.
 */

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$runner = Runner::getInstance();
$dbc = DatabaseConnector::getInstance();
$i18n = I18n::getInstance();
$config = Config::getInstance();

$tableName = (isset($_GET["table"])) ? $_GET["table"] : false;
$uid = (isset($_GET["uid"])) ? $_GET["uid"] : false;

if (! $config->getItem(".tables")->hasChild($tableName))
    $runner->displayError($i18n->t("L5LuMQ|Provided table name has ..."),
            $i18n->t("crDZRs|Page °%1° must be called...", $userRequestedFile,
                    $tableName), $userRequestedFile);
$recordItem = $config->getItem(".tables")->getChild($tableName);
$dbRecord = [ "uid" => "not found" ];
if ($uid && $tableName) {
    $tableRow = $dbc->find($tableName, "uid", $uid);
    if ($tableRow === false)
        $runner->displayError($i18n->t("FpMhl1|Record not found"),
            $i18n->t("ebNcoK|The record with uid %1 w...", $uid, $tableName), $userRequestedFile);
    else
        $dbRecord = $tableRow;
} else {
    $runner->displayError($i18n->t("KjzmnU|Not allowed."),
            $i18n->t("b1CfJu|Page °%1° must be called...", $userRequestedFile), $userRequestedFile);
}
$record = new Record($recordItem);
$record->parse($dbRecord, Language::SQL);
$occurrencesLink = ($recordItem->hasChild("uuid")) ? "<a href='../../tfyh/pages/whereIs.php?uuid=" .
    $dbRecord["uuid"] . "'>" : "";

// ===== start page output
echo $runner->pageStart();

echo "<h3>" . $i18n->t("Zc1xZ8|Record of table %1 for %...", $recordItem->name(),
        $record->recordToTemplate("name")) . "</h3>";
echo "<p>" . $i18n->t("lwuYMn|Summary") . ": " . $record->recordToTemplate("full") . " - ";
if (strlen($occurrencesLink) > 0)
    echo $occurrencesLink . $i18n->t("twKTVn|Find occurrences") . "</a> - ";
echo "<a href='../../tfyh/forms/editRecord.php?table=$tableName&uid=" . $dbRecord["uid"] . "'>" . $i18n->t("LY4vGp|Edit record") . "</a></p>";

echo $record->toHtmlTable($config->language());
echo "</div>";
$runner->endScript();
