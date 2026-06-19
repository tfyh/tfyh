<?php

namespace tfyh\data;

use tfyh\control\LoggerSeverity;
use tfyh\control\Runner;
use tfyh\control\Sessions;
use tfyh\control\Users;
include_once "../_Control/LoggerSeverity.php";
include_once "../_Control/Runner.php";
include_once "../_Control/Sessions.php";
include_once "../_Control/Users.php";

// internationalisation support on needed to translate the missingNotice
use tfyh\util\I18n;
use tfyh\util\ListHandlerKernel;
include_once "../_Util/I18n.php";
include_once "../_Util/ListHandlerKernel.php";

/**
 * Class responsible for managing indices stored in the $_SESSION super-global to enhance performance throughout a session.
 * The indices primarily support operations related to entity identification, mapping, and table management.
 */
class Indices
{

    // indices are stored in the $_SESSION super-global to increase performance throughout a session
    private String $userIdFieldName;
    public String $missingNotice;
    private static Indices $instance;

    /**
     * @return Indices the singleton instance of the indices.
     */
    public static function getInstance(): Indices {
        if (!isset(self::$instance))
            self::$instance = new Indices();
        return self::$instance;
    }

    /**
     * Constructor. Initializes the indices and sets the missingNotice.
     */
    private function __construct() {
        $this->init();
        $this->missingNotice = "[" . I18n::getInstance()->t("Vicmvm|not found") . "]";
        $this->userIdFieldName = Config::getInstance()->getItem(".framework.users.user_id_field_name")->valueStr();
    }

    /**
     * Initializer for the indices. Clears or creates the indices if they do not exist.
     * @return void
     */
    private function init(): void {
        if (! isset($_SESSION["ix"])) {
            $_SESSION["ix"] = [];
            $_SESSION["ix"]["uid2table"] = [];
            $_SESSION["ix"]["short2longUuid"] = [];
            $_SESSION["ix"]["uuid2table"] = [];
            $_SESSION["ix"]["uuid2invalidFrom"] = [];
            $_SESSION["ix"]["uuid2name"] = [];
            $_SESSION["ix"]["userId2name"] = [];
            $_SESSION["ix"]["name2uuid"] = [];
            $_SESSION["ix"]["name2invalidFrom"] = [];
            $_SESSION["ix"]["loaded"] = [];
        }
    }
    
    /**
     * Add a single entry to the indices. Provides a warning on duplicates for uid and short UUID, i.e. the first 11
     * characters of a UUID.
     * @param string $uid the uid of the record to index.
     * @param string $uuid the uuid of the record to index.
     * @param string $userId the user id of the record to index.
     * @param float $invalidFrom the invalidFrom of the record to index.
     * @param string $tableName the name of the table to which the record belongs.
     * @param string $name the name of object reference in the record to index.
     * @return String a warning message, if any.
     */
    private function add(string $uid, string $uuid, string $userId, float $invalidFrom, string $tableName, string $name): String {
        $warnings = "";
        if (strlen($uid) > 0) {
            if (! isset($_SESSION["ix"]["uid2table"][$uid]))
                $_SESSION["ix"]["uid2table"][$uid] = $tableName;
            else {
                $warning = "Duplicate uid $uid in both " . $_SESSION["ix"]["uid2table"][$uid] . " and $tableName. ";
                Runner::getInstance()->logger->log(LoggerSeverity::ERROR, "Indices->add", $warning);
                $warnings .= $warning;
            }
        }
        if (strlen($uuid) > 0) {
            $shortUuid = substr($uuid, 0, 11);
            $_SESSION["ix"]["uuid2table"][$shortUuid] = $tableName;
            $_SESSION["ix"]["uuid2name"][$shortUuid] = $name;
            if (! isset($_SESSION["ix"]["name2uuid"][$tableName][$name]))
                $_SESSION["ix"]["name2uuid"][$tableName][$name] = [];
            if (!in_array($uuid, $_SESSION["ix"]["name2uuid"][$tableName][$name]))
                $_SESSION["ix"]["name2uuid"][$tableName][$name][] = $uuid;
            if (! isset($_SESSION["ix"]["short2longUuid"][$shortUuid]))
                $_SESSION["ix"]["short2longUuid"][$shortUuid] = $uuid;
            else {
                $warning = "Duplicate short UUID $shortUuid (the 11 UUID start characters) in both " .
                    $_SESSION["ix"]["uid2table"][$uid] . " and $tableName. ";
                Runner::getInstance()->logger->log(LoggerSeverity::ERROR, "Indices->add()", $warning);
                $warnings .= $warning;
            }
        }
        if ($invalidFrom > 0) {
            if (!in_array($name, $_SESSION["ix"]["name2invalidFrom"][$tableName])
                || ($_SESSION["ix"]["name2invalidFrom"][$tableName][$name] < $invalidFrom))
                $_SESSION["ix"]["name2invalidFrom"][$tableName][$name] = $invalidFrom;
            if (strlen($uuid) > 0) {
                if (! isset($_SESSION["ix"]["uuid2invalidFrom"][$uuid]) || ($_SESSION["ix"]["uuid2invalidFrom"][$uuid] < $invalidFrom))
                    $_SESSION["ix"]["uuid2invalidFrom"][$uuid] = $invalidFrom;
            }
        }
        if (intval($userId) > 0)
            $_SESSION["ix"]["userId2name"][intval($userId)] = $name;
        return $warnings;
    }

