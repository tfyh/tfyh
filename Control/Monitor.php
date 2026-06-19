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

namespace tfyh\control;

use DateTime;

include_once '../../tfyh/Control/Logger.php';

include_once '../../tfyh/Data/Codec.php';
include_once '../../tfyh/Data/Formatter.php';
use tfyh\data\Codec;
use tfyh\data\Formatter;

include_once '../../tfyh/Util/Language.php';
use tfyh\util\Language;

const MONITOR_PERIOD = 1000; // The period to monitor events for load throttling and load warning in seconds.
const WARNING_INTERVAL = 120; // the minimum period between to overload warning log entries in seconds.

/**
 * A utility class to hold the application usage monitoring functions. It is a singleton class. While the Runner
 * controls a single web or API transaction, the Monitor is used to monitor the overall application performance and the
 * count of open sessions. It will delay page requests if the load is too high.
 */
class Monitor
{
    private float $lastTimeStamp;
    public static function logFilePath(String $sessionType): string { return "../../var/Log/" . $sessionType . ".log"; }

    private static Monitor $instance;

    /**
     * @param String $sessionType the type of the session, either "web" or "api".
     */
    private function __construct(String $sessionType) {
        $this->scriptStartedOn = microtime(true);
        $this->scriptCompleted = false;
        $this->sessionType = $sessionType;
        $this->logger = new Logger(self::logFilePath($sessionType));
    }

    /**
     * @param String $sessionType the type of the session, either "web" or "api".
     * @return Monitor the singleton instance of the Monitor class.
     */
    public static function getInstance(String $sessionType = "undefined"): Monitor {
        if (! isset(self::$instance) && (strlen($sessionType) > 0))
            self::$instance = new self($sessionType);
        return self::$instance;
    }
    /**
     * the type of the session, either "web" or "api".
     */
    private String $sessionType;
    public float $scriptStartedOn;
    public bool $scriptCompleted;
    private Logger $logger;
    private string $monitorDirectory = "../../var/Run/monitor";

    function getLogger(): Logger { return $this->logger; }

    function getSessionType(): string { return $this->sessionType;  }

    /**
     * start temporary performance measurement
     */
    function startMonitor(): void {
        $now = DateTime::createFromFormat('U.u', microtime(true));
        $this->lastTimeStamp = microtime(true);
        file_put_contents("../../var/Run/monitor.log", $now->format("m-d-Y H:i:s.u") . ": continued.\n", FILE_APPEND);
    }
    /**
     * Continue temporary performance measurement by logging a time-stamped message.
     * @param String $message the message to log.
     * @return void
     */
    function timeStamp(String $message): void {
        $nowMicroTime = microtime(true);
        $deltaMs = intval(($nowMicroTime - $this->lastTimeStamp) * 1000);
        $nowMicroTime = DateTime::createFromFormat('U.u', microtime(true));
        $this->lastTimeStamp = microtime(true);
        file_put_contents("../../var/Run/monitor.log", $nowMicroTime->format("m-d-Y H:i:s.u") . "(+$deltaMs): $message\n", FILE_APPEND);
    }

    /**
     * Throttling uses a file to which it appends a dot "." or, if $isError, "ErrorEvent". The file
     * is replaced by an empty file every MONITOR_PERIOD seconds. If the file size increases beyond the throttling size
     * limit of MONITOR_PERIOD * $hitsPerSecond, the script is paused by the square of the load in seconds, the load
     * being the file size divided by the throttling size limit. A warning is logged as long as this happens every
     * WARNING_INTERVAL seconds.
     * @param bool $isError true if the event is an error event, false otherwise.
     * @param float $hitsPerSecond the number of hits per second to be considered for throttling.
     * @return void
     */
    public function throttle (bool $isError, float $hitsPerSecond): void
    {
        // an error event is made ten times more relevant than any other page hit for throttling.
        $eventString = ($isError) ? "ErrorEvent" : ".";
        if (!file_exists($this->monitorDirectory))
            mkdir($this->monitorDirectory);
        $eventsDir = $this->monitorDirectory . "/" . $this->sessionType;
        if (!file_exists($eventsDir))
            mkdir($eventsDir);
        // create a new file all MONITOR_PERIOD seconds
        $eventsFile = $eventsDir . "/" . intval(microtime(true) / MONITOR_PERIOD);
        if (!file_exists($eventsFile)) {
            // new slot, remove previous files
            $files = scandir($eventsDir);
            if ($files !== false)
                foreach ($files as $file)
                    if ($file != "." && $file != ".." && $file != "lastWarning") unlink($eventsDir . "/" . $file);
            file_put_contents($eventsFile, $eventString, LOCK_EX);
        } else {
            $limit = MONITOR_PERIOD * $hitsPerSecond;
            $load = (filesize($eventsFile) / $limit);
            if ($load > 1.0) {
                // delay the action, starting slowly with parabolic increase
                $delaySeconds = $load * $load;
                $timestamp = intval(microtime(true) / WARNING_INTERVAL);
                $lastWarning = file_get_contents($eventsDir . "/lastWarning");
                $nextWarning = intval($lastWarning) + WARNING_INTERVAL;
                if (!$lastWarning || ($nextWarning < $timestamp)) {
                    // I18n not loaded for performance reasons in case of overload.
                    $this->logger->log(LoggerSeverity::WARNING,
                        "Runner->throttle", "Throttling interface " . $this->sessionType .
                        ". Current load: " . (intval($load * 1000) / 10) . "%:");
                    file_put_contents($eventsDir . "/lastWarning", strval($timestamp));
                }
                sleep($delaySeconds);
            } else {
                file_put_contents($eventsFile, $eventString, FILE_APPEND | LOCK_EX);
            }
        }
    }

