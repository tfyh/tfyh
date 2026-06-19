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

use tfyh\data\Codec;
use tfyh\data\Audit;

/**
 * Static class container file for a daily jobs routine. It may be triggered by whatever, and then checks whether it was
 * already run this day, and if not, starts the sequence.
 */
class CronJobs
{

    /**
     * run all daily jobs.
     */
    public static function runDailyJobs(): bool
    {
        // Check whether a day went by.
        $timeLastRun = (file_exists("../../var/Log/cronJobsLastDay")) ? file_get_contents(
            "../../var/Log/cronJobsLastDay") : 0;
        $today = date("Y-m-d");
        if (strcmp($timeLastRun, $today) == 0)
            return false;

        $cronLog = "../../var/Log/cronJobs.log";
        $cronStarted = time();
        $lastStepEnded = $cronStarted;
        file_put_contents($cronLog,
            date("Y-m-d H:i:s") . " +0: Cron jobs were started (time last run: $timeLastRun)\n",
            FILE_APPEND);

        // remove obsolete files in log directory from previous program versions or debug runs
        $monitor = Monitor::getInstance();
        $activitiesToTableArray = $monitor->activitiesToTableArray();
        $runner = Runner::getInstance();
        // "../../var/Log/tmp" is the usual test file name. Maybe some remainder is still there.
        if (file_exists("../../var/Log/tmp"))
            unlink("../../var/Log/tmp");
        file_put_contents("../../var/Run/activities.csv", Codec::encodeCsvTable($activitiesToTableArray));
        file_put_contents($cronLog,
            date("Y-m-d H:i:s") . " +" . (time() - $lastStepEnded) .
            ": Log rotation and analysis completed\n", FILE_APPEND);
        $lastStepEnded = time();

        // refresh timer as first action, to avoid duplicate triggering by
        // different users.
        file_put_contents("../../var/Log/cronJobsLastDay", $today);
        $runner->logger->log(LoggerSeverity::INFO, "CronJobs->runDailyJobs", "Starting daily jobs.");

        // check for updates
        file_put_contents("../../var/Log/currentApplicationVersion",
            $runner->getCurrentApplicationVersion("CronJobs.php"));

        // run audit
        $audit = new Audit();
        $audit->runAudit();
        file_put_contents($cronLog,
            date("Y-m-d H:i:s") . " +" . (time() - $lastStepEnded) . ": " . "Audit completed\n", FILE_APPEND);

        $runner->logger->log(LoggerSeverity::INFO, "CronJobs->runDailyJobs", "Daily jobs completed.");
        return true;
    }
}