    /**
     * Create the definition for a dynamic list which retrieves the information needed for the name index using the
     * tables "name" template.
     * @param Record $record the record of the table for which the list is created.
     * @param String $userRole the role of the user for which the list is created.
     * @return String the definition of the list.
     */
    private function createListDefinition(Record $record, String $userRole): String {
        $tableName = $record->item->name();
        $listDefinition = "id;permission;name;label;select;table;where;options\n";
        $listDefinition .= "1;$userRole;index;autogenerated index creation list;";
        $nameFields = $record->templateFields("name");
        $selectString = "";
        if ($record->item->hasChild("uid"))
            $selectString .= ",uid";
        if ($record->item->hasChild("uuid"))
            $selectString .= ",uuid";
        if ($record->item->hasChild("invalid_from"))
            $selectString .= ",invalid_from";
        if ($record->item->hasChild($this->userIdFieldName))
            $selectString .= "," . $this->userIdFieldName;
        // stop here, if there is not uid/uuid provided, like in system tables (archive, changes, rubbish)
        if (strlen($selectString) == 0)
            return "For table $tableName, there is no uid nor uuid. ";
        $sortField = explode(",", substr($selectString, 1))[0];
        $optionsString = ($record->item->hasChild("invalid_from"))
            ? "sort=uuid.-invalid_from&firstofblock=uuid" : "sort=$sortField";
        foreach ($nameFields as $nameField)
            $selectString .= "," . $nameField;
        $listDefinition .= substr($selectString, 1) . ";$tableName;1;$optionsString";
        return $listDefinition;
    }

