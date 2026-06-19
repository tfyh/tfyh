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
 * An implementation of a form to define the settings of the database access.
 */

// ===== THIS SHALL ONLY BE USED during application configuration, then access rights shall
// be changed to "no access" - even better: or the form deleted from the site.

// ===== initialize toolbox
use tfyh\control\Monitor;
use tfyh\control\Runner;
include_once "../_Control/Monitor.php";
include_once "../_Control/Runner.php";

use tfyh\data\Config;
use tfyh\data\DatabaseConnector;
include_once "../_Data/Config.php";
include_once "../_Data/DatabaseConnector.php";

use tfyh\util\I18n;
use tfyh\util\Language;
include_once "../_Util/I18n.php";
include_once "../_Util/Language.php";

// redirect error reporting
$cwd = getcwd();
$errorFile = substr($cwd, 0, strrpos($cwd, "/")) . "/Log/php_error.log";
error_reporting(E_ERROR | E_WARNING);
ini_set("error_log", $errorFile);

// This script is only used during installation. Access shall be forbidden in normal operation.
// Therefore, no load throttling or security check is applied.
$monitor = Monitor::getInstance("web");
$runner = Runner::getInstance();
$i18n = I18n::getInstance();

// run parts of the runner start script until database initialisation
// ===== Config and Monitor must have been initialised by init.php
$config = Config::getInstance();
$monitor = Monitor::getInstance();

// ===== load the configuration
$runner->setFields(__FILE__);
$config->load();
$runner->debugOn = $config->getItem(".app.operations.debug_on")->value();

// ===== initialize the internationalization support
$i18n = I18n::getInstance();
$i18n->loadResource(Language::DE);

// ===== define default values for configuration
$dbDefaultAccess["host"] = "rdbms.host.xyz";
$dbDefaultAccess["name"] = "app_data_base";
$dbDefaultAccess["user"] = "db_user";
$dbDefaultAccess["pwd"] = "password";

// ===== define display text for field in configuration form
$dbAccessFieldDescription["host"] = $i18n->t("vTZC3z|the database host serve...");
$dbAccessFieldDescription["name"] = $i18n->t("3fJWt1|the database name");
$dbAccessFieldDescription["user"] = $i18n->t("DTkSIs|name of the technical us...");
$dbAccessFieldDescription["pwd"] = $i18n->t("stSdEq|password of the technica...");

// ===== define field format in configuration form
$dbAccessFieldInputType["host"] = "text";
$dbAccessFieldInputType["name"] = "text";
$dbAccessFieldInputType["user"] = "text";
$dbAccessFieldInputType["pwd"] = "password";

// === PAGE OUTPUT ===================================================================
echo file_get_contents('../Config/snippets/page_01_start');
echo file_get_contents('../Config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo "<h3>" . $i18n->t("AAbSwk|Configure the database ...") . "</h3>";

// first set default values
$dbAccessToUse = $dbDefaultAccess;

// try to connect in step "done"
if ((isset($_GET['done']) && intval($_GET["done"]) == 1)) {
    
    // read entered values into $cfg_to_use array.
    foreach ($dbDefaultAccess as $key => $value) {
        $new_value = $_POST[$key];
        if (! is_null($new_value) && (strlen($new_value) > 0))
            $dbAccessToUse[$key] = $_POST[$key];
    }

    // test database access
    $connectionSuccess = true;
    echo "<p>" . $i18n->t("2J3SjW|Checking database conne...", $dbAccessToUse["user"]) . " ... ";
    $dbc = DatabaseConnector::getInstance();
    $connectRes = DatabaseConnector::getInstance()->open($dbAccessToUse);
    if ($connectRes === true)
        echo $i18n->t("XtU29P|Success.");
    else {
        echo $i18n->t("bUhlFK|Failed - what a pity. Th...", $connectRes);
        $connectionSuccess = false;
    }
    echo "</p>";
    
    // store the configuration
    if ($connectionSuccess !== false) {
        // pwd masking
        $dbAccessToUse["pwd"] = DatabaseConnector::swapLChars($dbAccessToUse["pwd"]);
        // write to settings file
        $cfgStr = serialize($dbAccessToUse);
        $cfgStrBase64 = base64_encode($cfgStr);
        echo "<p>" . $i18n->t("brci3Z|writing configuration") . " ...</p>";
        $byteCnt = file_put_contents("../Config/db", $cfgStrBase64);
        echo $byteCnt . " Bytes.</p>";
        echo "<h3>" . $i18n->t("zKNEos|Completed successfully.") . "</h3>";
        echo "<p>" . $i18n->t("dsRzOm|The configuration of the...") . "</p>";
        
        $tableNames = DatabaseConnector::getInstance()->tableNames();
        $hasUsersTable = false;
        $userTableName = $config->getItem(".framework.users.user_table_name")->valueStr();
        if (count($tableNames) > 0)
            foreach ($tableNames as $table_name)
                $hasUsersTable = $hasUsersTable || (strcasecmp($table_name, $userTableName) == 0);
        if (! $hasUsersTable) {
            echo "<p>" . $i18n->t("7TZdGF|There was no table found...") . "</p>";
            echo "<a href='/setup_clear_db.php' target='_blank'>&rArr; " .
                     $i18n->t("voVsDM|Please build the data ba...") . "</p>";
        } else {
            echo "<p>" . $i18n->t("ov6KSI|There is a table of user...") . "<br>";
            echo "<a href='/setup_finish.php'>" . $i18n->t("eMKeDR|Please terminate the ins...") .
                     "</a></p>";
        }
    } else {
        echo "<h3>" . $i18n->t("IVM45L|Unfortunately, something...") . "</h3>";
        echo "<p>" . $i18n->t("3SIWc4|The database access con...") . "</p>";
        echo "<p><a href='?done=0'>&rArr; " . $i18n->t("WxKo5h|You may try again.") . "</a></p>";
    }
} else {
    echo "<p>" . $i18n->t("6Ln3Bn|Please provide the data ...") . "</p>";
    echo "<form action='?done=1' method='post'>\n		<table>";
    // Display form fields depending on the installation mode.
    foreach ($dbAccessToUse as $key => $value) {
        echo '<tr><td>' . $key . ':<br>' . $dbAccessFieldDescription[$key] .
                 '&nbsp;</td><td><input class="formInput" type="' . $dbAccessFieldInputType[$key] .
                 '" size="35" maxlength="250" name="' . $key . '" value="' . $value . '"></td></tr>' . "\n";
    }
    echo "\n    </table>\n	<br> <input class='formButton' type='submit' value='" . $i18n->t("jQB4Kt|submit") . "'>\n</form>";
}
echo "</div></body></html>";
