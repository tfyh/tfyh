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
 * An overview on all accesses currently granted.
 */

include_once "../_Control/Runner.php";

use tfyh\data\Config;
include_once "../_Data/Config.php";

use tfyh\util\I18n;
include_once "../_Util/I18n.php";

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../_Control/init.php";
$i18n = I18n::getInstance();
$runner = Runner::getInstance();

$mode = (isset($_GET["mode"])) ? $_GET["mode"] : "show";
$top = (isset($_GET["top"])) ? $_GET["top"] : ".";
$topItem = Config::getInstance()->getItem($top);
$parentPath = $topItem->parent()->getPath();
$parentLink = ($topItem->parent() !== $topItem)
    ? "<a href='../_pages/configureApp.php?mode=$mode&top=" . $parentPath . "'>" . $parentPath . " ▲</a>" : "";
$refreshLink = "<a href='#'><span class='cfg-button' style='font-size:2em;' id='tfyhCfgPanel_refresh_all'>↺</span></a>";
// ===== start page output
echo $runner->pageStart();

// build page structure: header, content, footer.
echo "<div class='w3-container' id='tfyhCfg-container'>\n";
echo "<div class='w3-row'><div class='w3-col l1' id='tfyhCfg-header'></div></div>\n";
echo "<span class='tfyhConfigTop' id='$mode|$top'></span>\n";
echo "<div class='w3-container' id='tfyhCfg-branch'>" .
         $i18n->t("GoIpXR|loading to %1 configurat...", $mode, $top) . "</div>\n";
echo "<div class='w3-row'><div class='w3-col l1' id='tfyhCfg-footer'>$parentLink<br>$refreshLink</div></div>\n";
echo $runner->user2js();
echo "</div>";
$runner->endScript();
