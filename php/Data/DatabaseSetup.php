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

use Control\LoggerSeverity;
use Control\Runner;
use Control\Sessions;
use Control\Users;
// internationalisation support needed to translate setup error messages for the admin user.
use Util\I18n;
use Util\Language;

/**
 * Class file to adjust the database layout to the version required by the current configuration.
 */
class DatabaseSetup
{
    /**
     * Log path for specific database audit logging
     */
    private static string $dbAuditLog = "../../var/Log/sys_db_audit.log";

    public static string $sqlAddColumn = "ALTER TABLE `{table}` ADD `{column}` {type} NOT NULL {default};";
    public static string $sqlAddUnique = "ALTER TABLE `{table}` ADD UNIQUE(`{column}`";
    public static string $sqlAddAutoincrement = "ALTER TABLE `{table}` MODIFY `{column}` INT UNSIGNED NOT NULL AUTO_INCREMENT";
    public static string $sqlChangeColumn = "ALTER TABLE `{table}` CHANGE `{column}` `{column}` {type} NOT NULL {default};";
    public static string $adjustNullColumnStep1 = "UPDATE `{table}` SET `{column}`={default} WHERE `{column}` IS NULL;";
    public static string $adjustNullColumnStep2 = "ALTER TABLE `{table}` CHANGE `{column}` `{column}` {type} NOT NULL;";


    /**
     * The database permissions cache, to be read from the .appTables configuration. Fields will be filled on
     * demand.
     */
    private static array $columnWritePermissions = [];
    private static array $columnReadPermissions = [];
    private static array $recordWritePermissions = [];
    private static array $recordReadPermissions = [];

    /**
     * Read the permissions for a specific column from the cache or from the configuration and fill the cache.
     * @param string $tableName The name of the table to read the permissions for.
     * @param string $columnName The name of the column to read the permissions for.
     * @param bool $writePermissions Whether to read the write permissions or the read permissions.
     * @return string The permissions for the column.
     */
    private function getColumnPermissions(string $tableName, string $columnName, bool $writePermissions = true): string
    {
        // read cache, if existing
        if (!isset(self::$columnWritePermissions[$tableName]))
            self::$columnWritePermissions[$tableName] = [];
        elseif (isset(self::$columnWritePermissions[$tableName][$columnName]))
            return self::$columnWritePermissions[$tableName][$columnName];
        // if not, fill cache
        $columnItem = Config::getInstance()->getItem(".tables.$tableName.$columnName");
        self::$columnWritePermissions[$tableName][$columnName] = $columnItem->nodeWritePermissions();
        self::$columnReadPermissions[$tableName][$columnName] = $columnItem->nodeReadPermissions();
        return ($writePermissions) ?
            self::$columnWritePermissions[$tableName][$columnName] :
            self::$columnReadPermissions[$tableName][$columnName];
    }

    /**
     * Read the maximum permissions for a table record from the cache or from the configuration and fill the
     * cache.
     * @param string $tableName The name of the table to read the permissions for.
     * @param bool $writePermissions Whether to read the write permissions or the read permissions.
     * @return string The maximum of allowance for the table record fields.
     */
    private function getRecordPermissions(string $tableName, bool $writePermissions = true): string
    {
        // read cache, if existing
        if ($writePermissions && isset(self::$recordWritePermissions[$tableName]))
            return self::$recordWritePermissions[$tableName];
        if (!$writePermissions && isset(self::$recordReadPermissions[$tableName]))
            return self::$recordReadPermissions[$tableName];

        // if not, fill cache
        self::$recordWritePermissions[$tableName] = "";
        self::$recordReadPermissions[$tableName] = "";
        $tableItem = Config::getInstance()->getItem(".tables.$tableName");
        if (! $tableItem->isValid())
            return "";
        $recordWritePermissions = [];
        $recordReadPermissions = [];
        foreach ($tableItem->getChildren() as $child) {
            $columnWritePermissions = explode(",", $child->nodeWritePermissions());
            $columnReadPermissions = explode(",", $child->nodeReadPermissions());
            foreach ($columnWritePermissions as $columnWritePermission)
                if (!in_array($columnWritePermission, $recordWritePermissions))
                    $recordWritePermissions[] = $columnWritePermission;
            foreach ($columnReadPermissions as $columnReadPermission)
                if (!in_array($columnReadPermission, $recordReadPermissions))
                    $recordReadPermissions[] = $columnReadPermission;
        }
        self::$recordWritePermissions[$tableName] = implode(",", $recordWritePermissions);
        self::$recordReadPermissions[$tableName] = implode(",", $recordReadPermissions);
        return ($writePermissions) ?
            self::$recordWritePermissions[$tableName] :
            self::$recordReadPermissions[$tableName];
    }

