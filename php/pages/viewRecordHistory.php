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
use Util\I18n;

/**
 * Display the record history in a human-readable form.
 */

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$runner = Runner::getInstance();
$dbc = DatabaseConnector::getInstance();
$i18n = I18n::getInstance();
$config = Config::getInstance();

$uid = (isset($_GET["uid"])) ? $_GET["uid"] : false;
$tableName = (isset($_GET["table"])) ? $_GET["table"] : false;
$recordItem = $config->getItem(".tables." . $tableName);
$tableLabel = $recordItem->label();
$record = $dbc->find($tableName, "uid", $uid);

// ===== start page output
echo $runner->pageStart();

echo "<h3>" . $i18n->t("E8Dd4Q|Version history of a °%1...", $tableLabel) . "</h3>";
if ($record === false)
    echo $i18n->t("GkURzg|The record in table °%1°...", $tableLabel, $uid);
if (isset($record["history"]))
    echo $dbc->getHistoryAsHtml($record["history"], $recordItem, false);
else
    echo $i18n->t("pH7Sis|Unfortunately, there is ...");
echo "</div>\n<!-- END OF Content -->";
$runner->endScript();
