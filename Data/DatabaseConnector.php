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

use Exception;
use mysqli;
use mysqli_result;

use Control\Logger;
use Control\LoggerSeverity;
use Control\Runner;
use Control\Sessions;
// the internationalisation support is needed to translate setup error messages for the admin user.
use Util\I18n;
use Util\Language;

/**
 * Class file for the DataBaseInterface class A utility class to connect to the database. It provides read and write
 * functions as well as structure modification, finding, and similar. A single instance of this class is available.
 */
class DatabaseConnector
{

    private static DatabaseConnector $instance;

    public static function getInstance(): DatabaseConnector {
        if (!isset(self::$instance))
            self::$instance = new self();
        return self::$instance;
    }

    public static function isOpen(): bool {
        return isset(self::$instance->mysqli);
    }

    private mysqli $mysqli;
    private Logger $logger;
    private bool $debugOn = false;

    private array $cfg;

    private string $changelogName;
    // the columns of the change log except the auto-incrementing "id".
    private string $changelogColumns = "`author`, `time`, `table`, `changed_uid`, `modification`";

    private string $userTableName;
    private string $userIdFieldName;
    private string $userFirstNameField;
    private string $userLastNameField;
    private string $userAdminRole;
    private string $selfRegisteredRole;
    private string $anonymousRole;
    private string $userAdminWorkflows;

    private string $history;
    private string $maxVersions;

    /**
     * Little String mix helper
     *
     * @param String $p
     * @return string
     */
    public static function swapLChars(string $p): string
    {
        $P = "";
        for ($i = 0; $i < strlen($p); $i++)
            if ((ord($p[$i]) >= 97) && (ord($p[$i]) <= 122))
                $P .= chr(219 - ord($p[$i]));
            else
                $P .= $p[$i];
        return $P;
    }


    /** Singleton pattern:
     * 1. The constructor is private to prevent direct instantiation.
     * 2. The class is static to allow access to the singleton instance from
     *    outside the class.
     * 3. The getInstance() method is public, static, and final to prevent
     *    subclassing and to prevent clients from calling the constructor
     *    with the `new` operator.
     *    Instance */
    private function __construct()
    {
        $config = Config::getInstance();
        $this->readDatabaseSettings();
        $this->logger = Runner::getInstance()->logger;

        // changelog_name, changelog_columns
        $dbiConfigItem = $config->getItem(".framework.database_connector");
        foreach ($dbiConfigItem->getChildren() as $dbiConfigItemChild)
            $dbiSettings[$dbiConfigItemChild->name()] = $dbiConfigItemChild->value();

        $this->changelogName = $dbiSettingss["changelog_name"] ?? "changes";
        $this->history = $dbiSettings["history"] ?? "history";
        $this->maxVersions = intval($dbiSettings["max_versions"] ?? 10);

        // user roles for user rights protection check,
        $usersConfigItem = $config->getItem(".framework.users");
        $this->userTableName = $usersConfigItem->getChild("user_table_name")->value() ?? "users";
        $this->userIdFieldName = $usersConfigItem->getChild("user_id_field_name")->value() ?? "user_id";
        $this->userFirstNameField = $usersConfigItem->getChild("user_firstname_field_name")->value() ?? "users";
        $this->userLastNameField = $usersConfigItem->getChild("user_lastname_field_name")->value() ?? "user_id";
        $this->userAdminRole = $usersConfigItem->getChild("useradmin_role")->value() ?? "admin";
        $this->userAdminWorkflows = $usersConfigItem->getChild("useradmin_workflows")->value() ?? "";
        $this->selfRegisteredRole = $usersConfigItem->getChild("self_registered_role")->value() ?? "anonymous";
        $this->anonymousRole = $usersConfigItem->getChild("anonymous_role")->value() ?? "anonymous";
    }

    /* ----------------------------------------------------------------- */
    /* ------------------ DATA BASE ACCESS FUNCTIONS ------------------- */
    /* ----------------------------------------------------------------- */

    private function readDatabaseSettings(): void
    {
        $this->cfg = [];
        // Read database settings
        $settingsDb = "../../Config/db";
        if (file_exists($settingsDb)) {
            // read database connection configuration first
            $cfgStrBase64 = file_get_contents($settingsDb);
            if ($cfgStrBase64)
                $this->cfg = unserialize(base64_decode($cfgStrBase64));
        }
        // Remove obfuscation
        if (isset($this->cfg["pwd"]))
            $this->cfg["pwd"] = self::swapLChars($this->cfg["pwd"]);
    }

    /**
     * ******************** CONNECTION FUNCTIONS AND GENERIC QUERY **************************
     */

