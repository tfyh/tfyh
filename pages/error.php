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
 * A generic error message display page.
 */

namespace tfyh\pages;

use tfyh\control\Logger;
use tfyh\control\LoggerSeverity;
use tfyh\control\Monitor;
use tfyh\control\Runner;
use tfyh\control\Sessions;
include_once "../../tfyh/Control/Logger.php";
include_once "../../tfyh/Control/LoggerSeverity.php";
include_once "../../tfyh/Control/Monitor.php";
include_once "../../tfyh/Control/Runner.php";
include_once "../../tfyh/Control/Sessions.php";

use tfyh\data\Config;
include_once "../../tfyh/Data/Config.php";

use tfyh\util\I18n;
include_once "../../tfyh/Util/I18n.php";

// ===== read error information first
$lastErrorFile = "../../var/Run/lastError.txt";
$last_error = file_get_contents($lastErrorFile);
if (($last_error !== false) && (count(explode(";", $last_error)) >= 3)) {
    // "error.php" is called with an error description file provided.
    $error_description = explode(";", $last_error);
    $source_file = $error_description[0];
    $headline = $error_description[1];
    $text = $error_description[2];
    $get_params = (isset($error_description[3])) ? $error_description[3] : "";
} else {
    // "error.php" is called without an error description file provided.
    file_put_contents($lastErrorFile, "-invalid-");
    $source_file = "no_source";
    $headline = "Undefined error";
    $text = "It was not possible to get any information on the last error, that occurred.";
    $get_params = "";
}
$file_path_elements = explode("/", $source_file);
$index_last = count($file_path_elements) - 1;
$login_goto = $file_path_elements[$index_last - 1] . "/" . $file_path_elements[$index_last] . "?" . $get_params;

// exit, if the last error was caused by this page to avoid an infinite loop.
if (str_ends_with($source_file, "error.php")) {
    echo "<h3>Aborting due to an error reported by the error display page:</h3><h4>$headline</h4><p>$text</p>";
    exit(); // really exit, no test case left over
}

// throttle the throughput, if too many the errors occurred.
$config = Config::getInstance();
$config->load();
$maxErrorsPerHour = $config->getItem(".framework.sessions.max_errors_per_hour")->value();
// This can be explicitly suppressed by prefixing the error headline with "!#"
if (str_starts_with($headline, "!#"))
    $headline = substr($headline, 2);
else
    Monitor::getInstance("web")->throttle(true, $maxErrorsPerHour);

// return on concurrency limit violation
$too_many_sessions = (strcasecmp(Sessions::$tooManySessionsErrorHeadline, $headline) == 0);
if ($too_many_sessions) {
    $logger = new Logger(Monitor::logFilePath("web"));
    $logger->log(LoggerSeverity::WARNING, "error.php", "Application overload");
    // Return a very short String, no formatting.
    echo "<html lang=''><body><h4>Overload</h4><p>" . $headline . "</p><p>" . $text .
             "</p></body></html>";
    Runner::getInstance()->endScript(false);
}

// Now start the usual page start script
// ===== initialise
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$runner = Runner::getInstance();
$i18n = I18n::getInstance();

// redirect to the login.php page on invalid session user, but valid triggering page (usually a
// session timeout event).
if (($runner->sessions->userId() < 0) && (strrpos($source_file, "login.php") === false) &&
         (strrpos($source_file, "no_source") === false))
    header("Location: " . $runner->appRoot . "/../tfyh/forms/login.php?onerror=1&goto=" . urlencode($login_goto));

// ===== start page output
echo $runner->pageStart();

// page heading, identical for all workflow steps

echo "<h2>" . $i18n->t("UCOyeY|Error") . "</h2>";
echo "<h3>" . $i18n->t("JmUvue|Sorry, that didn°t work.") . "</h3>";
echo "<h3><br><br>" . $headline . "</h3>";
echo "<p>" . $text . "</p>";
if (($runner->sessions->userId() >= 0))
    echo "<p><br>" . $i18n->t("9mmdUG|The session is still act...") . "</p>";
echo "</div>";
$runner->endScript();