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

namespace Data;

use DateTimeZone;

include_once '../../tfyh/Data/Item.php';
include_once '../../tfyh/Data/ParserConstraints.php';
include_once '../../tfyh/Data/Property.php';
include_once '../../tfyh/Data/Type.php';
include_once '../../tfyh/Util/Language.php';

use Control\Logger;
use Control\LoggerSeverity;

use Util\Language;

const DEFAULT_TIME_ZONE = "Europe/Berlin";

/**
 * A utility class to load all application configuration. This is a singleton class. The configuration is built as a
 * tree, starting from a single root item. The program configuration is loaded from CSV files, the "packaged" set of files,
 * and the "added" set of files, which are tenant-specific. User configuration preferences are part of the user record.
 * You can think of it like a file tree, items can have children and properties. Each item is referenced by a path
 * within the tree, starting with '.' for the root item. The hierarchy is a dotted sequence, e.g.
 * ".app.user_preferences.language". See the Item class for more details on the item itself.
 */
class Config
{
    public static array $allSettingsFiles = [
        // the sequence is relevant for the loading process, do not change
        // settings descriptor
        "descriptor", // the file to define the properties which describe a value
        "types", // the file to define the available value types
        // immutable settings. These will never change in structure nor get actual values
        "access", // the settings which configure roles, menus, workflows, concessions and subscriptions
        "templates", // configuration branches which are available for multiple use in this app
        "framework", // the setting which configures the framework classes for this app
        "tables", // the database table layout
        // tenant-mutable structure. These may get added or deleted items and actual values
        "lists", // the settings which configure list retrievals off the database
        "app", // all settings of the application needed to run at the tenant.
        "catalogs", // the catalogues of types, like the valid boat variants asf.
        "ui" // user interface layout and other settings
    ];

    public static array $allSettingsDirs = [
        // the sequence is relevant for the loading process, do not change
        "packaged", // this is provided as part of the release distribution
        "added", // all structure additions for the tenant, including tenant-specific UI profiles
    ];

    /**
     * The definition of the root of the configuration tree.
     */
    private static array $rootItemDefinition = [
        "_path" => "#none", "_name" => "root", "default_label" => "root", "value_type" => "none" ];
    /**
     * The definition of the invalid item. This is a floating item, which is not part of the configuration tree. It
     * is used to represent the "null"-type item to avoid setting any item to null.
     */
    private static array $invalidItemDefinition = [
        "_path" => "#none", "_name" => "invalid", "default_label" => "invalid item", "value_type" => "none" ];

    private static Config $instance;
    static function getInstance(): Config {
        if (!isset(self::$instance))
            // create the instance
            self::$instance = new self();
        return self::$instance;
    }

    /**
     * Return the modification times of the configuration files for the settings loader of the client or the
     * Configuration panel.
     */
    public static function getModified(): String {
        $modified = "";
        foreach (self::$allSettingsDirs as $settingsDir) {
            foreach (self::$allSettingsFiles as $settingsFile) {
                $fName = "Config/$settingsDir/$settingsFile";
                if (file_exists("../../$fName"))
                    $modified .= "\n" . $fName . "=" . filemtime("../../$fName");
            }
        }
        return substr($modified, 1);
    }

    /**
     * The root and invalid item have no configuration file entry. They will be initialised within config->load()
     */
    public Item $invalidItem;
    /**
     * The root and invalid item have no configuration file entry. They will be initialised within config->load()
     */
    public Item $rootItem;
    private array $settingsCsv = [];
    private array $loaded = [];
    private Language $language = Language::DE;
    private DateTimeZone $timeZone;

    public Logger $logger;

    public String $appName = "";
    public string $appVersion = "0.0";
    public String $appUrl = "";

    /**
     * Singleton constructor.
     */
    private function __construct() {
        // while monitor and runner share the same session type logger, the configuration errors and warnings
        // get a different one because they will reissue the same warnings on every page.
        $this->logger = new Logger("../../var/Log/config.log");
    }

