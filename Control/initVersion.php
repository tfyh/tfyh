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
 * script to execute after upgrade to this version.
 */

use tfyh\control\Monitor;
use tfyh\control\Runner;
include_once '../_Control/Monitor.php';
include_once '../_Control/Runner.php';

use tfyh\data\DatabaseSetup;
use tfyh\util\I18n;

include_once '../_Data/DatabaseSetup.php';

$monitor = Monitor::getInstance("web");
$runner = Runner::getInstance();
$runner->startScript(__FILE__);
$i18n = I18n::getInstance();

// ===== control database setup
$databaseSetup = new DatabaseSetup();
$dbLayoutVerification = $databaseSetup->update_database_layout(true);

// ===== Reflect upgrade result to user
echo "<p><b>" . $i18n->t("oslDj7|Thank you for updating!") . "</b><br>";
if (! $dbLayoutVerification) {
    echo $i18n->t("GY59tz|When checking the databa...") .
        "<br>";
    echo "<b>" . $i18n->t("9nyiSN|Please run an audit and ...") . "</b><br><br>";
    echo "<a href='../_pages/databaseAudit.php'><input type='submit' class='formButton' value='" .
        $i18n->t("oCZhmi|start audit") . "'></a></p>";
} else {
    echo $i18n->t("5kpp1Q|Version %1 is no ready t...", file_get_contents("../public/version"));
    echo "<br>" . $i18n->t("88nTjP|Please do not reload thi...") . "<br><br>";
    echo "<a href='../pages/home.php'><input type='submit' class='formButton' value='" .
        $i18n->t("LqsK5O|Start the new version"). "'></a></p>";
}
