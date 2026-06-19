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
use tfyh\control\Menu;
use tfyh\control\Runner;
include_once "../_Control/LoggerSeverity.php";
include_once "../_Control/Runner.php";

use tfyh\util\I18n;
include_once "../_Util/I18n.php";

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../_Control/init.php";
$i18n = I18n::getInstance();
$runner = Runner::getInstance();

// ===== start page output
echo $runner->pageStart();

// page heading, identical for all workflow steps

echo "<h3>" . $i18n->t("1GPSPQ|Authorisations overview") . "</h3>";
echo "<p>" . $i18n->t("3Ju6sc|An overview of the curre...") . "</p>";
echo $runner->users->getAllAccesses();

echo "<h4>" . $i18n->t("VCcc4J|Permissions per role") . "</h4>";
$menu_file_path = "../Config/access/menuForUser";
$audit_menu = new Menu("identified");
echo $audit_menu->getAllowanceProfileHtml();

echo "</div><div class='w3-container'>";
echo "<h3>" . $i18n->t("NWE4ur|Information on data prot...") . "</h3>";
echo "<ul class='listWithMarker'><li>" .
         $i18n->t(
                "u34D4y|This information is prov...") .
         "</li></ul>";
echo "</div>";
$runner->endScript();
