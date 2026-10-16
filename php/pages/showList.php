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
 * List display page. Shows either a list of a set or a list itself.
 */

use Control\LoggerSeverity;
use Control\Runner;
use Util\FileHandler;
use Util\I18n;
use Util\ListHandler;
use Util\PivotTable;

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$runner = Runner::getInstance();
$i18n = I18n::getInstance();

if (isset($_GET["set"]))
    $set = $_GET["set"];
else {
    $set = ""; // this is no more needed, just to avoid the undefined value warning later
    $runner->displayError($i18n->t("afDI5Q|Set parameter missing"),
        $i18n->t("7aHKoI|You have to provide a se..."), "showList.php");
}
$listName = $_GET["name"] ?? "";
if (isset($_GET["pivot"]))
    $pivot = explode(".", $_GET["pivot"]);
else
    $pivot = [];
if (isset($_GET["listparameter"]))
    $listParameter = ["{listparameter}" => $_GET["listparameter"]
    ];
else
    $listParameter = [];
    
$list = new ListHandler($set, $listName, $listParameter);

// ===== check list definitions.
if ($list->count() == 0)
    $runner->displayError("!#" . $i18n->t("YG4BuC|Configuration error."),
            "List configuration not found. Configuration error of the application. Please talk to the administrator.", 
            $userRequestedFile);
if ($list->noValidCurrentList() && (strlen($listName) > 0))
    $runner->displayError("!#" . $i18n->t("qaczPb|Configuration error."),
            "Searched list not found. Configuration error of the application. Please talk to the admin.", 
            $userRequestedFile);

// ===== identify used list and verify user permissions
$permissions = ($listName == "") ? $list->getSetPermission() : $list->getPermission();
$permitted = $runner->users->isAllowedItem($list->getSetPermission());
if (! $permitted) {
    $runner->displayError("List for user not permitted", 
            $i18n->t("slCqRl|The list °%1° must not b...", $listName, $runner->sessions->userRole(),
                    $runner->sessions->userWorkflows(), $permissions),
            $userRequestedFile);
}

// ====== zip-Download was requested. Create a zip and return it.
$oSortsList = (isset($_GET["sort"])) ? $_GET["sort"] : "";
$oFilter = (isset($_GET["filter"])) ? $_GET["filter"] : "";
$oFValue = (isset($_GET["fvalue"])) ? $_GET["fvalue"] : "";
$dataErrors = "";
if (isset($_GET["zip"]) && (intval($_GET["zip"]) > 0)) {
    if ($_GET["zip"] == 1) {
        $runner->logger->log(LoggerSeverity::INFO, "showList.php",
            "List: '$listName' made available to user " . $runner->sessions->userId() . " for download as csv.");
        $list->returnZip($oSortsList, $oFilter, $oFValue);
    } elseif (($_GET["zip"] == 2) && (count($pivot) == 4)) {
        $pivotTable = new PivotTable($list, $pivot[0], $pivot[1], $pivot[2], $pivot[3]);
        $csv = $pivotTable->getCsv();
        $runner->logger->log(LoggerSeverity::INFO, "showList.php",
            "Pivot of list: '$listName' made available to user " . $runner->sessions->userId() . " for download as csv.");
        FileHandler::returnStringAsZip($csv, $list->getTableName() . ".csv");
    }
}

// ===== start page output
echo $runner->pageStart();

$headline = ($list->getLabel()) ?: $i18n->t("rV1nL0|Available lists of set %...", $set);
echo "<h3>" . $headline . "</h3><p>";

if ($listName == "")
    echo $i18n->t("xo9xjE|The table shows all list...", $runner->sessions->userRole());
echo "</p>";
if ($listName == "") {
    echo "<table><tr><th>" . $i18n->t("Z4cDbe|name") . " </th><th>" . $i18n->t("pYGgkq|Permission") .
             " </th><th>" . $i18n->t("OJFiWA|Description") . " </th></tr>\n";
    foreach ($list->getAllListDefinitions() as $l) {
        if ($runner->users->isAllowedItem($l["permission"])) {
            $permissionsString = (str_starts_with($l["permission"], "#")) ? $i18n->t(
                    "epw5RT|subscriptions, mask") . $l["permission"] : $l["permission"];
            $permissionsString = (str_starts_with($l["permission"], "@")) ? $i18n->t("IVseh0|Workflows, Mask") .
                $l["permission"] : $l["permission"];
            $list_params = $list->getArgs($l);
            if (strlen($list_params) > 0)
                $list_params = " {" . $list_params . "}";
            echo "<tr><td><a href='?name=" . $l["name"] . "&set=" . $set . "'>"
                . $i18n->t($l["name"]) . $list_params . "</a></td><td>"
                . $permissionsString . "</td><td>" . $l["label"] . "</td></tr>\n";
        }
    }
    echo "</table>\n";
} else {
    echo $dataErrors;
    echo $list->getHtml($oSortsList, $oFilter, $oFValue, $pivot);
}

echo "<!-- END OF Content -->\n</div>";
$runner->endScript();
