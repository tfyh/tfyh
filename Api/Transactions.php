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

namespace tfyh\api;

// TODO: normalisieren!
include_once "../../dilbo/App/DilboCronJobs.php";
use dilbo\DilboCronJobs;

use tfyh\control\LoggerSeverity;
use tfyh\control\Runner;
use tfyh\control\Sessions;
use tfyh\data\Codec;
use tfyh\data\Config;
use tfyh\data\DatabaseConnector;
use tfyh\data\Formatter;
use tfyh\data\Record;
use tfyh\util\I18n;
use tfyh\util\Language;
use tfyh\util\ListHandler;

/**
 * Class file for the handling of API transactions. The API is a simple text-based protocol, which is used to exchange
 * information on either the configuration or the database table data between the client and the server. It provides
 * functions for user authentication and session management.
 */
class Transactions
{

    private PreModificationCheck $preModificationCheck;

    /**
     * public Constructor to set the RecordHandler
     */
    public function __construct(PreModificationCheck $preModificationCheck)
    {
        $this->preModificationCheck = $preModificationCheck;
    }

    /* --------------------------------------------------------------------------------------- */
    /* ------------------ WRITE DATA TO SERVER - PUBLIC FUNCTIONS ---------------------------- */
    /* --------------------------------------------------------------------------------------- */

    /**
     * Get the last access of every API client
     */
    public static function getLastAccessesApi(): string
    {
        $dbc = DatabaseConnector::getInstance();
        $i18n = I18n::getInstance();
        $activeClients = "";
        if (!file_exists("../../var/Run/contentSize"))
            mkdir("../../var/Run/contentSize");
        $clientDirs = scandir("../../var/Run/contentSize");
        foreach ($clientDirs as $clientDir) {
            if (is_numeric($clientDir)) {
                $clientRecord = $dbc->find("persons", "user_id", $clientDir);
                if ($clientRecord !== false) {
                    $clientNames[$clientDir] = $clientRecord["first_name"] . " " .
                        $clientRecord["last_name"];
                    $activeClient = $clientNames[$clientDir] . " (#" . $clientRecord["user_id"] . ", " .
                        $clientRecord["role"] . ")";
                    if (file_exists("../../var/Run/lastReadAccess/" . $clientDir))
                        $activeClient .= ", " . $i18n->t("z1kw92|last activity:") . " " .
                            Formatter::microTimeToString(floatval(file_get_contents("../../var/Run/lastReadAccess/" . $clientDir)));
                    else
                        $activeClient .= ", " . $i18n->t("4fsAVc|last activity not known");
                } else {
                    $activeClient = $i18n->t("mwgz0K|For the still existing c...", $clientDir);
                }
                $activeClients .= $activeClient . "\n";
            }
        }
        return (strlen($activeClients) == 0) ? $i18n->t("L8CEpv|I°m afraid, there is not...") : $activeClients;
    }

    /* --------------------------------------------------------------------------------------- */
    /* ----------------------- READ DATA FROM SERVER ----------------------------------------- */
    /* --------------------------------------------------------------------------------------- */

    /**
     * Modify a record of a table using the API syntax and return the result as
     * "<resultCode>;<resultMessage>". Set the system-generated values except uid and uuid.
     * @param string $tableName the name of the table to modify
     * @param array $rowCsv The record, all values as Strings, not yet parsed.
     * @param int $mode 1 for insert, 2 for update, 3 for delete
     * @return string
     */
    public function apiModify(string $tableName, array $rowCsv, int $mode): string
    {
        $runner = Runner::getInstance();
        $userId = Sessions::getInstance()->userId();
        $modeStr = ($mode == 1) ? "insert" : (($mode == 2) ? "update" : "delete");
        if ($runner->debugOn)
            $runner->logger->log(LoggerSeverity::DEBUG, "Transactions->modify",
                "Starting modification ($modeStr) for client #$userId at table $tableName.");

        $recordItem = Config::getInstance()->getItem(".tables.$tableName");
        $record = new Record($recordItem, $this->preModificationCheck);
        $findings = $record->modify($rowCsv, $mode, Language::CSV);

        // Return response
        if (!str_starts_with($findings, "!")) {
            // return response, depending on whether a key was modified, or not.
            $lang = Config::getInstance()->language();
            if ($runner->debugOn)
                $runner->logger->log(LoggerSeverity::DEBUG, "Transactions->modify",
                    "api_$modeStr: completed. Uid '" .
                    $record->valueToDisplayByName("uid", "history", $lang));
            return ResultForTransaction::REQUEST_SUCCESSFULLY_PROCESSED->value . ";" .
                $record->valueToDisplayByName("modified", "history", $lang);
        } else {
            $runner->logger->log(LoggerSeverity::ERROR, "Transactions->modify",
                "api_$modeStr: Failed. Findings: '$findings'");
            return ResultForTransaction::TRANSACTION_FAILED->value . ";" . $findings;
        }
    }