    /**
     * Check whether the $permission String allows the requested access
     * @param string $tableName The name of the table to check the permissions for.
     * @param string $columnName The name of the column to check the permissions for.
     * @param bool $forWrite Whether to check the write permissions or the read permissions.
     * @return bool Whether the user is allowed to access the table column.
     */
    public function isAllowedField(string $tableName, string $columnName, bool $forWrite = true): bool
    {
        $permissions = $this->getColumnPermissions($tableName, $columnName, $forWrite);
        $users = Users::getInstance();
        return $users->isAllowedItem($permissions);
    }

    /**
     * Check whether the $permission String allows the request access for any of the fields of the record.
     * @param string $tableName The name of the table to check the permissions for.
     * @param bool $forWrite Whether to check the write permissions or the read permissions.
     * @return bool Whether the user is allowed to access the table column.
     */
    public function isAllowedRecord(string $tableName, bool $forWrite): bool
    {
        $permissions = $this->getRecordPermissions($tableName, $forWrite);
        $users = Users::getInstance();
        return $users->isAllowedItem($permissions);
    }

    /**
     * Empty constructor.
     */
    public function __construct() {}

    /**
     * Resolve a reference value into the correct record or Item. If, e.g. the user id is part of a record, the
     * respective user record is returned for the given $valueStr. If the reference is a configuration item, the item is
     * returned.
     * @param string $tableName The name of the table to resolve the reference for.
     * @param string $columnName The name of the column to resolve the reference for.
     * @param string $valueStr The value to resolve the reference for.
     * @return Item|array|null The resolved record or Item or null if the reference could not be resolved,
     * or the column does not contain a reference field.
     */
    public function resolve(string $tableName, string $columnName, string $valueStr): null|Item|array
    {
        $config = Config::getInstance();
        $columnItem = $config->getItem(".tables.$tableName.$columnName");
        if (! $columnItem->isValid())
            return null; // no column configuration
        $valueReference = $columnItem->valueReference();
        if (strlen($valueReference) == 0)
            return null; // no reference to another object
        if (str_starts_with($valueReference, ".")) {
            // if the reference starts with a ".", it refers to a configuration item.
            return $config->getItem($valueReference . "." . $valueStr);
        } else {
            // else it refers to a table record's field.
            $reference = explode(".", $valueReference);
            return DatabaseConnector::getInstance()->find($reference[0], $reference[1], $valueStr);
        }
    }

    /**
     * Check whether a person belongs to the owners of a record. This includes the lookup of the user's id
     * @param array $record The record to check the ownership for. All columns containing a field with the
     * owner handling flag set will be checked.
     * @param string $tableName The name of the table to check the ownership for.
     * @param array $ownerIds The ids of the owners to check the record against.
     * @return bool Whether the user is an owner of the record.
     */
    public function owns(array $record, string $tableName, array $ownerIds): bool
    {
        $config = Config::getInstance();
        $tableItem = $config->getItem(".tables.$tableName");
        foreach ($tableItem->getChildren() as $child) {
            if (!str_starts_with($child->name(), "_")) {
                // iterate through all table columns to look for owner fields
                $handling = $child->columnHandling();
                if (str_contains($handling, "o")) {
                    // check whether the respective owner field has any value at all
                    if (isset($record[$child->name()]) && (strlen($record[$child->name()]) > 0)) {
                        // Maybe a list (e.g. a crew). Split the list or convert the entry to array
                        $isList = (str_contains($handling, "l"));
                        $ownerFieldValues = ($isList) ?
                            Codec::splitCsvRow($record[$child->name], "|") :
                            array($record[$child->name()]);
                        // now check whether any of these entries matches the possible owner DilboIds
                        foreach ($ownerFieldValues as $ownerFieldValue)
                            if (in_array($ownerFieldValue, $ownerIds))
                                // it is enough to find one match
                                return true;
                    }
                }
            }
        }
        // no match found
        return false;
    }

