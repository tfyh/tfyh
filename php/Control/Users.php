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

namespace Control;

use Data\Codec;
use Data\Config;
use Data\DatabaseConnector;
use Data\Ids;
use Data\ParserConstraints;

// internationalisation support on needed to translate user access and services information provided at the UI
use Util\I18n;
include_once "../../tfyh/Util/I18n.php";

/**
 * A utility class to hold the user profile management functions which do not depend on the application.
 */
class Users
{
    protected array $actionLinks;
    public string $userTableName;

    public string $userIdFieldName;
    public string $userAccountFieldName;
    public string $userMailFieldName;
    public string $userFirstNameFieldName;
    public string $userLastNameFieldName;
    public string $userRoleFieldName = "role"; // not configurable
    public string $userPasswordHashFieldName = "password_hash"; // not configurable

    public bool $useSubscriptions;
    public bool $useWorkflows;
    public bool $useConcessions;

    public string $userAdminRole;
    public int $userAdminWorkflows;
    public string $anonymousRole;
    public string $selfRegisteredRole;

    /**
     * Roles may include other roles. Expansion provides the role plus the respective included roles in an
     * array. The role_hierarchy is read from the file "../../Config/access/role_hierarchy", which must contain
     * per role a line "role=role1,role2,...".
     */
    public array $includedRoles;

    /**
     * Is true for those roles for which those who get it shall be listed on role control.
     */
    public array $isPrivilegedRole;

    private static Users $instance;

    /**
     * Get the singleton instance of the Users class.
     */
    public static function getInstance(): Users {
        if (! isset(self::$instance))
            self::$instance = new self();
        return self::$instance;
    }

    /**
     * Construct the Users class. This reads the configuration, initialises the logger and the navigation menu,
     * asf.
     */
    private function __construct()
    {
        $this->setIncludedRoles();
        $this->setFields();
    }

    /**
     * Set all included roles based on the role hierarchy. The role hierarchy is read from the file
     * "../../Config/access/role_hierarchy", which must contain per role a line "role=role1,role2,..."
     * @return void
     */
    public function setIncludedRoles(): void
    {
        // resolve role hierarchy
        $roles = Config::getInstance()->getItem(".access.roles");
        foreach ($roles->getChildren() as $role) {
            $mainRole = $role->name();
            $includedRoles = $role->valueStr();
            $isPrivilegedRole = (str_starts_with($includedRoles, "*"));
            $this->isPrivilegedRole[$mainRole] = $isPrivilegedRole;
            if ($isPrivilegedRole)
                $includedRoles = substr($includedRoles, 1);
            $this->includedRoles[$mainRole] = explode(",", $includedRoles);
        }
    }

    /**
     * Convenience method to collect the user properties into directly accessible variables.
     */
    private function setFields(): void
    {
        $config = Config::getInstance();
        // user data configuration
        $this->actionLinks = $config->getItem(".framework.users.action_links")->value();
        $this->userTableName = $config->getItem(".framework.users.user_table_name")->value();
        $this->userIdFieldName = $config->getItem(".framework.users.user_id_field_name")->value();
        $this->userAccountFieldName = $config->getItem(".framework.users.user_account_field_name")->value();
        $this->userMailFieldName = $config->getItem(".framework.users.user_mail_field_name")->value();
        $this->userFirstNameFieldName = $config->getItem(".framework.users.user_firstname_field_name")->value();
        $this->userLastNameFieldName = $config->getItem(".framework.users.user_lastname_field_name")->value();
        // is set: $this->userRoleFieldName
        // is set: $this->userPasswordHashFieldName

        // Specifically, authorised roles. Table field name: "role" for the user role.
        $this->userAdminRole = $config->getItem(".framework.users.useradmin_role")->value();
        $this->userAdminWorkflows = $config->getItem(".framework.users.useradmin_workflows")->value();
        $this->selfRegisteredRole = $config->getItem(".framework.users.self_registered_role")->value();
        $this->anonymousRole = $config->getItem(".framework.users.anonymous_role")->value();

        // User preferences and permissions, table field names: subscriptions, workflows, and concessions
        $userRecordItem = $config->getItem(".tables." . $this->userTableName);
        $this->useSubscriptions = $userRecordItem->hasChild("subscriptions");
        $this->useWorkflows = $userRecordItem->hasChild("workflows");
        $this->useConcessions = $userRecordItem->hasChild("concessions");
    }