    /**
     * Open the access. Provide a configuration array with "host", "name", "user", and "pwd" only to test a
     * configuration. The normal call is without argument and uses the stored access configuration.
     */
    public function open(array $dbConfiguration = null): bool|string
    {
        // do nothing if connection is open.
        if (isset($this->mysqli))
            return true;
        // this will only connect with the correct settings in the settings_db file.
        try {
            if (is_null($dbConfiguration))
                $dbConfiguration = $this->cfg;
            $this->mysqli = new mysqli($dbConfiguration["host"], $dbConfiguration["user"], $dbConfiguration["pwd"],
                $dbConfiguration["name"]);
        } catch (exception $e) {
            return I18n::getInstance()->t("R51PFL|Data base connection fai...") . " " . $e->getMessage();
        }
        if ($this->mysqli->connect_error)
            return I18n::getInstance()->t("vCGSbJ|Data base connection err...") . " " . $this->mysqli->connect_error . ".";
        $this->customQuery("SET NAMES 'UTF8'", $this);
        $ret = $this->customQuery("SELECT 1", $this);
        // cf. https://stackoverflow.com/questions/3668506/efficient-sql-test-query-or-
        // validation-query-that-will-work-across-all-or-most
        return ($ret !== false) ? true : I18n::getInstance()->t("s2Mkwj|Data base connection suc...");
    }

    /**
     * Execute an SQL query, unchecked. Shall only be called by the DatabaseConnector itself, and the framework
     * classes DatabaseSetup, ListHandler, and WordIndex. Any other class's call produces a failure.
     *
     * @param string $sql the SQL statement to execute.
     * @param mixed $caller the calling class, always to be set to "$this" by the caller.
     * @return bool|mysqli_result Returns false on failure. For successful queries which produce a result set, such
     *              as SELECT, SHOW, DESCRIBE or EXPLAIN, mysqli_query will return a mysqli_result object. For other
     *              successful queries mysqli_query will return true.
     */
    public function customQuery(string $sql, mixed $caller): bool|mysqli_result
    {
        $callerClass = get_class($caller);
        $callerClass = substr($callerClass, strrpos($callerClass, '\\') + 1);
        if ($caller !== $this) {
            $isAllowedClassName = ($callerClass == "ListHandlerKernel") || ($callerClass == "ListHandler")
                || ($callerClass == "WordIndex") || ($callerClass == "DatabaseSetup");
            if ($isAllowedClassName) {
                $this->timestampAccess(($callerClass == "DatabaseSetup"));
            } else {
                $callerClass = "forbidden";
                $this->logger->log(LoggerSeverity::ERROR, "DatabaseConnector.customQuery",
                    "Rejected execution because of invalid caller for a custom query:" . $callerClass .
                    ". Query: " . $sql);
                return false;
            }
        }
        try {
            if ($this->debugOn)
                $this->logger->log(LoggerSeverity::DEBUG, "customQuery", "executing $sql");
            $result = $this->mysqli->query($sql);
            if ($result === false)
                // nio i18n, because the error description will anyway be English
                $this->logger->log(LoggerSeverity::ERROR, "customQuery", "[FAILED] " . $this->mysqli->error);
            elseif ($this->debugOn)
                $this->logger->log(LoggerSeverity::DEBUG, "customQuery", "[OK] " . json_encode($result));
        } catch (Exception) {
            $result = false;
            $this->logger->log(LoggerSeverity::ERROR, "customQuery", "[EXCEPTION RAISED] " . $this->mysqli->error);
        }
        return $result;
    }

    public function close(): void
    {
        if ($this->mysqli->ping()) $this->mysqli->close();
    }

    /**
     * ********************** CHANGE LOG AND IMMEDIATE QUERY EXECUTION **************************
     */

    public function cleanseChangeLog(int $daysToKeep): void
    {
        // delete those which are older than $daysToKeep
        $ageLimit = strval(microtime(true) - $daysToKeep * 24 * 3600);
        $sql = sprintf("DELETE FROM `%s` WHERE `Time`<'%s'", $this->changelogName, $ageLimit);
        $cleanseResult = $this->customQuery($sql, $this);
        if ($cleanseResult === false)
            $this->logger->log(LoggerSeverity::ERROR,"cleanseChangeLog",
                "Deletion of entries failed.");
    }

    public function changelogAsHtml()
    {
        $i18n = I18n::getInstance();
        $sql = "SELECT " . $this->changelogColumns . " FROM `" . $this->changelogName .
            "` WHERE 1 ORDER BY `ID` DESC LIMIT 500";
        $res = $this->customQuery($sql, $this);
        if ($res === false)
            return "<h3>" . $i18n->t("84qqTb|Changes") . "</h3><br>" . $i18n->t("edML3Q|Error executing database...");
        elseif (intval($res->num_rows) > 0)
            $rowSql = $res->fetch_row();
        else
            return $i18n->t("SMOZBB|No changes logged.");
        // "`Author`, `Time`, `ChangedTable`, `ChangedID`, `Modification`"
        $ret = "<table><tr><th>". $i18n->t("WP2gSG|Author:") .
            "</th><th>". $i18n->t("R9sv8M|Time:") .
            "</th><th>". $i18n->t("k55LAo|Table:") .
            "</th><th>". $i18n->t("gjYPBf|Changed ID:") .
            "</th><th>". $i18n->t("B9EOmx|Description:") ."</th></tr>\n";
        while ($rowSql) {
            $timestamp = Formatter::microTimeToString(floatval($rowSql[1]));
            $ret .= "<tr><td>" . $rowSql[0] . "</b></td><td>" . $timestamp . "</td><td>" . $rowSql[2] . "</td><td>" .
                $rowSql[3] . "</td><td>" . $rowSql[4]. "</td></tr>\n";
            $rowSql = $res->fetch_row();
        }
        return "<h3>" . $i18n->t("E6z7DI|Changes") . "</h3>\n" . $ret;
    }

