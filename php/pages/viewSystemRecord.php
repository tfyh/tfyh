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

/**
 * Rubbish record display file.
 */

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$runner = Runner::getInstance();
$dbc = DatabaseConnector::getInstance();
$i18n = I18n::getInstance();
$config = Config::getInstance();

$id = (isset($_GET["id"])) ? $_GET["id"] : "-"; // identify via id
$table = (isset($_GET["table"])) ? $_GET["table"] : "trash"; // either archive, change, or rubbish
$record = false;
if (intval($id) <= 0)
    $runner->displayError($i18n->t("yMxAqF|Not allowed."),
            $i18n->t("9tMKlx|The °%1° page must be ca...", $userRequestedFile),
                    $userRequestedFile);
else
    $record = $dbc->find($table, "id", $id);
if ($record === false)
    $runner->displayError($i18n->t("Tt7zlq|Not found."),
        $i18n->t("CQieDL|The record with Id %1 ...", $id, $table), $userRequestedFile);
$recordsUid = $record["uid"];
$recordsAuthor = $record["author"];
$recordsTime = $record["time"];
$recordsTable = $record["table"];
$restoredRecord = [];
$modification = "";
if (isset($record["record"])) {
    $ctrl_replaced = preg_replace('/[[:cntrl:]]/', '', $record["TrashedRecord"]);
    $restoredRecord = json_decode($ctrl_replaced, true);
} elseif (isset($record["modification"])) {
    $modification = $record["modification"];
}

// ===== start page output
echo $runner->pageStart();

// page heading, identical for all workflow steps
echo "<h3>" . $i18n->t("ySBlMg|Display of a system reco...", $table, $recordsTable) . "</h3>";
echo "<p>" . $i18n->t("IEHrUx|The system record fields...") . "</p>";
echo "<p><b>id</b>: " . $record["id"] . "</p>";
echo "<p><b>uid</b>: " . $record["uid"] . "</p>";
echo "<p><b>". $i18n->t("UvfVMp|time") . "</b>: " . $record["time"] . "</p>";
echo "<p><b>". $i18n->t("pwm01Q|author") . "</b>: " . $record["author"] . "</p>";
if (strlen($modification) > 0) {
    echo "<p><b>". $i18n->t("yobG1D|author") . "</b>: " . $modification . "</p>";
} else {
    $recordItem = $config->getItem(".tables." . $recordsTable);
    $record = new Record($recordItem);
    $language = $config->language();
    $record->parse($restoredRecord, $language);
    echo $record->toHtmlTable($language);
}
echo "</div>";
$runner->endScript();