    /* ======================== Access Control ============================== */
    /**
     * Check whether the item is visible to be accessed by the session user.
     * @param string $permission The permission string, e.g. "role1,role2,role3" or "role1,!role2,role3"
     * @return bool True if the user is allowed to access the item, false otherwise.
     */
    public function isHiddenItem(String $permission): bool
    {
        return ($this->isAllowedOrHiddenItem($permission) & 2) > 0;
    }

    /**
     * Check whether the item is allowed to be accessed by the session user.
     * @param string $permission The permission string, e.g. "role1,role2,role3" or "role1,!role2,role3"
     * @return bool True if the user is allowed to access the item, false otherwise.
     */
    public function isAllowedItem(string $permission): bool
    {
        return ($this->isAllowedOrHiddenItem($permission) & 1) > 0;
    }

    /**
     * Check for workflows, concessions, and subscriptions whether they are allowed for the current user.
     * @param int $allowedOrHidden The result of the previous check, 0-3 reflecting two bits:
     * for permitted AND with 0x1, for hidden AND with 0x2.
     * @param array $permissionsArray The array of permissions, e.g. ["role1","role2",".role4","@17"]
     * @param int $services The bitmask of the services to be checked, e.g. 0x1 for subscriptions, 0x2 for workflows,
     * 0x4 for concessions.
     * @param string $serviceIdentifier The identifier of the service, e.g. "#" for subscriptions, "@" for workflows,
     * "$" for concessions.
     * @return int The result of the check, 0-3 reflecting two bits: for permitted AND with 0x1, for hidden AND with 0x2.
     */
    private function addAllowedOrHiddenService(int $allowedOrHidden, array $permissionsArray,
                                               int $services, string $serviceIdentifier): int
    {
        foreach ($permissionsArray as $permissionsElement) {
            if (str_contains($permissionsElement, $serviceIdentifier)) {
                $elementHidden = (str_starts_with($permissionsElement, "."));
                $elementServiceMap = intval(substr($permissionsElement, (($elementHidden) ? 2 : 1)));
                $elementAllowed = (($services & $elementServiceMap) > 0);
                if ($elementAllowed) {
                    // add allowance if element is allowed
                    $allowedOrHidden = $allowedOrHidden | 1;
                    // remove hidden flag, if allowed and not hidden.
                    if (!$elementHidden && (($allowedOrHidden & 2) > 0))
                        $allowedOrHidden = $allowedOrHidden - 2;
                }
            }
        }
        return $allowedOrHidden;
    }

    /**
     * Check whether a role shall get access to the given item and, if so, whether it should be displayed in
     * the menu. The role will be expanded according to the hierarchy, and all included roles are as well
     * checked, except it is preceded by a '!'. If the permission String is preceded by a "." the menu will
     * not be shown, but accessible - same for all accessing roles.
     * @param string $permission The permission string, e.g. "role1,role2,.role4,@17"
     * @return int The result of the check, 0-3 reflecting two bits: for permitted AND with 0x1, for hidden AND with 0x2.
     */
    private function isAllowedOrHiddenItem(string $permission): int
    {
        $sessions = Sessions::getInstance();
        $accessingRole = $sessions->userRole();
        $subscriptions = $sessions->userSubscriptions();
        $workflows = $sessions->userWorkflows();
        $concessions = $sessions->userConcessions();
        // else it must match one of the roles in the hierarchy.
        $includedRoles = $this->includedRoles[$accessingRole];
        // now check permissions. This will for every permissions entry check allowance and display.
        $permissionsArray = explode(",", $permission);
        // the $allowed_or_hidden integer carries the result as 0-3 reflecting two bits:
        // for permitted AND with 0x1, for hidden AND with 0x2
        $allowedOrHidden = 2; // default is not permitted, hidden
        foreach ($permissionsArray as $permissionsElement) {
            $elementHidden = (str_starts_with($permissionsElement, "."));
            $elementRole = ($elementHidden) ? substr($permissionsElement, 1) : $permissionsElement;
            $elementAllowed = in_array($elementRole, $includedRoles);
            if ($elementAllowed) {
                // add allowance if element is allowed
                $allowedOrHidden = $allowedOrHidden | 1;
                // remove hidden flag, if allowed and not hidden.
                if (!$elementHidden && (($allowedOrHidden & 2) > 0))
                    $allowedOrHidden = $allowedOrHidden - 2;
            }
        }
        // or meet the permitted subscriptions.
        if ($subscriptions > 0)
            $allowedOrHidden = $this->addAllowedOrHiddenService($allowedOrHidden, $permissionsArray,
                $subscriptions, '#');
        // or meet the permitted workflows.
        if ($workflows > 0)
            $allowedOrHidden = $this->addAllowedOrHiddenService($allowedOrHidden, $permissionsArray,
                $workflows, '@');
        // or meet the permitted concessions.
        if ($concessions > 0)
            $allowedOrHidden = $this->addAllowedOrHiddenService($allowedOrHidden, $permissionsArray,
                $concessions, '$');
        return $allowedOrHidden;
    }

