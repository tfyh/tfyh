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

// ===== THIS SHALL ONLY BE USED during application configuration, then access rights shall
// be changed to "no access" - even better: or the form deleted from the site.
use tfyh\util\I18n;
use tfyh\util\Language;

include_once "../Util/I18n.php";
include_once "../Util/Language.php";

// remove install file from root folder
unlink("../../install.php");
$i18n = I18n::getInstance();
$i18n->loadResource(Language::DE);

// === PAGE OUTPUT ===================================================================
// page heading
echo file_get_contents('../../Config/snippets/page_01_start');
echo file_get_contents('../../Config/snippets/page_02_nav_to_body');

echo "<h3>". $i18n->t("sVDlYy|Application setup") . "</h3>";
echo "</div><div class='w3-container'><h3>" . $i18n->t("Kmj5St|The setup is completed.") . "</h3>";
echo "<p>" . $i18n->t("bM4Hxy|The file °install.php° w...") . "</p>";
echo "<p>" . $i18n->t("NI7Bvs|You can get started now.") . "</p>";
echo "<h4><a href='../../tfyh/forms/login.php' target='_blank'>" .  $i18n->t("s8LPqe|To the application login...") . "</a>";
echo " ... <a href='../../index.php'>" . $i18n->t("mWcht6|or to the homepage") . "</a></h4></div></body></html>";
// block access to install folder
file_put_contents("../tfyh/install/.htaccess", "Require all denied");
chmod("../tfyh/install", 0700);