    /**
     * Insert the default admin record for user id 1142. The user table must be empty for that. If not, the
     * function will return without doing anything.
     */
    private function insertFirstUserRecord(): string
    {
        $sessions = Sessions::getInstance();
        $users = Users::getInstance();
        $dbc = DatabaseConnector::getInstance();
        $i18n = I18n::getInstance();
        $userRecordsCount = $dbc->countRecords($users->userTableName);
        if ($userRecordsCount > 0) {
            $errorMessage = $i18n->t("N2Co3F|Failed to add default ad...", $users->userTableName);
            file_put_contents(self::$dbAuditLog, date("Y-m-d H:i:s") . ": " . $errorMessage . "\n",
                FILE_APPEND);
            return $errorMessage;
        }
        // if just a single user is in the database, it must be an admin user
        $firstUserRecord = $sessions->userCopy();
        $firstUserRecord["role"] = $users->userAdminRole;
        $success = $dbc->insertInto($users->userTableName, $firstUserRecord);
        if (is_numeric($success)) {
            $successMessage = $i18n->t("kx8iiD|Added admin record.");
            file_put_contents(self::$dbAuditLog,
                date("Y-m-d H:i:s") . ": " . $i18n->t("tNKKVB|Added admin record.") . "\n", FILE_APPEND);
            return $successMessage;
        } else {
            $errorMessage = $i18n->t("7vRDMJ|Failed to add default ad...") . $success;
            file_put_contents(self::$dbAuditLog, date("Y-m-d H:i:s") . ": " . $errorMessage . "\n",
                FILE_APPEND);
            return $errorMessage;
        }
    }

    /**
     * Build the sql command based on the definition here and the template like self::$sql_add_column_command
     * @param string $tableName The name of the table to build the command for.
     * @param string $columnName The name of the column to build the command for.
     * @param string $template The template to build the command from.
     * @return string The built sql command.
     */
    private function buildSqlColumnCommand(string $tableName, string $columnName, string $template): string
    {
        $sql = str_replace("{table}", $tableName, str_replace("{column}", $columnName, $template));

        $config = Config::getInstance();
        $cDefinition = $config->getItem(".tables.$tableName.$columnName");
        // SQL type definition. Use the size only for varchars
        $sqlType = $cDefinition->sqlType();
        $isVarChar = (strcasecmp($sqlType, "varchar") == 0);
        if ($isVarChar)
            $sqlType .= "(" . $cDefinition->valueSize() . ")";
        $defaultStr = "";
        if (!strcasecmp($cDefinition->sqlType(), "text") == 0) {
            // BLOB, TEXT, GEOMETRY or JSON columns can't have a default value
            $defaultValue = $cDefinition->value();
            $defaultStr = "DEFAULT '" . Formatter::format($defaultValue, $cDefinition->type()->parser(), Language::SQL) . "'";
        }
        return str_replace("{type}", $sqlType,
                    str_replace("{default}", $defaultStr, $sql));
    }

    /**
     * Build the sql command set (multiple commands) to create a table: DROP TABLE, CREATE TABLE (with all
     * columns), ALTER ... ADD UNIQUE, and ALTER ... MODIFY ... AUTO_INCREMENT
     * @param string $tableName The name of the table to create.
     * @return array The sql commands to create the table.
     */
    private function buildSqlAddTableCommands(string $tableName): array
    {
        // create an array of SQL commands to create the table.
        $sqlStatements = [];
        $config = Config::getInstance();

        // build the 'create table' statement with all columns
        $sqlStatements[0] = "CREATE TABLE `" . $tableName . "` ( ";
        $tableItem = $config->getItem(".tables.$tableName");
        $i = 1;
        foreach ($tableItem->getChildren() as $child) {
            $cName = $child->name();
            if (!str_starts_with($cName, "_")) {
                $colCmd = "`" . $cName . "` {type} NOT NULL {default}, ";
                $sqlStatements[0] .= self::buildSqlColumnCommand($tableName, $cName, $colCmd);
                // build all the necessary 'add unique'-statements on the way.
                $sqlIndexed = $child->sqlIndexed();
                if (str_contains($sqlIndexed, "u")) {
                    $sqlStatements[$i] = str_replace("{table}", $tableName, str_replace("{column}", $cName, self::$sqlAddUnique));
                    $i++;
                }
                // build the autoincrement statements on the way.
                if (str_contains($sqlIndexed, "a")) {
                    $sqlStatements[$i] = str_replace("{table}", $tableName, str_replace("{column}", $cName, self::$sqlAddAutoincrement));
                    $i++;
                }
            }
        }
        // close the columns list of the 'create table' statement
        $sqlStatements[0] = mb_substr($sqlStatements[0], 0, mb_strlen($sqlStatements[0]) - 2) . " )";
        return $sqlStatements;
    }