    /**
     * Get all access rights and their descriptions, either as html or as a string for the audit log.
     * @param bool $forAuditLog
     * @return string
     */
    public function getAllAccesses(bool $forAuditLog = false): string
    {
        $i18n = I18n::getInstance();
        $auditLogStr = $i18n->t("OPc8WE|Count of privileged role...") . " ";
        $html = "<h4>" . $i18n->t("8RhH9W|Roles") . "</h4>";
        foreach ($this->isPrivilegedRole as $role => $isPrivileged) {
            if ($isPrivileged) {
                $html .= "<h5>$role</h5><p>";
                $auditLogStr .= $role . " - ";
                $countRoleUsers = 0;
                $allPrivileged = DatabaseConnector::getInstance()->findAll($this->userTableName, ["role" => $role], 500);
                if ($allPrivileged)
                    foreach ($allPrivileged as $privileged) {
                        $user_reference = (isset($privileged["transactionId"])) ? "<a href='../../tfyh/forms/changeUser.php?id=" .
                            $privileged["transactionId"] . "'>" . $privileged[$this->userIdFieldName] . "</a>" : $privileged[$this->userIdFieldName];
                        $html .= "&nbsp;&nbsp;#" . $user_reference . ": " .
                            ((isset($privileged["Titel"])) ? $privileged["Titel"] : "") . " " .
                            $privileged[$this->userFirstNameFieldName] . " " .
                            $privileged[$this->userLastNameFieldName] . ".<br>";
                        $countRoleUsers++;
                    }
                else
                    $html .= "&nbsp;&nbsp;" . $i18n->t("355gfL|No one") . "<br>";
                $auditLogStr .= $countRoleUsers . "; ";
             }
        }

        $auditLogStr .= "\n" . $i18n->t("8fGF1t|Count of non-privileged ...") . " ";
        $dbc = DatabaseConnector::getInstance();
        foreach ($this->isPrivilegedRole as $role => $isPrivileged) {
            if (!$isPrivileged) {
                $html .= "<h5>$role</h5><p>";
                $allNonPrivileged = $dbc->find($this->userTableName, "role", $role);
                if (!$allNonPrivileged)
                    $html .= "&nbsp;&nbsp;" . $i18n->t("DEPfjp|No one") . "<br>";
                else
                    $html .= "&nbsp;&nbsp;" . $i18n->t("5Bd5LG|In Total %1 users.", count($allNonPrivileged)) .
                        "<br>";
                $auditLogStr .= $role . " - " .
                    (($allNonPrivileged) ? strval(count($allNonPrivileged)) : "0") . "; ";
            }
        }
        $auditLogStr .= "\n";
        $html .= "</p><p>";

        $servicesText = "";
        if ($this->useWorkflows)
            $servicesText .= $this->getServiceUsersListed("workflows",
                    "workflows", false, $forAuditLog) . "\n";
        if ($this->useConcessions)
            $servicesText .= $this->getServiceUsersListed("concessions",
                    "concessions", false, $forAuditLog) . "\n";
        if ($this->useSubscriptions)
            $servicesText .= $this->getServiceUsersListed("subscriptions",
                    "subscriptions", true, $forAuditLog) . "\n";
        if ($forAuditLog)
            return $auditLogStr . $servicesText;
        else
            return $html . str_replace("\n", "</p><p>", $servicesText) . "</p>";
    }