    /**
     * Execute the transaction. Return an empty String on success and a non-empty String on errors containing
     * the error message. For insert the inserted id is returned as integer on success.
     */
    private function executeAndLog(string $tableName, string $sql,
                                   string $changedId, string $changeEntry, bool $returnId): int|string
    {
        // debug helper
        if ($this->debugOn)
            $this->logger->log(LoggerSeverity::DEBUG, "executeAndLog", $sql);
        $this->timestampAccess(true);
        // execute SQL command. Connection must have been opened before.
        // no i18n here, error messages are anyway in English
        $ret = "";
        $res = $this->customQuery($sql, $this);
        if ($res === false) {
            $errorMessage = I18n::getInstance()->t("4YsmYP|Data base statement °%1 ...",
                Codec::htmlSpecialChars(mb_substr($sql, 0, 5000)), $this->mysqli->error);
            $ret .= $errorMessage;
            $this->logger->log(LoggerSeverity::ERROR, "executeAndLog", $errorMessage);
        } else {
            if ($returnId)
                $ret = $this->mysqli->insert_id;
            if (strlen($changedId) == 0)
                $changedId = $this->mysqli->insert_id;
            // write change log entry
            $timestamp = microtime(true);
            $sql = sprintf("INSERT INTO `%s` (%s) VALUES ('%s', %s, '%s', '%s', '%s');",
                $this->changelogName, $this->changelogColumns, Sessions::getInstance()->userId(), $timestamp,
                    $tableName, $changedId, str_replace("'", "\'",
                        str_replace("\\", "\\\\", $changeEntry)));
            if ($this->debugOn)
                $this->logger->log(LoggerSeverity::DEBUG, "executeAndLog", "adding change log entry");
            $this->customQuery($sql, $this);
        }
        return $ret;
    }

    /**
     * **************** RECORD HISTORY SUPPORT FUNCTIONS *********************
     */

    private function getVersions(string $recordHistory): array
    {
        $lines = explode("\n", $recordHistory);
        $i = 0;
        $versions = [];
        while ($i < count($lines)) {
            $version = $lines[$i];
            $i++;
            $countOfQuotes = substr_count($version, "\"");
            while (($countOfQuotes % 2 != 0) && ($i < count($lines))) {
                $version .= $lines[$i];
                $i++;
                $countOfQuotes = substr_count($version, "\"");
            }
            if (strlen(trim($version)) > 0)
                $versions[] = $version;
        }
        return $versions;
    }

    private function getDelta(array $newRecord,
                                  array $currentRecord = null): string {
        $delta = "";
        $isNullCurrentRecord = is_null($currentRecord);
        foreach ($newRecord as $fieldName => $newValue) {
            if (($fieldName != $this->history)) {
                $newStr = (is_null($newValue)) ? "" : strval($newValue);
                $currentStr = ($isNullCurrentRecord || !isset($currentRecord[$fieldName])) ? ""
                    : strval($currentRecord[$fieldName]);
                if ($newStr !== $currentStr) {
                    if (strlen($newStr) > 512)
                        $newStr = mb_substr($newStr, 0, 508) . "...";
                    $delta .= ";" . Codec::encodeCsvEntry($fieldName . ":" . $newStr);
                }
            }
        }
        return $delta;
    }

    private function updateRecordHistory(array $newRecord,
                                         array $currentRecord = null): string
    {
        // Recognise keyword to remove the history
        if (isset($newRecord[$this->history]) &&
            (strcmp($newRecord[$this->history], "REMOVE!") == 0))
            return "";

        // collect the versions, each being a simple string
        $recordVersions = [];
        $delta = $this->getDelta($newRecord, $currentRecord);
        if (!is_null($currentRecord)) {
            // The current record has no history entry yet, create version 1.
            if (!isset($currentRecord[$this->history]) ||
                (strlen($currentRecord[$this->history]) < 5)) {
                // use the new version as first one
                $newVersion = "1;" . Sessions::getInstance()->userId() . ";" . microtime(true) . $delta;
                $recordVersions = [$newVersion];
            } else {
                $recordVersions = $this->getVersions($currentRecord[$this->history]);
                // remove versions which are beyond the $maxVersions count
                if (count($recordVersions) >= $this->maxVersions)
                    $recordVersions = array_splice($recordVersions, 1 - $this->maxVersions);
            }
        }

        // add the new version if there were changes to the history array and return it.
        if (strlen($delta) > 0) {
            // find next version number
            $lastVersion = (count($recordVersions) > 0) ? $recordVersions[count($recordVersions) - 1] : "0;";
            $lastVersionNumber = intval(explode(";", $lastVersion, 2)[0]);
            $newVersion = ($lastVersionNumber + 1) . ";" . Sessions::getInstance()->userId() . ";" .
                microtime(true) . $delta;
            $recordVersions[] = $newVersion;
        }

        // compile the history String
        $recordHistory = "";
        foreach ($recordVersions as $recordVersion)
            if (strlen(trim($recordVersion)) > 0)
                $recordHistory .= $recordVersion . "\n";
        return $recordHistory;
    }