    /**
     * Load a list of a table using the API syntax and return the result.
     * @param string $listName the name of the list to load, e.g. "changes"
     * @param array $record the record containing the parameters for the list, e.g. "set=system;modified_after=1692224000".
     * It must contain at least a value for "set, to locate the list in the configuration tree at ".lists.set.listname"
     * @return string the contents of the list, or an error message.
     */
    public function apiList(string $listName, array $record): string
    {
        $i18n = I18n::getInstance();
        if (!isset($record["set"]))
            return ResultForTransaction::TRANSACTION_FAILED->value . ";" . $i18n->t("dAwKF1|Missing name of set in r...");
        if (!Config::getInstance()->getItem(".lists")->hasChild($record["set"]))
            return ResultForTransaction::TRANSACTION_FAILED->value . ";" . $i18n->t("X6z2gz|Invalid name of set in r...");
        $modified_after = (isset($record["modified_after"])) ? floatval($record["modified_after"]) : 0;
        $list_args = ["{modified_after}" => $modified_after
        ];
        foreach ($record as $key => $value)
            $list_args["{" . $key . "}"] = $value;

        $list = new ListHandler($record["set"], $listName, $list_args);
        $list->entrySizeLimit = 10000;
        $csv = $list->getCsv("", "", "");
        return ResultForTransaction::REQUEST_SUCCESSFULLY_PROCESSED->value . ";" . $csv;
    }

    /**
     * Load a configuration branch using the API syntax and return the result.
     * @param string $listName The list name is either ".modified" to
     *  get the file modification times of the configuration files, or ".actuals" to get the actual values of the
     *  configuration items, or a relative path to a configuration file, e.g. "../../Config/packaged/app"
     * @return string the contents of the configuration file, or an error message.
     */
    public function apiCfgList(string $listName): string
    {
        $i18n = I18n::getInstance();
        $settingsFilePath = substr($listName, 1);
        $okPrefix = ResultForTransaction::REQUEST_SUCCESSFULLY_PROCESSED->value . ";";
        if ($settingsFilePath === "modified")
            return $okPrefix . Config::getModified();
        if ($settingsFilePath === "actuals") {
            $csv = "_path;_name;actual_label;actual_description;actual_value";
            Config::getInstance()->rootItem->getActualValues($csv);
            return $okPrefix . $csv;
        }
        $csv = file_get_contents("../../Config/$settingsFilePath");
        if ($csv === false)
            return ResultForTransaction::TRANSACTION_FAILED->value . ";" . $i18n->t("3cuTrK|Failed to read ../%1", $settingsFilePath);
        return $okPrefix . $csv;
    }

    /**
     * Return just an OK message, like a ping.
     */
    public function apiNop(array $record): string
    {
        // wait some time as this is also a nop function.
        $wait_for_secs = (isset($record["sleep"])) ? intval(trim($record["sleep"])) : 1;
        $wait_for_secs = ($wait_for_secs > 100) ? 100 : $wait_for_secs;
        if ($wait_for_secs > 0)
            sleep($wait_for_secs);
        return ResultForTransaction::REQUEST_SUCCESSFULLY_PROCESSED->value . ";ok.";
    }

    /**
     * Run all configured housekeeping procedures, typically once a day.
     */
    public function apiHousekeeping(): string
    {
        $runner = Runner::getInstance();
        $runner->logger->log(LoggerSeverity::INFO, "Transactions->apiHousekeeping",
            "Executing housekeeping request.");
        unlink("../../var/Log/cronJobsLastDay");
        DilboCronJobs::runDailyJobs();
        return ResultForTransaction::REQUEST_SUCCESSFULLY_PROCESSED->value . ";ok.";
    }

