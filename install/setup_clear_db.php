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
 * A database bootstrap script to create the server side admin tables and the first admin user.
 */

// ===== THIS SHALL ONLY BE USED during application configuration, then access rights shall
// be changed to "no access" - even better: or the form deleted from the site.

// ===== initialize toolbox
use Control\Sessions;
use Data\DatabaseConnector;
use Data\DatabaseSetup;
use Data\Findings;
use Data\Ids;
use Data\Validator;

// reduced init procedure for setup files
include 'setup_init.php';
global $config, $i18n, $runner;
include_once "../../tfyh/init/includeAll.php";

$dbc = DatabaseConnector::getInstance();

$dbName = $dbc->dbName();

// ===== define admin user default configuration
// set default values
$defaultAdminUser["adminFirstName"] = "Alex";
$defaultAdminUser["adminLastName"] = "Admin";
$defaultAdminUser["adminId"] = "1142";
$defaultAdminUser["adminPassword"] = "";
$defaultAdminUser["adminPasswordConfirm"] = "";

// ===== Form texts for admin user configuration
$userFieldsDescription["adminFirstName"] = $i18n->t("z1iH2X|first name of administra...");
$userFieldsDescription["adminLastName"] = $i18n->t("yX4D1v|last name of administrat...");
$userFieldsDescription["adminId"] = $i18n->t(
        "bX2gGZ|user id for administrato...");
$userFieldsDescription["adminPassword"] = $i18n->t("Alcaqq|password for administrat...");
$userFieldsDescription["adminPasswordConfirm"] = $i18n->t("yUqlic|password repeated for ad...");

// ===== define field format in configuration form
$userFieldsInputType["adminFirstName"] = "text";
$userFieldsInputType["adminLastName"] = "text";
$userFieldsInputType["adminId"] = "text";
$userFieldsInputType["adminPassword"] = "password";
$userFieldsInputType["adminPasswordConfirm"] = "password";

// === PAGE OUTPUT ===================================================================
echo file_get_contents('../../Config/snippets/page_01_start');
echo file_get_contents('../../Config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps

echo "<h3>" . $i18n->t("SJJb4H|Delete database %1 and r...", $dbName) . "</h3>";
echo "</div><div class='w3-container'>";

if ((isset($_GET['done']) && intval($_GET["done"]) == 1)) {
    
    foreach ($defaultAdminUser as $key => $value)
        $bootstrapAdminUser[$key] = $value;
    
    // read entered values into $cfg_to_use array.
    foreach ($defaultAdminUser as $key => $value) {
        $new_value = $_POST[$key];
        if (! is_null($new_value) && (strlen($new_value) > 0))
            $bootstrapAdminUser[$key] = $_POST[$key];
    }
    // check password
    if (strcmp($bootstrapAdminUser["adminPassword"], $bootstrapAdminUser["adminPasswordConfirm"]) != 0) {
        echo "<h4>" . $i18n->t("SPIwPm|Sorry, but password and ...") . "</h4>";
        echo "<p>" . $i18n->t("mFJD4W|You may %1try again%2.", "<a href='?done=0'>", "</a>") . "</p>";
        echo "</div>";
        exit(); // really exit. No test case left over.
    }
    Findings::clearFindings();
    Validator::checkPassword($bootstrapAdminUser["adminPassword"]);
    if (Findings::countErrors() > 0) {
        echo "<h4>" . $i18n->t("0dHbIb|Sorry, but the password ...") . "</h4>";
        echo "<p>" . $i18n->t("Onf4wv|You may %1try again%2.", "<a href='?done=0'>", "</a>") . "</p>";
        echo "</div>";
        exit(); // really exit. No test case left over.
    }
    // set properties of the selected admin as bootstrap user, to be able to manipulate the database later.
    $adminUserForBootstrap = [];
    $userFirstNameFieldName = $config->getItem(".framework.users.user_firstname_field_name")->valueStr();
    $userLastNameFieldName = $config->getItem(".framework.users.user_lastname_field_name")->valueStr();
    $userIdFieldName = $config->getItem(".framework.users.user_id_field_name")->valueStr();
    $userAdminRole = $config->getItem(".framework.users.useradmin_role")->valueStr();
    $adminUserForBootstrap[$userFirstNameFieldName] = $bootstrapAdminUser["adminFirstName"];
    $adminUserForBootstrap[$userLastNameFieldName] = $bootstrapAdminUser["adminLastName"];
    $adminUserForBootstrap[$userIdFieldName] = $bootstrapAdminUser["adminId"];
    $adminUserForBootstrap["password_hash"] = password_hash($bootstrapAdminUser["adminPassword"], PASSWORD_DEFAULT);
    $adminUserForBootstrap["role"] = $userAdminRole;

    // ===== open the database access and set the session user
    $dbc = DatabaseConnector::getInstance();
    $dbc->open();
    $databaseSetup = new DatabaseSetup();
    if (! isset($adminUserForBootstrap["uuid"]))
        $adminUserForBootstrap["uuid"] = Ids::generateUuid();
    if (! isset($adminUserForBootstrap["uid"]))
        $adminUserForBootstrap["uid"] = Ids::generateUID(6);
    $runner->sessions = Sessions::getInstance("web");
    $runner->sessions->setAdminUserForBootstrap($adminUserForBootstrap);

    // ===== create database, including the insertion of the first admin user.
    $bootstrapResult = $databaseSetup->initDataBase();
    
    // ===== return the result
    echo "<p>" . $bootstrapResult . "</p>";
    // Display result and next steps
    echo "<h3>" . $i18n->t("Z4Qu8U|Done.") . "</h3>";
    echo "<p>" . $i18n->t("3xwsuW|The database was delete...",
            "<a href='setup_finish.php'>", "</a>") . "</p>";
} else {
    echo "<p>" .
        $i18n->t("LTZcG4|Please enter the adminis...") .
        "</p>";
	echo "<form action='?done=1' method='post'>\n		<table>";
    // Display form fields depending on the installation mode.
    foreach ($defaultAdminUser as $key => $value)
        echo '<tr><td>' . $key . ':<br>' . $userFieldsDescription[$key] .
                 '&nbsp;</td><td><input class="formInput" type="' . $userFieldsInputType[$key] .
                 '" size="35" maxlength="250" name="' . $key . '" value="' . $value . '"></td></tr>';
    echo "</table><br> <input class='formButton' type='submit' value='" .
        $i18n->t("PYRA5W|Setup database from scra...") . "'>	</form>";
    echo "<h3>" . $i18n->t("PW2Hz1|DANGER ZONE: THIS WILL D...", $dbName) . "</h3>";
}
echo "</div>";