    /**
     * Add all records of a table to the indices. Provides warnings on duplicates for uid and short UUID, i.e. the
     * first 11 characters of a UUID.
     * @param Item $recordItem the configuration of the record to be indexed.
     * @return String a warning message, if any.
     */
    private function addTable(Item $recordItem): String {
        $tableName = $recordItem->name();
        if (isset($_SESSION["ix"]["loaded"][$tableName]) && $_SESSION["ix"]["loaded"][$tableName])
            return "";
        $userRole = Sessions::getInstance()->userRole();
        if ($userRole === Users::getInstance()->anonymousRole)
            return "No index built for anonymous user.";
        $record = new Record($recordItem);
        $listDefinition = $this->createListDefinition($record, $userRole);
        $listHandlerKernel = new ListHandlerKernel("@dynamic", $listDefinition);
        $rows = $listHandlerKernel->getRows("csv");
        $warnings = "";
        if (! isset($_SESSION["ix"]["name2uuid"][$tableName]))
            $_SESSION["ix"]["name2uuid"][$tableName] = [];
        if (! isset($_SESSION["ix"]["name2invalidFrom"][$tableName]))
            $_SESSION["ix"]["name2invalidFrom"][$tableName] = [];
        foreach ($rows as $rowSql) {
            $name = $record->rowToTemplate("name", $rowSql);
            $uid = $rowSql["uid"] ?? "";
            $uuid = $rowSql["uuid"] ?? "";
            $userId = $rowSql[$this->userIdFieldName] ?? "";
            $invalidFrom = (isset($rowSql["invalid_from"]) && (strlen($rowSql["invalid_from"]) > 0))
                ? floatVal($rowSql["invalid_from"]) : ParserConstraints::FOREVER_SECONDS;
            $warnings .= $this->add($uid, $uuid, $userId, $invalidFrom, $tableName, $name);
        }
        $_SESSION["ix"]["loaded"][$tableName] = true;
        return $warnings;
    }

    /**
     * Add all records of all tables to the indices. Provides warnings on duplicates for uid and short UUID, i.e. the
     * first 11 characters of a UUID.
     */
    public function addAll(): String {
        if (isset($_SESSION["ix"]["loaded"]["@all"]) && $_SESSION["ix"]["loaded"]["@all"])
            return "";
        $warnings = "";
        $tablesItem = Config::getInstance()->getItem(".tables");
        foreach ($tablesItem->getChildren() as $recordItem) {
            if ($recordItem->hasChild("uid")
                && (!isset($_SESSION["ix"]["loaded"][$recordItem->name()]) || !$_SESSION["ix"]["loaded"][$recordItem->name()]))
                $warnings .= $this->addTable($recordItem);
        }
        $_SESSION["ix"]["loaded"]["@all"] = true;
        return $warnings;
    }

    /**
     * Set the index of names to uuids for a specific table. Access the result in this.name2uuid[tableName].
     * The Form uses this public access to the #addTable private function
     * @param String $tableName the name of the table for which the index is built.
     * @return void
     */
    public function buildIndexOfNames(String $tableName): void {
        if (! isset($_SESSION["ix"]["name2uuid"][$tableName]))
            $this->addTable(Config::getInstance()->getItem(".tables." . $tableName));
    }

    /**
     * Get the record's name (i.e. the filled "name" template) for an uuid. returns a missing notice, if not resolved.
     * Restrict the search to a single table by setting the $tableName.
     * @param String $uuidOrShortUuid  the uuid or the first 11 characters of the uuid.
     * @param String $tableName the name of the table for which the index is built.
     * @return String the name of the record, or a missing notice.
     */
    public function getNameForUuid(String $uuidOrShortUuid, String $tableName = "@all"): String {
        if (strlen($uuidOrShortUuid) == 0)
            return "";
        $shortUuid = substr($uuidOrShortUuid, 0, 11);
        if ($tableName !== "@all") {
            $matchedTableName = $this->getTableForUuid($shortUuid, $tableName);
            if ($matchedTableName !== $tableName)
                return $this->missingNotice;
            else
                return $_SESSION["ix"]["uuid2name"][$shortUuid];
        } else
            return $_SESSION["ix"]["uuid2name"][$shortUuid] ?? $this->missingNotice;
    }

    /**
     * Get the username for a specific user id.
     * @param int $userId the id of the user.
     * @return mixed|string the name of the user, or a missing notice.
     */
    public function getUserName(int $userId): mixed
    {
        $userTable = Users::getInstance()->userTableName;
        if (isset($_SESSION["ix"]["loaded"][$userTable]) && !$_SESSION["ix"]["loaded"][$userTable])
            $this->addTable(Config::getInstance()->getItem(".tables." . Users::getInstance()->userTableName));
        return $_SESSION["ix"]["userId2name"][$userId] ?? $this->missingNotice;
    }