    public function getHistoryAsHtml(string|null $recordHistory, Item $recordItem, bool $includeNullValues)
    {
        $i18n = I18n::getInstance();
        // read the current history. Keep the version index. Create an empty array to insert
        if (is_null($recordHistory) || (strlen($recordHistory) == 0))
            return $i18n->t("Xn806o|No version history avail...");

        $versions = $this->getVersions($recordHistory);
        $html = "";
        foreach ($versions as $version) {
            // now interpret the version.
            $parts = explode(";", $version, 4);
            $versionNumber = intval($parts[0]);
            $author = $this->findFirst($this->userTableName,
                [$this->userIdFieldName => $parts[1]
                ]);
            $authorName = ($author !== false) ? $parts[1] . " (" .
                $author[$this->userFirstNameField] . " " .
                $author[$this->userLastNameField] . ")" : (($parts[1] ==
                "0") ? $i18n->t("KWQvhQ|Application") : $i18n->t("VCdF1p|unknown"));

            // a headline for the version table
            $timestamp = Formatter::microTimeToString(floatval($parts[2]));
            $versionHtml = "<p>" . $timestamp . " - <b>V" . $versionNumber . "</b> - " .
                $i18n->t("nOchmJ|Author") . " " . $authorName . "</p>";

            // the version table
            $fields = (isset($parts[3])) ? Codec::splitCsvRow($parts[3]) : [];
            $versionRow = [];
            foreach ($fields as $field) {
                $keyAndValue = explode(":", $field, 2);
                $versionRow[$keyAndValue[0]] = $keyAndValue[1];
            }
            $record = new Record($recordItem);
            $record->parse($versionRow, Language::SQL);
            $versionHtml .= $record->toHtmlTable(Config::getInstance()->language(), $includeNullValues);

            // reverse version order
            $html = $versionHtml . $html;
        }
        return $html;
    }

    /**
     * **************** DB WRITE ACCESS SUPPORT FUNCTIONS *********************
     */

    /**
     * Prevent from manipulation of the user role and workflows
     */
    private function protectUserRights(string $tableName, array $record): array|string
    {
        $i18n = I18n::getInstance();
        if (strcasecmp($tableName, $this->userTableName) != 0)
            // this is no user data table: OK.
            return $record;

        // allowed: the very first user insertion
        $usersCount = $this->countRecords($this->userTableName);
        if ($usersCount == 0)
            // the very first user must get the permission to be inserted to be able to administer the app
            return $record;

        // allowed: the session user is admin
        $sessions = Sessions::getInstance();
        $userRole = $sessions->userRole();
        if (strcasecmp($userRole, $this->userAdminRole) == 0)
            // user has user administration privilege: OK.
            return $record;

        $userWorkflows = intval($sessions->userWorkflows());
        // allowed: the session user has appropriate workflow allowance
        foreach (explode(",", $this->userAdminWorkflows) as $allowedWorkflow)
            if (($userWorkflows & intval($allowedWorkflow)) > 0)
                return $record;

        // forbidden unknown affected user's ID
        if (!isset($record[$this->userIdFieldName]))
            return $i18n->t("IvtKey|A user record must not b...");
        $recordUserId = $record[$this->userIdFieldName];
        $sessionUserId = $sessions->userId();
        $existingUser = DatabaseConnector::getInstance()->find($this->userTableName,$this->userIdFieldName, $recordUserId);

        // insertion of users
        if ($existingUser === false) {
            if (!isset($record["role"]))
                return $i18n->t("0ICPQx|A user record must set t...");
            $recordRole = $record["role"];
            // self-registration case with self-registration or anonymous role and neither workflows nor concessions
            if ((strcmp($this->selfRegisteredRole, "forbidden") != 0) && // transaction is not forbidden
                ((strcasecmp($recordRole, $this->anonymousRole) == 0) ||
                    (strcasecmp($recordRole, $this->selfRegisteredRole) == 0)) &&  // role is anonymous
                (intval($record["workflows"]) == 0) && (intval($record["concessions"]) == 0)) // no workflows, no concessions
                return $record;
            else
                return $i18n->t("lDSTHw|Someone tried to create ...", $this->selfRegisteredRole);
        }

        // in update and delete cases the user must not modify another user at all.
        elseif ($recordUserId != $sessionUserId)
            return $i18n->t("WdiaX5|User %1 tried to modify ...",
                $sessionUserId, $recordUserId);

        // and for its own rights it must not change the role, workflows, concessions
        else {
            if (isset($record["role"]) && (strcasecmp($record["role"], $userRole) != 0))
                return $i18n->t("gI7hvr|User tried to modify own...");
            if (intval($record["Workflows"]) != intval($sessions->userWorkflows()))
                return $i18n->t("4RIp1F|User tried to modify own...");
            if (intval($record["Concessions"]) != intval($sessions->userConcessions()))
                return $i18n->t("T5N6rh|User tried to modify own...");
        }

        // All checks passed: OK.
        return $record;
    }