    /**
     * Return some configuration settings, the user information, and a session renewal token
     */
    public function apiSession(string $requestSid, string $todo): string
    {
        $runner = Runner::getInstance();
        $i18n = I18n::getInstance();

        $userId = $runner->sessions->userId();
        if (strcasecmp($todo, "start") == 0) {
            // open the api session
            $responseSid = $runner->sessions->sessionStart($userId);
            if ($responseSid === false)
                return ResultForTransaction::TRANSACTION_FAILED->value . ";" . $i18n->t("kzd3DP|Failed to start session.");
        } elseif (strcasecmp($todo, "regenerate") == 0) {
            // regenerate the api session
            $responseSid = $runner->sessions->sessionRegenerate($requestSid);
            if ($responseSid === false)
                return ResultForTransaction::TRANSACTION_FAILED->value . ";" . $i18n->t("6QCVNs|Failed to regenerate ses...");
        } elseif (strcasecmp($todo, "close") == 0) {
            // close the api session
            $runner->sessions->sessionClose($i18n->t("uecpM1|Client request."),
                $requestSid);
            return ResultForTransaction::REQUEST_SUCCESSFULLY_PROCESSED->value . ";session closed.";
        } else
            return ResultForTransaction::TRANSACTION_FAILED->value . ";" . $i18n->t("njV4BP|No valid session command...", $todo);
        // provide the new API session.
        $txResponse = ResultForTransaction::REQUEST_SUCCESSFULLY_PROCESSED->value .
            ";api_session_id=" . $runner->sessions->sessionId();
        $txResponse .= ";" . $this->apiConfiguration();
        $txResponse .= ";" . $this->apiUser();
        return $txResponse;
    }

    /**
     * Add the api configuration information to a transaction response
     */
    private function apiConfiguration(): string
    {
        $runner = Runner::getInstance();
        $config = Config::getInstance();
        $i18n = I18n::getInstance();

        $apiConfiguration = "max_session_keepalive=" . $runner->sessions->settings["max_session_keepalive"];
        $apiConfiguration .= ";max_session_duration=" . $runner->sessions->settings["max_session_duration"];

        // add the synchronisation period settings
        $synchPeriod = $config->getItem(".app.synchronisation.synch_period");
        $synchCheckPeriod = $config->getItem(".app.synchronisation.synch_check_period");
        $apiConfiguration .= ";synch_check_period=" . $synchCheckPeriod->valueCsv();
        $apiConfiguration .= ";synch_check=" . $synchPeriod->valueCsv();

        // add the server welcome message
        $username = $runner->sessions->userFullName() . " (" . $runner->sessions->userRole() . ")";
        $version = (file_exists("../../version")) ? file_get_contents("../../version") : "";
        $acronym = $config->getItem(".app.club.acronym");
        $welcome_message = "app Server of '" . $acronym->value() . "'. Version '" . $version . "'//" .
            $i18n->t("BN4UxH|Connected as") . " '" . $username . "'.";
        $apiConfiguration .= ";" . Codec::encodeCsvEntry("server_welcome_message=" . $welcome_message);
        return $apiConfiguration;
    }

    /**
     * @return string the user authorization information needed by the client.
     */
    private function apiUser(): string
    {
        $sessions = Runner::getInstance()->sessions;
        $apiUser = "user_id;first_name;last_name;uuid;role;workflows;subscriptions;concessions;preferences";
        $apiUser .= "\n" . Codec::encodeCsvEntry($sessions->userId());
        $apiUser .= ";" . Codec::encodeCsvEntry($sessions->userFirstName());
        $apiUser .= ";" . Codec::encodeCsvEntry($sessions->userLastName());
        $apiUser .= ";" . Codec::encodeCsvEntry($sessions->userUuid());
        $apiUser .= ";" . Codec::encodeCsvEntry($sessions->userRole());
        $apiUser .= ";" . Codec::encodeCsvEntry($sessions->userWorkflows());
        $apiUser .= ";" . Codec::encodeCsvEntry($sessions->userSubscriptions());
        $apiUser .= ";" . Codec::encodeCsvEntry($sessions->userConcessions());
        $apiUser .= ";" . Codec::encodeCsvEntry($sessions->userPreferences());
        return Codec::encodeCsvEntry("api_user=$apiUser");
    }
}