    /**
     * Get the set of available services to a user.
     * @param string $type The type of services, e.g. "workflows", "concessions", "subscriptions"
     * @return array The set of services, e.g. all workflows, all concessions, all subscriptions.
     */
    private function getServiceSet(string $type): array {
        $set = [];
        $typeItem = Config::getInstance()->getItem(".access.$type");
        foreach ($typeItem->getChildren() as $child) {
            $service = [];
            $service["name"] = $child->name();
            $service["title"] = $child->label();
            $service["description"] = $child->description();
            $service["flag"] = $child->value();
            $set[] = $service;
        }
        return $set;
    }

    /**
     * Provide a list of users for all services existing
     * @param string $type The type of services, e.g. "workflows", "concessions", "subscriptions"
     * @param string $fieldName The field name of the service, e.g. "edit logbook"
     * @param bool $countOnly Whether to count only or to list all users.
     * @param bool $forAuditLog Whether to return the audit log string or the html.
     * @return string The html or the audit log string.
     */
    private function getServiceUsersListed(string $type, string $fieldName, bool $countOnly, bool $forAuditLog): string
    {
        $i18n = I18n::getInstance();
        $servicesSet = $this->getServiceSet($type);
        $servicesList = (count($servicesSet) > 0) ? "<h4>$fieldName</h4>" : "";
        $auditLog = $fieldName . ": ";
        $noUsersAt = "";

        foreach ($servicesSet as $service) {
            $title = ((strcasecmp("workflows", $type) == 0) ? "@" : ((strcasecmp("concessions", $type) == 0) ? "$" : "#")) .
                $service["flag"] . ": " . $i18n->t($service["title"]);
            $serviceUsers = DatabaseConnector::getInstance()->findAllSorted($this->userTableName, [ $fieldName =>
                $service["flag"]], 5000, "&", $this->userFirstNameFieldName, true);
            $countOfServiceUsers = ($serviceUsers) ? count($serviceUsers) : 0;
            if ($countOfServiceUsers == 0)
                $noUsersAt .= $title . ", ";
            else {
                $servicesList .= "<h5>" . $title . "</h5><p>";
                $servicesList .= $i18n->t("AkTh7r|In Total %1 users.", $countOfServiceUsers) . "<br>";
                $auditLog .= $title . " - " . $countOfServiceUsers . "; ";
                if (!$countOnly && is_array($serviceUsers))
                    foreach ($serviceUsers as $serviceUser)
                        $servicesList .= "<a href='../../tfyh/forms/changeServices.php?type=" . strtolower($fieldName) .
                            "&id=" . $serviceUser["transactionId"] . "'>#" . $serviceUser[$this->userIdFieldName] .
                            "</a>: " . ((isset($serviceUser["title"])) ? $serviceUser["Titel"] : "") .
                            " " . $serviceUser[$this->userFirstNameFieldName] . " " .
                            $serviceUser[$this->userLastNameFieldName] . ".<br>";
                $servicesList .= "</p>";
            }
        }

        if (strlen($noUsersAt) > 0) {
            $servicesList .= "<h5>" . $i18n->t("4C9I5e|No users for") . "</h5><p>" . $noUsersAt . "</p>";
            $auditLog .= "\n " . $i18n->t("wyHjxb|No users for") . " " . $noUsersAt;
        }
        return ($forAuditLog) ? $auditLog : $servicesList;
    }

    /**
     * Provide a list of service titles for subscriptions, workflows, and concessions which the user is
     * granted. In the case of subscriptions, a change link is added. The list is returned as a table-row inhtml format with two
     * entries: $key and the list.
     * @param string $type The type of services, e.g. "workflows", "concessions", "subscriptions"
     * @param string $key An arbitrary key field for the table row, e.g. "Dienste" or "Workflows" just for html-display
     * @param string $value The bitmask of the services of the user and their named list.
     * @return string The html for the table row.
     */
    public function getUserServices(string $type, string $key, string $value): string
    {
        $i18n = I18n::getInstance();
        $servicesSet = Codec::csvFileToMap("../../Config/access/$type");
        $servicesList = "[" . $value . "] ";
        foreach ($servicesSet as $service)
            if ((intval($value) & intval($service["Flag"])) > 0)
                $servicesList .= $i18n->t($service["Titel"]) . ", ";
        $change_link = (strcasecmp($type, "subscriptions") == 0) ? "<br><a href='../../tfyh/forms/changeServices.php'> &gt; " .
            $i18n->t("08PFcm|change") . "</a>" : "";
        return "<tr><td><b>" . $key . "</b>&nbsp;&nbsp;&nbsp;</td><td>" . $servicesList . $change_link .
            "</td></tr>\n";
    }