    /**
     * Little helper to create the "WHERE" - clause to match the $matching keys.
     */
    private function sqlWhereClause(string $tableName, array $matching, string $condition): string
    {
        if (strlen($condition) == 0)
            return "WHERE 1";
        $whereClause = "WHERE ";
        $conditions = explode(",", $condition);
        $c = 0;
        foreach ($matching as $key => $value) {
            if (strcasecmp($conditions[$c], "NULL") == 0) {
                $whereClause .= "`" . $tableName . "`.`" . $key . "` IS NULL AND ";
            } else
                if (strcasecmp($conditions[$c], "IN") == 0) {
                    $whereClause .= "`" . $tableName . "`.`" . $key . "` IN (" . $value . ") AND ";
                } else
                    $whereClause .= "`" . $tableName . "`.`" . $key . "` " . $conditions[$c] . " '" .
                        $value . "' AND ";
            if ($c < count($conditions) - 1)
                $c++;
        }
        if (strlen($whereClause) == strlen("WHERE "))
            return "WHERE 1";
        return mb_substr($whereClause, 0, mb_strlen($whereClause) - 5);
    }

    public function timestampAccess(bool $isWrite): void
    {
        $userId = Sessions::getInstance()->userId();
        if ($userId > 0) {
            $timestamp = strval(microtime(true));
            $directory = ($isWrite) ? "lastWriteAccess" : "lastReadAccess";
            file_put_contents("../../var/Run/$directory/" . Sessions::getInstance()->userId(), $timestamp);
            file_put_contents("../../var/Run/$directory/any", $timestamp);
        }
    }

    /**
     * **************** STANDARD DB WRITE QUERIES: INSERT; UPDATE; DELETE *********************
     */

    /**
     * Insert the record. Return the inserted id as integer on success and a non-empty String on errors containing
     * the error message.
     */
    public function insertInto(string $tableName, array $record): int|string
    {
        // protect user records from being changed by anyone except the user itself or user admin
        $record = $this->protectUserRights($tableName, $record);
        if (!is_array($record)) return $record;

        // initialise history data field, if applicable
        $record[$this->history] = $this->updateRecordHistory($record);

        // create the sql command and the change log entry
        $sql = sprintf("INSERT INTO `%s` (`", $tableName);
        $changeEntry = "inserted: "; // Technical term, no i18n
        foreach ($record as $key => $value) {
            $sql .= $key . "`, `";
            // no change logging for the record history to avoid redundant information
            if ($key != $this->history)
                // No quote escaping in the change log entry. This will be handled in executeAndLog().
                $changeEntry .= $key . '="' . $value . '", ';
        }
        // cut off last ", `" or ", "
        $sql = mb_substr($sql, 0, mb_strlen($sql) - 3);
        $changeEntry = mb_substr($changeEntry, 0, mb_strlen($changeEntry) - 2);
        $sql .= ") VALUES ('";
        foreach ($record as $value) {
            if (is_null($value))
                $sql = mb_substr($sql, 0, mb_strlen($sql) - 1) . "NULL, '";
            else
                $sql .= str_replace("'", "\'", str_replace("\\", "\\\\", $value)) . "', '";
        }
        // cut off last ", "
        $sql = mb_substr($sql, 0, mb_strlen($sql) - 3);
        $sql .= ")";

        // execute sql command and log execution.
        $changedId = (isset($record["uid"])) ? $record["uid"] : "";
        return $this->executeAndLog($tableName, $sql, $changedId, $changeEntry, true);
    }