    /**
     * Execute and log a $sql_cmd. THIS MUST ONLY BE CALLED, IF THE USER HAS BEEN PROVED BEING AN ADMIN USER,
     * BECAUSE IT CIRCUMVENTS ALL PERMISSION CHECKS.
     * @param string $appUserID The id of the user who is executing the command.
     * @param string $sql The sql command to execute.
     * @param string $logMessage The message to log.
     * @return bool Whether the command was executed successfully.
     */
    private function executeAndLog(string $appUserID, string $sql, string $logMessage): bool
    {
        // this circumvents any permission check because it is only called by an admin user.
        $dbc = DatabaseConnector::getInstance();
        $success = $dbc->customQuery($sql, $this);
        if ($success === false) {
            $failMessage = "Failed database statement for User '$appUserID': $logMessage. Error: '" .
                $dbc->getError(). "' in $sql";
            file_put_contents(self::$dbAuditLog, date("Y-m-d H:i:s") . ": $failMessage.\n", FILE_APPEND);
            return false;
        } else {
            $successMessage = "Executed database statement for User '$appUserID': $logMessage.";
            file_put_contents(self::$dbAuditLog, date("Y-m-d H:i:s") . ": $successMessage.\n", FILE_APPEND);
            return true;
        }
    }

    /**
     * Delete all existing tables and build the database from scratch. Insert the current user as the single
     * administrator at the end to allow access to the database.
     * @return string The result of the database initialization. This is a string containing the log messages
     * for each executed SQL command. The string will be empty if the database was successfully initialized,
     * otherwise it will contain an error message
     */
    public function initDataBase(): string
    {
        $result = "";

        // check user privileges and get admin record
        $users = Users::getInstance();
        $sessions = Sessions::getInstance();
        $isAdminUser = $sessions->isAdminSessionUser(false);
        $i18n = I18n::getInstance();
        if ($isAdminUser === false)
            return $i18n->t("vB1jX7|Error: User does not hav...");
        $userId = $sessions->userId();

        // now reset all tables
        $runner = Runner::getInstance();
        $runner->logger->log(LoggerSeverity::INFO, "DatabaseSetup->initDatabase",
            "Starting database initialization");

        // build all tables
        $config = Config::getInstance();
        $tablesCfgRoot = $config->getItem(".tables");
        foreach ($tablesCfgRoot->getChildren() as $child) {
            $tableName = $child->name();
            $logMessage = $i18n->t("2p7kEh|Dropping table °%1°...", $tableName);
            $sql = "DROP TABLE `" . $tableName . "`;";
            $dropSuccess = $this->executeAndLog($userId, $sql, $logMessage);
            $result .= $logMessage . (($dropSuccess) ? $i18n->t("vI1rIC|ok.") : $i18n->t("VTLYoN|no such table.")) . "<br>";
            // this will abort execution after first failure.
            $logMessage = $i18n->t("WRvThr|Creating empty new table...", $tableName);
            $sqlStatements = $this->buildSqlAddTableCommands($tableName);
            $resetSuccess = true;
            foreach ($sqlStatements as $sql)
                // this will abort execution after first failure.
                $resetSuccess = $resetSuccess && $this->executeAndLog($userId, $sql, $logMessage);
            $result .= $logMessage .
                (($resetSuccess) ? $i18n->t("sQuH9Y|ok.") : $i18n->t("uhcKky|aborted, see sys_db_audi...")) . "<br>";
        }

        // if also the user's table was dropped and rebuilt, insert the admin user now.
        $logMessage = $i18n->t("wwM2JD|Inserting admin %1 recor...", $sessions->userId(), $users->userTableName) . ": ";
        $adminInsertSuccess = $this->insertFirstUserRecord();
        $result .= $logMessage . $adminInsertSuccess;
        return $result;
    }

