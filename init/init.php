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

use Control\Monitor;
use Control\Runner;

// include statements are inserted later in the script code for performance reasons

const HITS_PER_SECOND_MAX = 2.0;
// ===== Redirect in case of maintenance
$maintenanceUntil = file_get_contents("../../maintenance.until");
if ($maintenanceUntil && (strlen(explode("\n", $maintenanceUntil)[0]) > 3))
    header("Location: ../../maintenance.php?until=" . urlencode($maintenanceUntil));

// ===== set a session type
// the $userRequestedFile must be set by the calling page. __FILE__ would point to this "init.php" here.
global $userRequestedFile;
if (! isset($userRequestedFile)) {
    echo "Fatal error in init.php. \$userRequestedFile is not set. Aborting.";
    exit();
}
$sessionType = (str_ends_with($userRequestedFile, "post_tx.php")) ? "api" : "web";

// ===== Set PHP error reporting.
$cwd = getcwd();
$tfyhDir = substr($cwd, 0, strrpos($cwd, "/"));
$rootDir = substr($tfyhDir, 0, strrpos($tfyhDir, "/"));
$errorFile = $rootDir . "/var/Log/php_error.log";
if (file_exists($errorFile) && (filesize($errorFile) > 200000)) {
    copy($errorFile, $errorFile . ".previous");
    file_put_contents($errorFile, "[continued]\n");
}
if ($_SERVER["HTTP_HOST"] == "localhost")
    error_reporting(E_ERROR | E_WARNING);
else
    error_reporting(E_ERROR);
ini_set("error_log", $errorFile);

// ===== start the Monitor and throttle to prevent from machine attacks.
// use the minimum set of includes for this part.
include_once '../../tfyh/Control/Monitor.php';
$monitor = Monitor::getInstance($sessionType);
$monitor->throttle(false, HITS_PER_SECOND_MAX);

// ===== instantiate the Runner for script execution control: session, database connector, etc.
// ===== This essentially loads the whole application including the i18n support.
include_once '../../tfyh/Control/Runner.php';
$runner = Runner::getInstance();

// now include all package files
include_once 'includeAll.php';

// register the shutdown function to be able to log preemptive script abortion.
function shutdown(): void { Runner::getInstance()->shutdown(); }
register_shutdown_function('shutdown');

// ===== initialisation finished. Start script execution
$runnerTime = microtime(true);
$runner->startScript($userRequestedFile);
file_put_contents("../../var/Log/runnerTimes.log", (microtime(true) - $runnerTime) . "\n", FILE_APPEND);