    /**
     * Update the record. Return an empty String on success and a non-empty String on errors containing
     * the error message.
     */
    public function update(string $tableName, string $keyFieldName, array $newRecord): array|string
    {
        // protect user records from being changed by anyone except the user itself or user admin
        $newRecord = $this->protectUserRights($tableName, $newRecord);
        if (!is_array($newRecord)) return $newRecord;

        // update history data field
        $matching = [$keyFieldName => $newRecord[$keyFieldName]];
        $currentRecord = $this->findFirst($tableName, $matching);
        if ($currentRecord === false)
            return I18n::getInstance()->t("EvZXbc|Error updating record in...", $tableName) . " $keyFieldName = " . $newRecord[$keyFieldName];
        $newRecord[$this->history] = $this->updateRecordHistory($newRecord, $currentRecord);

        // create SQL command and change log entry.
        $sql = "UPDATE `" . $tableName . "` SET ";
        $changeEntry = "updated: "; // Technical term, no i18n
        foreach ($newRecord as $fieldName => $value) {
            // check empty values. 1a. If previous and current are empty, skip the field.
            $skipUpdate = (!isset($currentRecord[$fieldName]) || (strlen($currentRecord[$fieldName]) == 0)) && (!isset($value) ||
                    (strlen($value) == 0));
            // check mismatching fields. 1b. If the new record has an extra field, drop it.
            $skipUpdate = $skipUpdate || !array_key_exists($fieldName, $currentRecord);
            // skip the key
            $skipUpdate = $skipUpdate || (strcasecmp($fieldName, $keyFieldName) == 0);
            if (!$skipUpdate) {
                if (is_null($value))
                    $sql .= "`" . $fieldName . "` = NULL,";
                elseif ((strlen($value) == 0) &&
                    (is_numeric($currentRecord[$fieldName]) || is_numeric(strtotime($currentRecord[$fieldName]))))
                    // new record with empty numeric values. Use NULL as a value instead
                    $sql .= "`" . $fieldName . "` = NULL,";
                else
                    $sql .= "`" . $fieldName . "` = '" . str_replace("'", "\'",
                            str_replace("\\", "\\\\", $value)) . "',";
            }
            // the change entry shall neither contain the keys nor the record history to
            // prevent from too much redundant information
            if (!$skipUpdate && (strcmp($value, $currentRecord[$fieldName]) !== 0) && ($fieldName != $this->history))
                // No quote escaping in the change log entry. This will be handled in
                // executeAndLog().
                $changeEntry .= $fieldName . ': "' . $currentRecord[$fieldName] . '"=>"' . $value . '", ';
        }

        $sql = mb_substr($sql, 0, mb_strlen($sql) - 1);
        $changeEntry = mb_substr($changeEntry, 0, mb_strlen($changeEntry) - 2);
        $sql .= " " . $this->sqlWhereClause($tableName, $matching, "=");

        // execute sql command and log execution.
        return $this->executeAndLog($tableName, $sql, $newRecord[$keyFieldName], $changeEntry, false);
    }

    /**
     * Delete the record. Return an empty String on success and a non-empty String on errors containing
     * the error message.
     */
    public function delete(string $tableName, array $matching): string
    {
        $i18n = I18n::getInstance();
        // get previous record to log change
        $previousRecord = $this->findFirst($tableName, $matching);
        if ($previousRecord === false)
            return $i18n->t("S9MHHT|Record to delete was not...");

        // protect user records from being deleted by anyone except the user admin
        if (strcasecmp($tableName, $this->userTableName) == 0) {
            $this->protectUserRights($tableName, $previousRecord); // result is ignored, the record will be deleted anyway
            if ($this->countRecords($this->userTableName) == 1)
                return $i18n->t("rGyRdy|The very last user must ...");
        }

        // create change log entry and SQL command.
        // No quote escaping in the change log entry. This will be handled in
        // executeAndLog().
        $changeEntry = "deleted: "; // technical term, no i18n
        foreach ($previousRecord as $key => $value) {
            $changeEntry .= $key . "='" . $value . "', ";
        }
        // deletions will not change the last modified time stamp, because they
        // delete the data anyway.
        $changeEntry = mb_substr($changeEntry, 0, mb_strlen($changeEntry) - 2);
        // ID used is **ID**
        $sql = "DELETE FROM `" . $tableName . "` " .
            $this->sqlWhereClause($tableName, $matching, "=");

        // execute sql command and log execution.
        $key = array_key_first($matching);
        return $this->executeAndLog($tableName, $sql, $key, $changeEntry, false);
    }

    /**
     * ************************ SELECT (FIND) RECORDS *****************************
     */

    public function find(string $tableName, string $key, string $value): array|bool
    {
        return $this->findFirst($tableName, [$key => $value
        ]);
    }

    public function findFirst(string $tableName, array $matching): array|bool
    {
        $sets = $this->findAll($tableName, $matching, 1);
        if (($sets === false) || (count($sets) == 0))
            return false;
        return $sets[0];
    }

    public function findAll(string $tableName, array $matching, int $maxRows): bool|array
    {
        return $this->findAllSorted($tableName, $matching, $maxRows,
            ((count($matching) == 0) ? "" : "="), "", true);
    }

