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
 * The page to show the data model.
 */
use tfyh\control\Runner;
include_once "../_Control/Runner.php";

use tfyh\data\DatabaseConnector;
use tfyh\data\DatabaseSetup;
include_once "../_Data/DatabaseConnector.php";
include_once "../_Data/DatabaseSetup.php";

use tfyh\util\FileHandler;
use tfyh\util\I18n;
include_once "../_Util/FileHandler.php";
include_once "../_Util/I18n.php";

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../_Control/init.php";
$runner = Runner::getInstance();
$i18n = I18n::getInstance();
$dbc = DatabaseConnector::getInstance();
$dbSetup = new DatabaseSetup();

$downloadCsv = (isset($_GET["download"])) ? intval($_GET["download"]) : 0;

// === APPLICATION LOGIC ==============================================================

$structureHtml = "<h4>" . $i18n->t("yxtlEE|data structure") . "</h4>";
$totalTableCount = 0;
$totalRecordCount = 0;
$tableNames = $dbc->tableNames();
$summary = "";
$csv = $i18n->t("0i10mg|Table name;Column name;D...");
$csv .= "\n";
foreach ($tableNames as $tn) {
    $recordCount = $dbc->countRecords($tn);
    $columnNames = $dbc->columnNames($tn);
    $columnTypes = $dbc->columnTypes($tn);
    $structureHtml .= "<h5>" . $tn . " " .
             $i18n->t("RNkGtP|(%1 data records with %2...", $recordCount, count($columnNames)) . "</h5>";
    $dataKey = $dbc->indexes($tn, true);
    $autoIncrements = $dbc->autoIncrementColumns($tn);
    $notNulls = $dbc->columnsNotNull($tn);

    $totalRecordCount += $recordCount;
    $totalTableCount ++;
    $structureHtml .= "<ul>";
    $allColumns = "";
    $c = 0;
    $keyComment = "";
    foreach ($columnNames as $cn) {
        $keyComment = "";
        if (isset($dataKey[$cn]))
            $keyComment .= $dataKey[$cn];
        if (isset($notNulls[$cn]))
            $keyComment .= " : NOT NULL";
        if (isset($autoIncrements[$cn]))
            $keyComment .= " : AUTO_INCREMENT";
        $cnHtml = (strlen($keyComment) > 0) ? "<b>" . $cn . "</b>" : $cn;
        $structureHtml .= "<li>" . $cnHtml . " - " . $columnTypes[$c] . " " .
                 $keyComment . "</li>";
        $cTypeParts = explode("(", $columnTypes[$c]);
        $cType = $cTypeParts[0];
        $cSize = ((count($cTypeParts) > 1) && (strlen($cTypeParts[1]) > 0)) ? intval(
                mb_substr($cTypeParts[1], 0, mb_strlen($cTypeParts[1]) - 1)) : 0;
        $csv .= $tn . ";" . $cn . ";" . $cType . ";" . $cSize . ";" . $keyComment . "\n";
        $allColumns .= $cn . ",";
        $c ++;
    }
    $structureHtml .= "</ul>";
    if (strlen($allColumns) > 0)
        $allColumns = substr($allColumns, 0, strlen($allColumns) - 1);
    $summary .= $totalTableCount . ";permission;" . $tn . ";" . $allColumns . ";" . $tn . ";1;<br>";
}

$structureHtml .= "<h5>" . $i18n->t("Kzbcet|In total %1 tables with ...", $totalTableCount, $totalRecordCount) .
         "</h5>";

// return file before page output starts.
if ($downloadCsv > 0) {
    FileHandler::returnStringAsZip($csv, "database_layout.csv");
}

// === PAGE OUTPUT ===================================================================
echo $runner->pageStart();

// page heading

echo "<h3>" . $i18n->t("H3jSfa|The implemented data str...") . "</h3>";
echo "<p>" . $i18n->t("e2gF2y|This is the result of a ...") . "</p>";
echo '</div><div class="w3-container">';
echo $structureHtml;
echo '</div>';
$runner->endScript();

    
