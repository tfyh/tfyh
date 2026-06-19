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
 * Simple display page. Shows all recent changes.
 */

namespace tfyh\pages;

use tfyh\control\Runner;
use tfyh\data\DatabaseConnector;
use tfyh\util\I18n;

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$runner = Runner::getInstance();
$dbc = DatabaseConnector::getInstance();
$i18n = I18n::getInstance();

// ===== start page output
echo $runner->pageStart();

echo "<h3>" . $i18n->t("jkjFR8|Changes to data") . "</h3>";
echo "<p>" . $i18n->t("jvNIEJ|Each change to data is r...") . "</p>";
$dbc->cleanseChangeLog(100); // keep changes for max. 100 days.
echo $dbc->changelogAsHtml();
echo "<!-- END OF Content -->\n</div>";
$runner->endScript();