    public function findAllSorted(string $tableName, array $matching, int $maxRows,
                                  string $condition, string $sortKey, bool $ascending,
                                  int $startAtRow = 0): array|bool
    {
        // compile command parts: columns to choose
        $columnNames = $this->columnNames($tableName);
        $columnIndicators = "";
        foreach ($columnNames as $col_name)
            $columnIndicators .= "`" . $col_name . "`, ";
        if (strlen($columnIndicators) == 0)
            $columnIndicators = "*";
        else
            $columnIndicators = mb_substr($columnIndicators, 0, mb_strlen($columnIndicators) - 2);
        // compile command parts: rows to choose
        $whereString = (strlen($condition) == 0) ? 'WHERE 1 ' : $this->sqlWhereClause($tableName,
            $matching, $condition);
        // compile command parts: sorting of result
        $sortStr = "";
        if ($sortKey && strlen($sortKey) > 0) {
            $sortWay = ($ascending) ? "ASC" : "DESC";
            $sortColumns = explode(",", $sortKey);
            $sortStr = " ORDER BY ";
            foreach ($sortColumns as $sortColumn) {
                if (str_starts_with($sortColumn, '#'))
                    $sortStr .= "CAST(`" . $tableName . "`.`" . substr($sortColumn, 1) . "` AS UNSIGNED) " .
                        $sortWay . ", ";
                else
                    $sortStr .= "`" . $tableName . "`.`" . $sortColumn . "` " . $sortWay . ", ";
            }
            $sortStr = mb_substr($sortStr, 0, mb_strlen($sortStr) - 2);
        }
        // compile command parts: limit or chunk of returned rows
        $limitString = " LIMIT " . $startAtRow . "," . $maxRows;

        // compile command and execute
        $sql = "SELECT " . $columnIndicators . " FROM `" . $tableName . "` " . $whereString . $sortStr .
            $limitString;
        $res = $this->customQuery($sql, $this);
        $rowsSql = [];
        $nRows = 0;
        if (($res !== false) && (intval($res->num_rows) > 0)) {
            $rowSql = $res->fetch_row();
            while (($rowSql) && ($nRows < $maxRows)) {
                $rowsSql[] = $rowSql;
                $nRows++;
                $rowSql = $res->fetch_row();
            }
        } else
            return false;

        // TODO permissions check

        // add column names to build an associative array
        $columnNames = $this->columnNames($tableName);
        $i = 0;
        $sets = [];
        foreach ($columnNames as $columnName) {
            for ($r = 0; $r < $nRows; $r++) {
                if (!isset($sets[$r])) $sets[$r] = [];
                $sets[$r][$columnName] = $rowsSql[$r][$i];
            }
            $i++;
        }
        // log the read access and return
        $this->timestampAccess(false);
        return $sets;
    }

    public function countRecords(string $tableName, array $matching = null, string $condition = ""): int
    {
        // now retrieve all column names
        $sql = ($matching == null) ?
            sprintf("SELECT COUNT(*) FROM `%s`;", $tableName) :
            sprintf("SELECT COUNT(*) FROM `%s` %s", $tableName, $this->sqlWhereClause($tableName, $matching, $condition));
        $res = $this->customQuery($sql, $this);
        $count = 0;
        if (is_object($res) && intval($res->num_rows) > 0) {
            $row = $res->fetch_row();
            $count = intval($row[0]);
        }
        return $count;
    }

    /**
     * *************************** GET STRUCTURE INFORMATION *****************************
     */

    public function dbName(): string { return $this->cfg["name"]; }
    public function pwLength(): int { return strlen($this->cfg["pwd"]); }
    public function historyName(): string { return $this->history; }

    public function serverInfo(): string
    {
        return "Client info = " . $this->mysqli->client_info . ", Server info = " . $this->mysqli->server_info .
            ", Server version = " . $this->mysqli->server_version;
    }

    public function getError(): string
    {
        return $this->mysqli->error;
    }

    public function columnNames(string $tableName): array
    {
        // Retrieve all column names
        $sql = sprintf("SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA`='%s' AND `TABLE_NAME`='%s' ORDER BY ORDINAL_POSITION",
            $this->dbName(), $tableName);

        $result = $this->customQuery($sql, $this);
        $ret = [];
        if (!is_array($result) && !is_object($result))
            return $ret;
        // put all values to the array, with numeric auto-incrementing key.
        $columnNames = $result->fetch_array();
        while ($columnNames) {
            // the fetch_array function is an iterator, returning an array with
            // the column name
            // always being at pos 0
            $ret[] = $columnNames[0];
            $columnNames = $result->fetch_array();
        }
        return $ret;
    }

    /**
     * Get all column types by ordinal position as an array with $array[n] = n. column's type.
     *
     * @param String $tableName
     *            the name of the table to be used.
     * @return array numbered array of column types including size (if not 0) like varchar(192) or false, if
     *         database connection fails.
     */
    public function columnTypes(string $tableName): array
    {
        // now retrieve all column names
        $sql = sprintf("SELECT `DATA_TYPE`, `CHARACTER_MAXIMUM_LENGTH` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA`='%s' AND `TABLE_NAME`='%s' ORDER BY ORDINAL_POSITION",
            $this->dbName(), $tableName);
        $res = $this->customQuery($sql, $this);
        $ret = [];
        if (!is_array($res) && !is_object($res))
            return $ret;
        // put all values to the array, with numeric auto-incrementing key.
        $columnTypes = $res->fetch_array();
        while ($columnTypes) {
            // the fetch_array function is an iterator
            $ret[] = (strlen($columnTypes[1]) > 0) ? $columnTypes[0] . " (" . $columnTypes[1] . ")" : $columnTypes[0];
            $columnTypes = $res->fetch_array();
        }
        return $ret;
    }