    /**
     * Update all tables and columns to comply with the selected database layout.
     * @param bool $verifyOnly If true, only verify the database layout, but don't change anything. If false,
     * change the database layout to match the expected layout.
     * @return bool True if the database layout was successfully updated, false otherwise.
     */
    public function updateDatabaseLayout(bool $verifyOnly): bool
    {
        // check user privileges
        $i18n = I18n::getInstance();
        $isAdminUser = Sessions::getInstance()->isAdminSessionUser(false);
        if (($isAdminUser === false) && !$verifyOnly) {
            file_put_contents(self::$dbAuditLog,
                date("Y-m-d H:i:s") . ": " . $i18n->t("a9d5U7|User is no admin and thu...") . "\n", FILE_APPEND);
            return false;
        }

        $userId = Sessions::getInstance()->userId();
        file_put_contents(self::$dbAuditLog,
            date("Y-m-d H:i:s") . ": " . $i18n->t("8hPspQ|Starting") . " update_database_layout [" .
            json_encode($verifyOnly) . ", " . $userId . "].\n");

        // now adjust all tables
        $dbc = DatabaseConnector::getInstance();
        $correctionSuccess = true;
        $verificationSuccess = true;
        $tableNamesExisting = $dbc->tableNames();

        // adjust or add tables according to the expected layout
        $config = Config::getInstance();
        $tablesCfgRoot = $config->getItem(".tables");
        $tablesOfLayout = [];
        foreach ($tablesCfgRoot->getChildren() as $child) {
            $tableName = $child->name();
            if (! str_starts_with($tableName, "_")) {
                // cache all table names for later
                $tablesOfLayout[] = $tableName;
                if (strcmp($tableName, strtolower($tableName)) !== 0)
                    // Microsoft mySQL implementations on MS Azure use all lower case table names.
                    $tablesOfLayout[] = strtolower($tableName);
                // adjust existing table
                if (in_array($tableName, $tableNamesExisting)) {
                    if (!$this->updateTableLayout($userId, $tableName, $verifyOnly)) {
                        if (!$verificationSuccess && !$verifyOnly)
                            file_put_contents(self::$dbAuditLog,
                                date("Y-m-d H:i:s") . ": " . $i18n->t("rSrSm0|Update failed for table ...", $tableName) .
                                "\n", FILE_APPEND);
                        $correctionSuccess = false;
                        $verificationSuccess = false;
                    }
                } else {
                    // add missing table
                    if ($verifyOnly) {
                        file_put_contents(self::$dbAuditLog,
                            date("Y-m-d H:i:s") . ": " . $i18n->t("KprqM0|Verification failed. Tab...", $tableName) .
                            "\n", FILE_APPEND);
                        $verificationSuccess = false;
                    } else {
                        $logMessage = $i18n->t("wpf4AI|Create table °%1° with a...", $tableName);
                        $sqlStatements = $this->buildSqlAddTableCommands($tableName);
                        foreach ($sqlStatements as $sql)
                            // this will continue execution after failure.
                            $correctionSuccess = $this->executeAndLog($userId, $sql, $logMessage) &&
                                $correctionSuccess;
                    }
                }
            }
        }
        // and drop the obsolete ones
        foreach ($tableNamesExisting as $tableName) {
            if (!in_array($tableName, $tablesOfLayout)) {
                if ($verifyOnly) {
                    file_put_contents(self::$dbAuditLog,
                        date("Y-m-d H:i:s") . ": " . $i18n->t("9g3u60|Verification failed. Tab...", $tableName) .
                        "\n", FILE_APPEND);
                    $verificationSuccess = false;
                } else {
                    $sql = "DROP TABLE `" . $tableName . "`";
                    $logMessage = $i18n->t("TQrqJw|Drop table °%1°.", $tableName);
                    $correctionSuccess = $this->executeAndLog($userId, $sql, $logMessage) &&
                        $correctionSuccess;
                }
            }
        }

        // notify and register activity.
        if (!$verifyOnly) {
            file_put_contents(self::$dbAuditLog,
                date("Y-m-d H:i:s") . ": " . $i18n->t("qiUbiD|the database layout was ..."), FILE_APPEND);
        } else
            file_put_contents(self::$dbAuditLog,
                date("Y-m-d H:i:s") . ": " . $i18n->t("YwHPOy|database_layout verified"), FILE_APPEND);

        return ($verifyOnly) ? $verificationSuccess : $correctionSuccess;
    }

