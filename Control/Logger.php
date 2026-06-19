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

use DateTimeImmutable;
use DateTimeZone;
use Exception;

use tfyh\data\Codec;
use tfyh\data\Formatter;
use tfyh\data\ParserName;
use const tfyh\data\DEFAULT_TIME_ZONE;
use tfyh\util\FileHandler;
use tfyh\util\Language;

/**
 * Log file size limit. If exceeded, it is copied to a previous log file, and a new log file is created.
 */
const LOG_FILE_SIZE = 250_000;

/**
 * A basic logging capability for the application. After exceeding the log file size limit, log files are copied to a
 * previous log, which is overwritten, and a new log file is created.
 */
class Logger
{
    private String $filePath;
    private Language $language = Language::EN;
    private DateTimeZone $timeZone;

    public function __construct(String $filePath) {
        $this->filePath = $filePath;
        if (! file_exists($filePath)) {
            $logDir = substr($filePath, 0, strrpos($filePath, "/"));
            if (!file_exists($logDir))
                mkdir($logDir, 0777, true);
        }
        // do not Use DEFAULT_TIME_ZONE constant to avoid the need for the data/config loading here.
        $this->timeZone = new DateTimeZone("Europe/Berlin");
    }

    public function setLocale(Language $language, DateTimeZone $timeZone = null): void
    {
        $this->language = $language;
        $this->timeZone = (is_null($timeZone)) ? new DateTimeZone(DEFAULT_TIME_ZONE) : $timeZone;
    }

    public static function rotateIfNeeded(string $filename, string $headline): void {
        if (filesize($filename) > LOG_FILE_SIZE) {
            rename($filename, $filename . ".previous");
            file_put_contents($filename, "$headline\n");
        }
    }

    /**
     * Log an event.
     * @param LoggerSeverity $severity the severity of the logged event.
     * @param String $caller the caller of the logging function.
     * @param String $message the message to log.
     * @return void
     */
    public function log(LoggerSeverity $severity, String $caller, String $message): void
    {
        try {
            $now = new DateTimeImmutable("now", $this->timeZone);
            $logLine = Formatter::format($now, ParserName::DATETIME, $this->language);
        } catch (Exception) {
            $logLine = "date-and-time";
        }
        $logLine .= ";" . microtime(true) . ";" . $severity->name . ";$caller;" . Codec::encodeCsvEntry($message);
        self::rotateIfNeeded($this->filePath, "datetime;timestamp;severity;caller;message");
        file_put_contents($this->filePath, $logLine . "\n", FILE_APPEND);
    }

    /**
     * Zip all logs into the monitoring report. Returns the monitoring report file name "logs.zip", which sits in the
     * "../../var/Log/" directory
     * @param array $loggerNames the names of the log-files to zip
     * @return string the name of the zip file
     */
    public static function zipLogs (array $loggerNames): string
    {
        $logNames = [];
        $cwd = getcwd();
        chdir("../../var/Log");
        foreach ($loggerNames as $loggerName) {
            if (file_exists($loggerName . ".log"))
                $logNames[] = $loggerName . ".log";
            if (file_exists($loggerName . ".log.previous"))
                $logNames[] = $loggerName . ".log.previous";
        }
        FileHandler::zipFiles($logNames, "logs.zip");
        chdir($cwd);
        return "logs.zip";
    }

}