    /**
     * Get all index columns as a name array. Set include description to add things like "UNIQUE" key name.
     */
    public function indexes(string $tableName, bool $includeDescription): array
    {
        $indexResponseColumns = ["Table", "Non_unique", "Key_name", "Seq_in_index", "Column_name",
            "Collation", "Cardinality", "Sub_part", "Packed", "Null", "Index_type", "Comment", "Index_comment",
            "Visible", "Expression"
        ];

        // Unique and nullable property
        $indexRelevantColumns = ["Non_unique", "Key_name", "Column_name", "Null"
        ];
        $sql = "SHOW KEYS FROM `" . $tableName . "`";
        $indexes = [];
        $res = $this->customQuery($sql, $this);
        if (!is_array($res) && !is_object($res))
            return $indexes;
        if ($res->num_rows > 0) {
            $row = $res->fetch_row();
            while ($row) {
                $c = 0;
                $index = [];
                foreach ($indexResponseColumns as $indexResponseColumn) {
                    if (in_array($indexResponseColumn, $indexRelevantColumns))
                        $index[$indexResponseColumn] = $row[$c];
                    $c++;
                }
                $indexDescription = " ";
                if (isset($index["Non_unique"]) && (intval($index["Non_unique"]) === 0))
                    $indexDescription .= " : UNIQUE ";
                if (isset($index["Key_name"]) && $includeDescription)
                    $indexes[$index["Key_name"]] = $indexDescription;
                else
                    $indexes[] = $index["Key_name"];
                $row = $res->fetch_row();
            }
        }
        return $indexes;
    }

    private function filterColumns(string $sql): array {
        $columns = [];
        $res = $this->customQuery($sql, $this);
        if (!is_array($res) && !is_object($res))
            return $columns;
        if ($res->num_rows > 0) {
            $row = $res->fetch_row();
            while ($row) {
                $columns[$row[3]] = $row[15] . ", " . $row[16] . ", " . $row[18];
                $row = $res->fetch_row();
            }
        }
        return $columns;
    }

    /**
     * Get all not null columns as an array with $array[columnName] = description.
     */
    public function columnsNotNull(string $tableName): array
    {
        // NOT NULL property
        $sql = sprintf("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '%s' AND IS_NULLABLE = 'NO'",
            $tableName);
        return $this->filterColumns($sql);
    }

    /**
     * Get all auto-increments as an array with $array[columnName] = description.
     */
    public function autoIncrementColumns(string $tableName): array
    {
        $sql = sprintf("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '%s' AND EXTRA LIKE '%%auto_increment%%'", $tableName);
        return $this->filterColumns($sql);
    }

    /**
     * Get all available table names.
     *
     * @return array of table names or false, if database connection fails.
     */
    public function tableNames(): array
    {
        $sql = sprintf("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA='%s' ",
            $this->dbName());
        $res = $this->customQuery($sql, $this);
        // put all values to the array, the column name being the key.
        $ret = [];
        $row = $res->fetch_row();
        while ($row) {
            // the fetch_row function is an iterator, returning an array with
            // the table name always being at pos 0
            $ret[] = $row[0];
            $row = $res->fetch_row();
        }
        $res->free();
        return $ret;
    }

    /**
     * Get the size in kB of all tables of the database.
     *
     * @return array the tables size as associative array, sorted by largest first.
     */
    public function tableSizesKiloBytes(): array
    {
        $sql = sprintf("SELECT TABLE_NAME AS `Table`, ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024) AS `Size (kB)` FROM information_schema.TABLES WHERE TABLE_SCHEMA = '%s' ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;",
            $this->dbName());
        $res = $this->customQuery($sql, $this);
        // put all values to the array, the column name being the key.
        $ret = [];
        $row = $res->fetch_row();
        while ($row) {
            // the fetch_row function is an iterator, returning an array with
            // the table name always being at pos 0
            $ret[$row[0]] = $row[1];
            $row = $res->fetch_row();
        }
        $res->free();
        foreach ($ret as $tableName => $tableSize) {
            $columnNames = $this->columnNames($tableName);
            $columnTypes = $this->columnTypes($tableName);
            $totalBlobSizeKiloBytes = 0;
            for ($c = 0; $c < count($columnNames); $c++) {
                if (str_contains(strtolower($columnTypes[$c]), "text")) {
                    $sql = "SELECT SUM(OCTET_LENGTH(`" . $columnNames[$c] .
                        "`)) AS TOTAL_SIZE FROM `$tableName`";
                    $res = $this->customQuery($sql, $this);
                    $row = $res->fetch_row();
                    $columnSize = intval($row[0] / 1024);
                    $totalBlobSizeKiloBytes += $columnSize;
                }
            }
            $ret[$tableName] += $totalBlobSizeKiloBytes;
        }
        return $ret;
    }
}