    /* ======================== Generic user property management ============================== */
    /**
     * Return the respective link set for allowed actions of a verified user regarding the user to modify.
     * @param int $userId The id of the user to modify.
     * @param string|null $uid The uid of the user to modify.
     * @return string
     */
    public function getActionLinks(int $userId, string $uid = null): string
    {
        $actionLinksHtml = "";
        foreach ($this->actionLinks as $actionLink) {
            $parts = explode(":", $actionLink);
            if ($this->isAllowedItem($parts[0])) {
                // i18n support
                $textStart = strpos($parts[1], "i('");
                $textEnd = strpos($parts[1], "')");
                if (($textStart !== false) && ($textEnd !== false) && ($textEnd > $textStart)) {
                    $textStart = $textStart +3; // skip the needle part
                    $text = substr($parts[1], $textStart, $textEnd - $textStart);
                    $text_i18n = I18n::getInstance()->t($text);
                    $parts[1] = substr($parts[1], 0, $textStart - 3) . $text_i18n .
                        substr($parts[1], $textEnd + 2);
                }
                $actionLinkHtml = str_replace("{#ID}", $userId, $parts[1]);
                if (!is_null($uid))
                    $actionLinkHtml = str_replace("{#uid}", $uid, $actionLinkHtml);
                $actionLinksHtml .= $actionLinkHtml;
            }
        }
        return $actionLinksHtml;
    }

    /**
     * Get an empty user for this application. Creates a new record with a new uid and uuid if the user table has
     * these fields.
     * @param String $keepPassword The default password input field, if the user table has a password field. If
     * recognized on form submission, the password will be kept.
     * @return array
     */
    public function getEmptyUserRow(String $keepPassword = ""): array
    {
        $user = array();
        $user[$this->userIdFieldName] = "-1";
        $user["role"] = $this->anonymousRole;
        if ($this->useSubscriptions) $user["subscriptions"] = "0";
        if ($this->useWorkflows) $user["workflows"] = "0";
        if ($this->useConcessions) $user["concessions"] = "0";
        $userRecordItem = Config::getInstance()->getItem(".tables." . $this->userTableName);
        if ($userRecordItem->hasChild("uid"))
            $user["uid"] = Ids::generateUid(6);
        if ($userRecordItem->hasChild("uuid"))
            $user["uuid"] = Ids::generateUuid();
        $user[$this->userFirstNameFieldName] = "Mary";
        $user[$this->userLastNameFieldName] = "Doe";
        $user[$this->userMailFieldName] = "PLEASE.CHANGE_@_THIS.ADDRESS.ORG";
        if ($userRecordItem->hasChild("valid_from"))
            $user["valid_from"] = strval(microtime(true));
        if ($userRecordItem->hasChild("invalid_from"))
            $user["invalid_from"] = strval(ParserConstraints::FOREVER_SECONDS);
        if ($userRecordItem->hasChild("password_hash"))
            $user["password_hash"] = $keepPassword;
        return $user;
    }

    /**
     * @return int The highest user id in the database.
     */
    public function getHighestUserId(): int {
        $idMaxUsers = DatabaseConnector::getInstance()->findAllSorted($this->userTableName, [], 1, "=",
            $this->userIdFieldName, false);
        if (isset($idMaxUsers[0][$this->userIdFieldName]) && $idMaxUsers !== false)
            return intval($idMaxUsers[0][$this->userIdFieldName]);
        else return 0;
    }

    /**
     * Get the user record for a user id. This takes into account versioned records and will only return
     * valid user record, no invalid ones. It can be used to get the session user record.
     * @param int $userId The id of the user to be retrieved.
     * @return array|false An associative array containing the user's record if found and valid, or false if no valid
     * record is found.
     */
    public function getUserById(int $userId) : array|false
    {
        $userRecord = DatabaseConnector::getInstance()->findAllSorted($this->userTableName,
            [$this->userIdFieldName => $userId], 10, "=", "invalid_from", false);
        if (($userRecord === false) || !is_array($userRecord))
            return false;
        if ((count($userRecord) == 1) & (!array_key_exists("invalid_from", $userRecord[0]) ||
                ($userRecord[0]["invalid_from"] == 0)))
            return $userRecord[0];
        return false;
    }

}