    /**
     * Get an Item by its path. Returns the invalid item on errors
     * @param string $path the path of the item, e.g. ".app.user_preferences.language"
     * @return Item the item or the invalid item if the path is not valid.
     */
    public function getItem(string $path): Item
    {
        if (strlen($path) == 0)
            return self::$instance->rootItem;
        if (!str_starts_with($path, "."))
            return self::$instance->invalidItem;
        $names = explode(".", substr($path, 1));
        $i = 0;
        $parent = $this->rootItem;
        while (($i < count($names) && !is_null($parent) && $parent->hasChild($names[$i])))
            $parent = $parent->getChild($names[$i++]);
        if ($parent != null) {
            if ($i == count($names))
                return $parent; // path fully resolved
            if ($parent === $parent->parent()) {
                // hit top level
                if (!($this->loaded[$names[0]] ?? false)) {
                    $this->loadBranch($names[0]);
                    $this->logger->log(LoggerSeverity::INFO, "Config->getItem()", "Path '"
                        . $names[0] . "' loaded.");
                    return $this->getItem($path);
                } else {
                    $this->logger->log(LoggerSeverity::ERROR, "Config->getItem()", "Path '$path' not found");
                    return self::$instance->invalidItem; // path is not resolved
                }
            }
        }
        $this->logger->log(LoggerSeverity::ERROR, "Config->getItem()", "Path '$path' not found");
        return self::$instance->invalidItem; // path is not resolved
    }

    /**
     * @return Language the language of the application as set by the configuration including the user preferences.
     */
    public function language(): Language { return $this->language; }
    public function timeZone(): DateTimeZone {
        if (!isset($this->timeZone))
            $this->timeZone = new DateTimeZone(DEFAULT_TIME_ZONE);
        return $this->timeZone;
    }

    /**
     * Load a top-level branch. This is not part of the main loading procedure but performed on demand based on
     * the getItem() requests. The branch name is the first part of the path.
     * @param String $branchName The branch name is the first part of the path.
     * @return void
     */
    private function loadBranch(String $branchName): void {
        if ($this->loaded[$branchName] ?? false)
            return;

        foreach (Config::$allSettingsDirs as $settingsDir) {
            $isPackaged = ($settingsDir == "packaged");
            if (isset($this->settingsCsv[$settingsDir][$branchName])) {
                $settingsMap = Codec::csvToMap($this->settingsCsv[$settingsDir][$branchName]);
                $loadingResult = $this->rootItem->readBranch($settingsMap, $isPackaged);
                if (strlen($loadingResult) > 0)
                    $this->logger->log(LoggerSeverity::ERROR, "Config->load",
                        "[$settingsDir]: $loadingResult");
            }
        }
        $this->loaded[$branchName] = true;
    }
    /**
     * Load the minimum configuration. This will only parse CSV files; therefore, no language setting is needed
     * beforehand.
     */
    public function load(): void
    {
        // only for the PHP, because the class initialiser cannot handle expressions
        ParserConstraints::init();

        // initialise the Type object
        $descriptorCsv = file_get_contents("../../Config/packaged/descriptor");
        $typesCsv = file_get_contents("../../Config/packaged/types");
        Type::init($descriptorCsv, $typesCsv);

        // Initialise the root and invalid item. They have no configuration file entry.
        // PHP only due to initialisation language restrictions. The nullish operator ensures rootItem and invalidItem
        // to stay the same if reloading occurs, e.g. in the _pages/error.php page
        $this->rootItem = $this->rootItem ?? Item::getFloating(self::$rootItemDefinition);
        $this->invalidItem = $this->invalidItem ?? Item::getFloating(self::$invalidItemDefinition);

        // read the settings
        foreach (Config::$allSettingsDirs as $settingsDir) {
            $this->settingsCsv[$settingsDir] = [];
            foreach (Config::$allSettingsFiles as $settingsFile)
                if (file_exists("../../Config/$settingsDir/$settingsFile"))
                    $this->settingsCsv[$settingsDir][$settingsFile] = file_get_contents("../../Config/$settingsDir/$settingsFile");
        }

        // set tables and language
        $loadTime = microtime(true);
        Record::copyCommonFields();
        file_put_contents("../../var/Log/tablesInitTimes.log", microtime(true) - $loadTime . "\n", FILE_APPEND);
        $languageString = $this->getItem(".app.user_preferences.language")->valueCsv();
        $this->language = Language::valueOfOrDefault(strtoupper($languageString));
        $this->appName = $this->getItem(".framework.app.name")->valueCsv();
        $this->appUrl = $this->getItem(".framework.app.url")->valueCsv();
        $this->appVersion = file_get_contents("../../version");

        // initialise the locale settings for parser and formatter (JavaScript code: see main loader.)
        $this->logger->setLocale($this->language);
        Formatter::setLocale($this->language);
        Parser::setLocale($this->language, $this->timeZone());
    }
}