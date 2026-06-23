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

include_once "../Control/Monitor.php";
include_once "../Control/Runner.php";
include_once "../Data/Config.php";

use Control\Monitor;
use Control\Runner;
use Data\Config;
use Util\I18n;
use Util\Language;

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
$i18n->loadResource(Language::DE);
$runner->debugOn = $config->getItem(".app.operations.debug_on")->value();