    /**
     * Get the name of the table in which the uid occurs. Returns an empty String, if not resolved.
     * Restrict the search to a single table by setting the $tableName.
     * @param String $uid the uid of the record to look for.
     * @param String $tableName Restrict the search to a single table by setting the $tableName.
     * @return String the name of the table, or an empty String.
     */
    public function getTableForUid(String $uid, String $tableName = "@all"): String {
        if (strlen($uid) == 0)
            return "";
        if ($tableName !== "@all") {
            $this->addAll();
        } else {
            $recordItem = Config::getInstance()->getItem(".tables.$tableName");
            if (! $recordItem->isValid())
                return "";
            $this->addTable($recordItem);
        }
        return $_SESSION["ix"]["uid2table"][$uid] ?? "";
    }

    /**
     * Get the name of the table in which the uid occurs. Returns a missing notice, if not resolved.
     * Restrict the search to a single table by setting the $tableName.
     * @param String $uuidOrShortUuid the uuid of the record to look for.
     * @param String $tableName Restrict the search to a single table by setting the $tableName.
     * @return String the name of the table, or an empty String.
     */
    public function getTableForUuid(String $uuidOrShortUuid, String $tableName = "@all"): String {
        if (strlen($uuidOrShortUuid) == 0)
            return "";
        $shortUuid = substr($uuidOrShortUuid, 0, 11);
        if ($tableName !== "@all") {
            $recordItem = Config::getInstance()->getItem(".tables.$tableName");
            if (!$recordItem->isValid())
                return "";
            $this->addTable($recordItem);
            if (!isset($_SESSION["ix"]["uuid2table"][$shortUuid]) || $_SESSION["ix"]["uuid2table"][$shortUuid] !== $tableName)
                return "";
            else
                return $_SESSION["ix"]["uuid2table"][$shortUuid];
        }
        return $_SESSION["ix"]["uuid2table"][$shortUuid] ?? "";
    }

    /**
     * Get the uuid for a name within a table. If the name cannot be resolved, "" is returned. If for this name
     * exists multiple uuids, the uuid with the most recent invalidFrom parameter is used.
     * @param String $tableName the name of the table for which the index is built.
     * @param String $nameToResolve the name to resolve.
     * @return string the uuid of the record, or an empty string.
     */

    public function getUuid(String $tableName, String $nameToResolve): string {
        if (strlen($nameToResolve) == 0)
            return "";
        if (!isset($_SESSION["ix"]["name2uuid"][$tableName]))
            $this->addTable(Config::getInstance()->getItem(".tables.$tableName"));
        $uuids = $_SESSION["ix"]["name2uuid"][$tableName][$nameToResolve];
        if (!is_array($uuids))
            return "";
        if (count($uuids) == 1)
            return $uuids[0];
        $mostRecent = [];
        foreach ($uuids as $uuid)
            $mostRecent[$_SESSION["ix"]["uuid2invalidFrom"][$uuid]] = $uuid;
        krsort ($mostRecent);
        return $mostRecent[array_key_first($mostRecent)];
    }

    /**
     * Get all names for a specific table as name => invalidFrom (float)
     * @param String $tableName the name of the table for which the index is built.
     * @return array the names of the records, or an empty array.
     */
    public function getNames(String $tableName): array {
        if (!isset($_SESSION["ix"]["name2invalidFrom"][$tableName]))
            $this->addTable(Config::getInstance()->getItem(".tables.$tableName"));
        return $_SESSION["ix"]["name2invalidFrom"][$tableName];
    }

    /**
     * Get a new uid which is checked against all existing to be really new. The randomizer has a very low
     * probability of 1 in 2.5e14 to generate two identical uids, but who knows. This is secure but requires
     * the full indices loading.
     */
    public function getNewUid(): String {
        $this->addAll();
        $uid = Ids::generateUid(6);
        while (isset($_SESSION["ix"]["uid2table"][$uid]))
            $uid = Ids::generateUid(6);
        return $uid;
    }

}
