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

use Control\Runner;
use Data\Audit;
use Data\Config;
use Data\DatabaseConnector;
use Data\Formatter;
use Util\FileHandler;
use Util\I18n;

/**
 * The application software upgrade page.
 */

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$runner = Runner::getInstance();
$dbc = DatabaseConnector::getInstance();
$i18n = I18n::getInstance();
$config = Config::getInstance();

// Source Code path.
$versionInstalled = (file_exists("../public/version")) ? file_get_contents("../public/version") : "???";
$versionInstalledOn = (file_exists("../public/version")) ? filemtime("../public/version") : 0;
$currentVersion = $runner->getCurrentApplicationVersion("upgrade.php");

// ===== start page output
echo $runner->pageStart();

if (! isset($_GET["upgrade"])) {
    echo "<h3>" . $i18n->t("w2PJel|Upgrade of the %1 applic...", $config->getItem(".framework.app.name")->valueStr()) . "</h3>";
    echo "<p>" . $i18n->t("YYlWr8|The upgrade unpacks the ...") . "</p>";
    if (strlen($currentVersion) == 0)
        echo "<h4>" . $i18n->t("BwU3GN|No server version found....") . "</h4>";
    echo "<p>" . $i18n->t("ZC5KSz|Currently installed:") . " <b>" . $versionInstalled . "</b><br>" .
        $i18n->t("qC9XHB|Installed on:") . " <b>" . Formatter::microTimeToString(
            floatval($versionInstalledOn), $config->language()) . "</b></p>";
    echo "<p>" . $i18n->t("Current version at server") . " <b>" . $currentVersion . "</b></p>";
    echo "<p>" . $i18n->t("9dBgWW|An upgrade cannot be und...") . "</p>";
    echo "<p>" . $i18n->t("d0xnMU|Please note: the process...") . "</p>";
    echo "<form action='?upgrade=1' method='post'>\n <input type='submit' class='formButton' value='" .
             $i18n->t("M4MjRw|Update to version - %1", $currentVersion) . "' /> </form>";
} else {
    
    $upgradePath = $config->getItem(".framework.app.upgrade_url")->valueStr();
    $appName = $config->appName;
    $appSourcePath =  $upgradePath . "/$appName.zip";

    // check loaded modules
    // ==============================================================================================
    $referenceModules = ["bz2","calendar","Core","ctype","curl","date","dom","exif","fileinfo","filter","ftp",
            "gd","gettext","hash","iconv","json","libxml","mbstring","mysqli","openssl","pcre","pdo_mysql",
            "PDO","Phar","posix","Reflection","session","SimpleXML","SPL","standard","tokenizer","xml",
            "xmlreader","xmlwriter","xsl","zip","zlib"
    ];
    $thisServerModules = get_loaded_extensions();
    $missing = [];
    foreach ($referenceModules as $referenceModule) {
        $contained = false;
        foreach ($thisServerModules as $thisServerModule) {
            $contained = $contained || (strcmp($thisServerModule, $referenceModule) == 0);
        }
        if (! $contained)
            $missing[] = $referenceModule;
    }
    echo "<p>" . $i18n->t("kfB6Rx|Installed PHP modules ch...");
    if (count($missing) > 0) {
        echo "<br>" . $i18n->t("4SVUF8|The following modules ar...") . "<br>";
        foreach ($missing as $m)
            echo "'" . $m . "', ";
        echo $i18n->t("HxsB1x|It is possible that %1 a...", $config->appName) . "</p>";
    } else
        $i18n->t("Zg9SKd|ok") . "</p>";
    
    // fetch program source
    // ==============================================================================================
    echo "<p>" . $i18n->t("Bwa5WS|Loading the source code ...") . ": " . $appSourcePath . " ...<br>";
    file_put_contents("src.zip", file_get_contents($appSourcePath));
    echo " ... " . $i18n->t("HAqb4l|completed. File size") . ": " . filesize("src.zip") . ".</p>";
    if (filesize("src.zip") < 1000) {
        echo "<p>" . $i18n->t("uuZ8U8|The size of the source c...") . "</p></body></html>";
        exit(); // really exit. No test case left over.
    }
    
    // read settings, will be used as cache
    // ==============================================================================================
    echo "<p>" . $i18n->t("iM0FxL|Saving the existing conf...") . "</p>";
    $pathsToSave = [ "Config/db", "Config/tenant", "resources/app-colors.txt" ];
    $settings = [];
    foreach ($pathsToSave as $pathToSave)
        if (file_exists("../$pathToSave"))
            $settings[$pathToSave] = file_get_contents("../$pathToSave");

    // Open zip archive
    // ==============================================================================================
    echo "<p>" . $i18n->t("NgKZWV|Checking code archive") . " ... ";
    $zip = new ZipArchive();
    $res = $zip->open('src.zip');
    if ($res !== TRUE) {
        echo $i18n->t("7rViLS|Opening the code archive...") . "</p>";
        unlink("src.zip");
        $runner->endScript();
    }

    // Delete server side files.
    // ==============================================================================================
    echo "<p>" . $i18n->t("rve23X|Deleting old code") . " ...<br>";
    // delete other code
    $toplevelDirs = scandir("..");
    foreach ($toplevelDirs as $topLevelDir) {
        $delete = str_starts_with($topLevelDir, "_") || str_starts_with($topLevelDir, "js")
            || ((strcmp(strtolower($topLevelDir), $topLevelDir) != 0) && ($topLevelDir != "Log") && ($topLevelDir != "Uploads"));
        if ($delete) {
            echo $topLevelDir . " ... ";
            FileHandler::rrmdir($topLevelDir);
        }
    }
    echo "<br>" . $i18n->t("ag2Cwk|Done") . "</p>";
    
    // Unpack source files
    // ==============================================================================================
    echo "<p>" . $i18n->t("GuEiQo|Unpacking and copying th...") . "</p>";
    $zip->extractTo('..');
    $zip->close();

    // Remove obsolete files
    // ==============================================================================================
    echo "<p>" . $i18n->t("B9Tygk|removing files that are ...") . "<br>";
    $app_remove_files = $config->getItem("remove_files")->value();
    if (!is_array($app_remove_files))
        $app_remove_files = [];
    foreach ($app_remove_files as $app_remove_file) {
        if (file_exists("$app_remove_file")) {
            echo " --> " . $app_remove_file . "<br>";
            unlink($app_remove_file);
        }
    }
    echo $i18n->t('vp2UUx|ready.') . '</p>';
    unlink("src.zip");

    // restore settings
    // ==============================================================================================
    echo "<p>" . $i18n->t("AmEL6s|restoring the existing c...") . " ...</p>";
    foreach ($settings as $pathToSave => $setting)
        file_put_contents("../$pathToSave", $setting);

    // Set directory access rights and audit the upgrade result.
    // ==============================================================================================
    $audit = new Audit();
    $audit->setDirectoriesAccessRights($runner->appRoot, 0, false);
    $audit->runAudit();
    echo "<h5>" . $i18n->t("RHFSbA|Checking the result") . "</h5>";
    echo "<p>" . $i18n->t("RGjcvy|Done. For the audit prot...") . '</p>';

    // update the installation timestamp
    // ==============================================================================================
    file_put_contents("../tfyh/install/lastUpgrade", Formatter::microTimeToString(microtime(true)));
    
    // ==============================================================================================
    // initialize the version, if the respective script is available.
    // ==============================================================================================
    if (file_exists("../../tfyh/init/initVersion.php")) {
        include "../../tfyh/init/initVersion.php";
    }
}
$runner->endScript();
