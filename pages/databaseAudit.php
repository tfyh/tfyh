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

/**
 * A page to audit the complete database.
 */

namespace tfyh\pages;

use tfyh\control\Runner;
use tfyh\data\DatabaseConnector;
use tfyh\data\DatabaseSetup;
use tfyh\util\I18n;

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$i18n = I18n::getInstance();
$runner = Runner::getInstance();
$dbc = DatabaseConnector::getInstance();
$dbSetup = new DatabaseSetup();

// ===== Improve database status, if requested
$improve = (isset($_GET["do_improve"])) ? $_GET["do_improve"] : "";
$doImprove = (strcmp($improve, "now") == 0);
$improvements = "";

// ===== Size check
// start with the database size in kB
// ===================================
$auditResult = "<li><b>" . $i18n->t("SqtY4N|List of tables by size") . "</b></li>\n<ul>";
$tableSizes = $dbc->tableSizesKiloBytes();
$totalSize = 0;
$tableRecordCountList = "<b>" . $i18n->t("NKmdC2|Size check: Tables and r...") . "</b><ul>";
$totalRecordCount = 0;
$totalTableCount = 0;
foreach ($tableSizes as $name => $size) {
    $recordCount = $dbc->countRecords($name);
    $totalRecordCount += $recordCount;
    $totalSize += intval($size);
    $totalTableCount ++;
    $tableRecordCountList .= "<li>$name: $recordCount " . $i18n->t("xhYaXI|Records") .
             ", $size kB]</li>";
}
$tableRecordCountList .= "<li>" . $i18n->t("qHF8o7|in total:") . " $totalRecordCount " . $i18n->t("jnzy2g|Records") .
         ", $totalTableCount " . $i18n->t("tables") . ", $totalSize kB.</li></ul>";

// ===== Layout implementation check
$verificationResult = "<b>" . $i18n->t("e5VOu1|Result of layout check") . "</b><ul><li>";
$dbLayoutVerified = $dbSetup->update_database_layout(! $doImprove);
if ($dbLayoutVerified) {
    $optimizationNeeded = false;
    $verificationResult .= $i18n->t("m0N2tS|The layout of the data b...");
} else {
    $optimizationNeeded = true;
    $verificationResult .= $i18n->t("1Pq6VT|NOT OK.") . "</li><li>" . str_replace("\n", "</li><li>",
            str_replace($i18n->t("DQYc1I|Verification failed"), "<b>" . $i18n->t("P1TqTx|Verification failed") . "</b>",
                    file_get_contents("../../var/Log/sys_db_audit.log")));
}
$verificationResult .= "</li></ul>";

// ===== start page output
echo $runner->pageStart();

echo "<h3>" . $i18n->t("6ePLG9|Audit for database %1", $dbc->dbName()) . "</h3>";
echo "<p>" . $i18n->t("ImbQDK|Here is the result of th...") . "</p>";

echo $improvements;
echo $verificationResult;
if ($optimizationNeeded)
    echo '<p><a href="?do_improve=now"><span class="formbutton">' . $i18n->t("MQEsnc|Correct now - Wait - tak...") .
             '</span></a><br /><br /></p>';
echo $tableRecordCountList;

echo "</div>";
$runner->endScript();