    /**
     * Updates the layout of a database table to match the expected schema configuration.
     * The method verifies the structure and optionally applies corrections. THIS MUST ONLY BE CALLED,
     * IF THE USER HAS BEEN PROVED BEING AN ADMIN USER, BECAUSE IT CIRCUMVENTS ALL PERMISSION CHECKS
     *
     * @param string $userId The ID of the user requesting the update, used for logging and permissions.
     * @param string $tableName The name of the table whose layout needs to be checked or updated.
     * @param bool $verifyOnly If true, only verifies the table layout without applying changes;
     *                         if false, applies corrections to match the expected layout.
     * @return bool True if the verification and/or corrections were successful, false otherwise.
     */
    private function updateTableLayout(string $userId, string $tableName, bool $verifyOnly): bool
    {
        $i18n = I18n::getInstance();
        // start process and logging.
        if (!$verifyOnly)
            file_put_contents(self::$dbAuditLog,
                date("Y-m-d H:i:s") . ": " . $i18n->t("YPmPF9|Starting") . " update_table_layout(" . $tableName .
                ").\n", FILE_APPEND);

        // read existing schema information.
        // =================================
        $dbc = DatabaseConnector::getInstance();
        $columnNamesExisting = $dbc->columnNames($tableName);
        // split type description (like 'VARCHAR(64)') into name and size
        $columnTypeDescriptionsExisting = $dbc->columnTypes($tableName);
        $columnTypesExisting = [];
        $columnSizesExisting = [];
        foreach ($columnTypeDescriptionsExisting as $columnTypeDescription) {
            $hasSize = str_contains($columnTypeDescription, "(");
            $columnTypesExisting[] = ($hasSize) ? explode("(", $columnTypeDescription)[0] : $columnTypeDescription;
            $columnSizesExisting[] = ($hasSize) ? intval(
                str_replace(")", "", explode("(", $columnTypeDescription)[1])) : 0;
        }
        // collect indexes
        $indexesExisting = $dbc->indexes($tableName, false);
        $autoIncrementsExisting = $dbc->autoIncrementColumns($tableName);
        // collect null-allowed columns
        $columnNullExisting = $dbc->columnsNull($tableName);
        $defaultsExisting = $dbc->columnDefaults($tableName);

        // adjust or add columns, according to the expected layout
        $verificationSuccess = true;
        $correctionSuccess = true;
        $config = Config::getInstance();
        $tableRootItem = $config->getItem(".tables.$tableName");

        // Collect what is expected
        // ========================
        $columnsExpected = [];
        $indexesExpected = [];
        foreach ($tableRootItem->getChildren() as $child) {
            $cName = $child->name();
            if (!str_starts_with($cName, "_"))
                $columnsExpected[] = $cName;
            if (strcmp(strtolower($cName), $cName) != 0)
                // Microsoft mySQL implementations on MS Azure use all lower case table names.
                $columnsExpected[] = strtolower($cName);
            if (strlen($child->sqlIndexed()) > 0)
                $indexesExpected[] = $cName;
        }

        // Add or modify all columns of this table
        // =======================================

        foreach ($tableRootItem->getChildren() as $child) {
            $cName = $child->name();
            if (!str_starts_with($cName, "_")) {

                // find the column within the set of exiting ones
                $c = array_search($cName, $columnNamesExisting, true);
                if ($c !== false) {  // column is EXISTING
                    // get SQL parameters
                    $cType = trim($columnTypesExisting[$c]);
                    $cSize = ((strcasecmp($cType, "text") == 0) || (strcasecmp($cType, "mediumtext") == 0)) ? 0 : intval(
                        trim($columnSizesExisting[$c]));
                    $sqlType = $child->sqlType();
                    $sqlSize = (strcasecmp($child->sqlType(), "varchar") == 0) ? $child->valueSize() : 0;
                    $sqlNull = array_key_exists($cName, $columnNullExisting);
                    $defaultMatched = true;
                    $defaultNull = false;
                    $isAutoIncrement = array_key_exists($cName, $autoIncrementsExisting);
                    if (!strcasecmp($child->sqlType(), "text") == 0) {
                        // BLOB, TEXT, GEOMETRY or JSON columns can't have a default value
                        $defaultValue = $child->defaultValue();
                        $defaultNull = is_null($defaultsExisting[$c]);
                        $defaultConfigured = Formatter::format($defaultValue, $child->type()->parser(), Language::SQL);
                        $defaultMatched = !$defaultNull && strcasecmp($defaultConfigured, $defaultsExisting[$c]) == 0;
                    }

                    if (!$verifyOnly) {
                        // adjust null values
                        if ($sqlNull) {
                            $sql = self::buildSqlColumnCommand($tableName, $cName, self::$adjustNullColumnStep1);
                            $sql = str_replace("DEFAULT ", "", $sql);
                            $logMessage = $i18n->t("Replaced null values for column °%1°.°%2°", $tableName, $cName);
                            $correctionSuccess = $this->executeAndLog($userId, $sql, $logMessage) &&
                                $correctionSuccess;
                            $sql = self::buildSqlColumnCommand($tableName, $cName, self::$adjustNullColumnStep2);
                            $logMessage = $i18n->t("Disallowed null values for column °%1°.°%2°", $tableName, $cName);
                            $correctionSuccess = $this->executeAndLog($userId, $sql, $logMessage) &&
                                $correctionSuccess;
                        }
                        // perform the column adjustment
                        if ((strcasecmp($cType, $sqlType) != 0) || ($cSize != $sqlSize) || !$defaultMatched) {
                            $sql = self::buildSqlColumnCommand($tableName, $cName, self::$sqlChangeColumn);
                            $logMessage = $i18n->t("sNh3bq|Changed column °%1°.°%2°...", $tableName, $cName);
                            $correctionSuccess = $this->executeAndLog($userId, $sql, $logMessage) &&
                                $correctionSuccess;
                        }
                    } else {
                        // or check type, size or default if just the verification shall happen
                        // and log the discrepancy
                        if (strcasecmp($cType, $sqlType) != 0) {
                            file_put_contents(self::$dbAuditLog,
                                date("Y-m-d H:i:s") . ": " . $i18n->t("mwaK4O|Verification failed. Col...",
                                    $tableName, $cName, $cType, $sqlType) . "\n", FILE_APPEND);
                            $verificationSuccess = false;
                        }
                        if ($cSize != $sqlSize) {
                            file_put_contents(self::$dbAuditLog,
                                date("Y-m-d H:i:s") . ": " . $i18n->t("3EWY5V|Verification failed. Col...",
                                    $tableName, $cName, $cSize, $sqlSize) . ".\n", FILE_APPEND);
                            $verificationSuccess = false;
                        }
                        if ($sqlNull) {
                            file_put_contents(self::$dbAuditLog,
                                date("Y-m-d H:i:s") . ": " . $i18n->t("Verification failed. %1.%2 must not allow a null value",
                                    $tableName, $cName) . ".\n", FILE_APPEND);
                            $verificationSuccess = false;
                        }
                        if (!$isAutoIncrement) {
                            // All columns must have a default in tfyh tables, but SQL does not allow a default setting for autoincrement columns.
                            if ($defaultNull) {
                                file_put_contents(self::$dbAuditLog,
                                    date("Y-m-d H:i:s") . ": " . $i18n->t("Verification failed. %1.%2 must have a default value",
                                        $tableName, $cName) . ".\n", FILE_APPEND);
                                $verificationSuccess = false;
                            }
                            elseif (!$defaultMatched) {
                                file_put_contents(self::$dbAuditLog,
                                    date("Y-m-d H:i:s") . ": " . $i18n->t("Verification failed. The default value for %1.%2 is wrong",
                                        $tableName, $cName) . ".\n", FILE_APPEND);
                                $verificationSuccess = false;
                            }
                        }
                    }
                } else {

                    // this column is NOT existing.
                    if ($verifyOnly) {
                        // log the discrepancy
                        file_put_contents(self::$dbAuditLog,
                            date("Y-m-d H:i:s") . ": " .
                            $i18n->t("rxMN98|Verification failed. Col...", $tableName, $cName) . "\n",
                            FILE_APPEND);
                        $verificationSuccess = false;
                    } else {
                        // add column
                        $activityTemplate = self::$sqlAddColumn;
                        $logMessage = $i18n->t("xw01aj|Added column °%1°.°%2° w...", $tableName, $cName);
                        $sql = self::buildSqlColumnCommand($tableName, $cName, $activityTemplate);
                        $correctionSuccess = $this->executeAndLog($userId, $sql, $logMessage) &&
                            $correctionSuccess;
                    }
                }
            }

            // Add or modify all indexes of this table
            // =======================================

            // add a unique quality, if not yet existing
            $isSqlIndexedUnique = str_contains($child->sqlIndexed(), "u");
            if ($isSqlIndexedUnique) {
                if (!in_array($cName, $indexesExisting)) {
                    // sometimes indices are not recognized by the DatabaseConnector. It may be there. Drop it first and
                    // restore it.
                    $sql1 = "ALTER TABLE `" . $tableName . "` DROP INDEX `" . $cName . "`;";
                    $sql2 = "ALTER TABLE `" . $tableName . "` ADD UNIQUE `" . $cName . "` (`" . $cName .
                        "`); ";
                    $logMessage = $i18n->t("S1nQio|Added unique property to...", $tableName, $cName);
                    if (!$verifyOnly) {
                        // rebuild the index
                        $correctionSuccess = $this->executeAndLog($userId, $sql1, $logMessage) &&
                            $correctionSuccess;
                        $correctionSuccess = $this->executeAndLog($userId, $sql2, $logMessage) &&
                            $correctionSuccess;
                    } else {
                        // log the discrepancy
                        file_put_contents(self::$dbAuditLog,
                            date("Y-m-d H:i:s") . ": " . $i18n->t("9mUNZn|Verification failed. Ind...",
                                $tableName, $cName) . "\n", FILE_APPEND);
                        $verificationSuccess = false;
                    }
                }
            }

            // add an autoincrement quality, if needed
            // e.g. ALTER TABLE `persons` CHANGE `id` `id` INT NOT NULL AUTO_INCREMENT;
            $isSqlIndexedAutoIncrement = str_contains($child->sqlIndexed(), "a");
            if ($isSqlIndexedAutoIncrement) {
                if (!array_key_exists($cName, $autoIncrementsExisting)) {
                    $sql = str_replace("{table}", $tableName, str_replace("{column}", $cName, self::$sqlAddAutoincrement));
                    $logMessage = $i18n->t("H5p5xA|Added auto increment pro...", $tableName, $cName);
                    if (!$verifyOnly) {
                        // make the index autoincrement
                        $correctionSuccess = $this->executeAndLog($userId, $sql, $logMessage) &&
                            $correctionSuccess;
                    } else {
                        // log the discrepancy
                        file_put_contents(self::$dbAuditLog,
                            date("Y-m-d H:i:s") . ": " . $i18n->t("Auto-increment property missing for column %1.%2",
                                $tableName, $cName) . "\n", FILE_APPEND);
                        $verificationSuccess = false;
                    }
                }
            }
        }

        // delete what is obsolete in the expected layout
        // ==============================================

        // columns
        foreach ($columnNamesExisting as $cNameExisting) {
            if (!in_array($cNameExisting, $columnsExpected)) {
                if ($verifyOnly) {
                    file_put_contents(self::$dbAuditLog,
                        date("Y-m-d H:i:s") . ": " . $i18n->t("ulIoAv|Verification failed. Col...", $tableName,
                            $cNameExisting) . "\n", FILE_APPEND);
                    $verificationSuccess = false;
                } else {
                    $sql = "ALTER TABLE `" . $tableName . "` DROP `" . $cNameExisting . "`;";
                    $logMessage = $i18n->t("TuOggl|Dropped obsolete column ...", $tableName, $cNameExisting);
                    $correctionSuccess = $this->executeAndLog($userId, $sql, $logMessage) &&
                        $correctionSuccess;
                }
            }
        }
        // indexes
        foreach ($indexesExisting as $indexExisting) {
            if (!in_array($indexExisting, $indexesExpected)) {
                if ($verifyOnly) {
                    file_put_contents(self::$dbAuditLog,
                        date("Y-m-d H:i:s") . ": " .
                        $i18n->t("nPmhjO|Verification failed. Ind...", $tableName, $indexExisting) .
                        "\n", FILE_APPEND);
                    $verificationSuccess = false;
                } else {
                    $sql = "ALTER TABLE `" . $tableName . "` DROP INDEX `" . $indexExisting . "`;";
                    $logMessage = $i18n->t("zQY162|Dropped obsolete index °...", $tableName, $indexExisting);
                    $correctionSuccess = $this->executeAndLog($userId, $sql, $logMessage) &&
                        $correctionSuccess;
                }
            }
        }

        // log the result and return it
        // ============================

        // if the $verify_only flag is set and this point reached, all is fine
        if ($verifyOnly) {
            if ($verificationSuccess) {
                file_put_contents(self::$dbAuditLog,
                    date("Y-m-d H:i:s") . ": " . $i18n->t("gLdeJY|Verification successful ...", $tableName) . "\n",
                    FILE_APPEND);
            } else {
                file_put_contents(self::$dbAuditLog,
                    date("Y-m-d H:i:s") . ": " . $i18n->t("A1tKnO|Verification failed for ...", $tableName) . "\n",
                    FILE_APPEND);
            }
        } else {
            if ($correctionSuccess) {
                file_put_contents(self::$dbAuditLog,
                    date("Y-m-d H:i:s") . ": " . $i18n->t("6QCftb|Completed update_table_l...", $tableName) . "\n",
                    FILE_APPEND);
            } else {
                file_put_contents(self::$dbAuditLog,
                    date("Y-m-d H:i:s") . ": " . $i18n->t("URJSm4|Correction failed for °%...", $tableName) . "\n",
                    FILE_APPEND);
            }
        }
        return ($verifyOnly) ? $verificationSuccess : $correctionSuccess;
    }
}
    