    /**
     * Log the response time per request and user. The response time is logged in microseconds,
     * the user id is the id of the user logged in, and the request is the file name of the request
     * @param int $userId the id of the user logged in.
     * @param String $request the file name of the request.
     * @return void
     */
    public function monitorResponseTime (int $userId, String $request): void
    {
        $filename = $this->monitorDirectory . "/responseTime.log";
        $timestamp = microtime(true) . ";" . $userId . ";" . $request . ";" .
            substr(strval(microtime(true) - $this->scriptStartedOn), 0, 6) . "\n";
        Logger::rotateIfNeeded($filename, "timestamp;user;file;responseTime");
        file_put_contents($filename, $timestamp, FILE_APPEND);
    }

    /**
     * Add an activity to the activities monitoring. $type shall be one of "init", "api", "error", or "login".
     * Used for usage statistics at the API.
     * @param int $userId the id of the user logged in.
     * @param String $type the type of the activity, one of "init", "api", "error", or "login"
     */
    public function monitorActivity (int $userId, String $type): void
    {
        $filename = $this->monitorDirectory . "/activities.log";
        $timestamp = microtime(true) . ";" . $userId . ";" . $type . "\n";
        Logger::rotateIfNeeded($filename, "timestamp;user;activity");
        file_put_contents($filename, $timestamp, FILE_APPEND);
    }

    /**
     * Provide a human-readable list of all entries in the log file.
     * @param string $filename the name of the log file.
     * @return string the list of entries, one per line.
     */
    public function list(string $filename): string
    {
        $list = "";
        // read log file, split lines and check one by one
        $logfile = file_get_contents($filename);
        $logAsTable = Codec::csvToMap($logfile);
        // write the header
        foreach ($logAsTable[0] as $key => $value)
            $list .= $key . " ";
        $list = mb_substr($list, 1, mb_strlen($list) - 1) . "\n";
        // write the entries
        foreach ($logAsTable as $record) {
            foreach ($record as $key => $value)
                $list .= (($key == "timestamp") ? Formatter::microTimeToString(floatval($value)) : $value) . " ";
            $list = mb_substr($list, 1, mb_strlen($list) - 1) . "\n";

        }
        return $list;
    }

    /**
     * Pivot all activities into a statistics array.
     * @return array the activities as a pivot table, one entry per day.
     */
    public function pivotActivities (): array
    {
        $filename = $this->monitorDirectory . "/activities.log";
        $lines = explode("\n", file_get_contents($filename));
        $pivot = [];
        $r = 0;
        foreach ($lines as $line) {
            $entries = explode(";", $line);
            if (($r > 0) && (count($entries) == 3)) {
                $date = substr(Formatter::microTimeToString(floatval($entries[0]), Language::CSV), 0, 10);
                $type = $entries[2];
                if (! isset($pivot[$date])) $pivot[$date] = [];
                if (! isset($pivot[$date][$type])) $pivot[$date][$type] = 0;
                $pivot[$date][$type] = $pivot[$date][$type] + 1;
            }
            $r++;
        }
        krsort($pivot);
        return $pivot;
    }

    /**
     * Reformat the activities as a table array for CSV encoding.
     * @return array the activities as a table for later CSV encoding.
     */
    public function activitiesToTableArray(): array {
        $pivot = $this->pivotActivities();
        $table = [];
        foreach ($pivot as $key => $value) {
            $table[] = array_merge(["Date" => $key], $value);
        }
        return $table;
    }
    /**
     * Get all activities as a pivot table in HTML format. This will not be translated because the Monitor has no
     * i18n-support for performance reasons. To get a localised version, replace the Strings "Date" and "Total days"
     * with the corresponding translation.
     */
    public function activitiesToHtml (): string
    {
        // get activities as pivot table
        $pivot = $this->pivotActivities();
        $activitySums = ["init" => 0, "error" => 0, "login" => 0];
        // format activities header
        $html = "<table><tr><th>" . "Date" . "</th>";
        foreach ($activitySums as $activityType => $count)
            $html .= "<th>" . $activityType . "</th>";
        $html .= "</tr>";
        // format per day
        $days = 0;
        foreach ($pivot as $date => $types) {
            $html .= "<tr><td>" . $date . "</td>";
            foreach ($activitySums as $activityType => $count) {
                if (isset($types[$activityType])) {
                    $activitySums[$activityType] += $types[$activityType];
                    $html .= "<td>" . $count . "</td>";
                }
                else
                    $html .= "<td>-</td>";
            }
            $html .= "</tr>";
            $days ++;
        }
        // format sum
        $html .= "<tr><td><b>" . "Total days: " . $days . "</b></td>";
        foreach ($activitySums as $sum)
            $html .= "<td><b>" . $sum . "</b></td>";
        $html .= "</tr></table>";
        return $html;
    }

}