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
 * A page to reset the complete database.
 */

namespace tfyh\pages;

use tfyh\control\Runner;
use tfyh\data\Config;
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

$doReset = (strcmp($_GET["do_reset"], "full") == 0);
$userFullName = $runner->sessions->userFullName();
if ($doReset)
    // ===== create database
    $resultBootstrap = $dbSetup->initDataBase();

// ===== start page output
echo $runner->pageStart();

echo "<h3>" . $i18n->t("P8NqjU|Delete database %1", $dbc->dbName()) . "</h3>";

if ($doReset) {
    echo "<p>" . $i18n->t(
            "z9JLLb|The database has been re...", $userFullName) . "</p>";
    echo "<p>" . $i18n->t("WOnK0g|The following activity r...") . "<br>" . $resultBootstrap .
             "</p>";
    echo "<p>" . $i18n->t("fCWHZq|Please log out and log i...") . "<br><br><a href='logout.php'>" .
             $i18n->t("4RX8ec|Logout") . "</a></p>";
} else {
    echo "<h3><br>" . $i18n->t("yaRbu4|DANGER ZONE!") . "</h3><p>" .
             $i18n->t("mw21ct|This is the option to de...");
    echo "<br><b>" . $i18n->t("ceLd6k|If You go for it the dat...");
    echo "</b><br>" . $i18n->t("dQ3PQG|Administrators are also ...", $userFullName);
    echo $i18n->t("DA6MeJ|The process can take 10-...") . "</p>";
    $appName = Config::getInstance()->appName;
    echo "<h4>" . $i18n->t("MOvHFw|I don°t really know.") . " &gt;&gt; <a href='../../$appName/pages/webApp.php'>" . $i18n->t("zD9aCz|Abort now.") . "</a></h4>";
    echo "<p>" . $i18n->t("CxPfCd|I am sure and I know wha...") . " &gt;&gt; <b>";
    echo "<a href='?do_reset=full'>" . $i18n->t("yu9T3e|Delete database °%1° at...", $dbc->dbName(), $runner->appRoot) .
             "</a></p>";
}
echo "</div>";
$runner->endScript();
