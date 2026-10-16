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
 * The page to log out and show that the logout was successful.
 */

use Control\Menu;
use Control\Runner;
use Util\I18n;

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$runner = Runner::getInstance();
$runner->sessions->sessionClose("logout");
$runner->menu = new Menu("public");

$i18n = I18n::getInstance();

// ===== start page output
echo $runner->pageStart();

echo "<h3><br><br><br><br>" . $i18n->t("cxAly0|Logged off") . "</h3>";
echo "<p>" . $i18n->t("bvy48a|Logoff was successful.") . "</p>\n</div>";
$runner->endScript();

