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

use Data\Config;
use Data\DatabaseConnector;
use Data\Ids;

/**
 * Class to handle an application sessions pool to manage concurrency and throttle load. Two sorts of sessions
 * exist: Web-sessions for web access which are managed by the PHP session framework and only mirrored into
 * the application sessions pool and api-sessions for API access. Both are pooled in the "../../var/Run/sessions"
 * directory, each type represented by a session file, named with its session's ID. The session file starts
 * with three numbers: started at (Unix timestamp, seconds - float); refreshed at (Unix timestamp, seconds -
 * float); user ID (integer) - all terminated by a ";". Sessions have a keep-alive limit and a lifetime. If a
 * session is inactive until the keep-alive limit is hit or actively hits the lifetime end, it is removed from
 * the application session pool and its PHP session is closed. Regenerate the session to keep it alive beyond
 * its lifetime end. This will change the session id regularly to mitigate spoofing risks.
 */
class Sessions
{

    public static string $tooManySessionsErrorHeadline = "!#too many sessions";
    /**
     * see "https://www.php.net/manual/en/session.configuration.php"
     */
    private static array $phpIniDefaults = ['session.name' => 'PHPSESSID', 'session.save_handler' => 'files',
        'session.auto_start' => '0', 'session.gc_probability' => '1', 'session.gc_divisor' => '100',
        'session.gc_maxlifetime' => '1440', 'session.serialize_handler' => 'php',
        'session.cookie_lifetime' => '0', 'session.cookie_path' => '/', 'session.cookie_domain' => '',
        'session.cookie_secure' => '0', 'session.cookie_httponly' => '0', 'session.cookie_samesite' => '',
        'session.use_strict_mode' => '0', 'session.use_cookies' => '1', 'session.use_only_cookies' => '1',
        'session.referer_check' => '', 'session.cache_limiter' => 'nocache', 'session.cache_expire' => '180',
        'session.use_trans_sid' => '0', 'session.trans_sid_tags' => 'a=href,area=href,frame=src,form=',
        'session.trans_sid_hosts' => "\$_SERVER['HTTP_HOST']", 'session.sid_length' => '32',
        'session.sid_bits_per_character' => '4', 'session.upload_progress.enabled' => '1',
        'session.upload_progress.cleanup' => '1', 'session.upload_progress.prefix' => 'upload_progress_',
        'session.upload_progress.name' => 'PHP_SESSION_UPLOAD_PROGRESS',
        'session.upload_progress.freq' => '1%', 'session.upload_progress.min_freq' => '1',
        'session.lazy_write' => '1'
    ];

    /**
     * see "https://www.php.net/manual/en/features.session.security.management.php"
     */
    private static array $phpIniSecurity = ['session.cookie_secure' => '1',
        // If off cookies will bes sent also over http, not only https
        'session.cookie_httponly' => '1',
        // Marks the cookie as accessible only through the HTTP protocol
        'session.cookie_samesite' => 'Strict',
        // assert that a cookie ought not to be sent along with cross-site requests.
        'session.use_strict_mode' => '1',
        // see Non-adaptive Session Management. "Warning: Do not misunderstand the DoS risk.
        // session.use_strict_mode=On is mandatory for general session ID security! All sites are advised
        // to enable session.use_strict_mode. "
        'session.sid_length' => '26',
        // the longer, the better. The typical setting is 26
        'session.sid_bits_per_character' => '5'
        // typical setting is 5
    ];

    /**
     * The grace period does keep an obsolete session for the case that the client browser did not receive
     * a Set-cookie header. Value is in seconds.
     */
    private static int $gracePeriod = 60;

    private static string $sessionsDir = "../../var/Run/sessions/";

    private static Sessions $instance;

    private string $sessionType; // the type of the session web or api.
    private array $user; // the user owning the current session
    private string $sessionId; // the id of the current session
    private Users $users;
    public array $settings;

    private function __construct(string $sessionType) {

        $config = Config::getInstance();
        $this->sessionType = $sessionType;
        $this->users = Users::getInstance();
        $sessionsItem = $config->getItem(".framework.sessions");
        // max_inits_per_hour, max_errors_per_hour,
        // max_concurrent_sessions, max_session_duration, max_session_keepalive
        foreach ($sessionsItem->getChildren() as $sessionConfigItem)
            $this->settings[$sessionConfigItem->name()] = $sessionConfigItem->value();
        if (!file_exists(self::$sessionsDir))
            mkdir(self::$sessionsDir);
        $this->initSecurity();
        file_put_contents(self::$sessionsDir . "/php_security.log", $this->logSecurity());
        $this->clear();
    }

    public static function getInstance(string $sessionType = "undefined"): Sessions {
        if (! isset(self::$instance))
            self::$instance = new self($sessionType);
        return self::$instance;
    }

    public function sessionType(): string { return self::$instance->sessionType; }
    public function sessionId(): string { return self::$instance->sessionId; }
    /**
     * Clear the user and the session ID.
     */
    private function clear(): void
    {
        $this->user = $this->users->getEmptyUserRow(); // the user owning the current session
        $this->sessionId = ""; // the id of the current session
    }

    /**
     * Get a copy of the session users record. The session user's record itself shall not be modified outside this class.
     */
    public function userCopy(): array {
        return array_map(function ($value) {
            return $value;
        }, $this->user);
    }
    public function userFirstName(): string {
        return $this->user[$this->users->userFirstNameFieldName] ?? "Mary";
    }
    public function userLastName(): string {
        return $this->user[$this->users->userLastNameFieldName] ?? "Doe";
    }
    public function userFullName(): string {
        return $this->user[$this->users->userFirstNameFieldName] . " " . $this->user[$this->users->userLastNameFieldName];
    }
    public function userId(): int {
        return intval($this->user[$this->users->userIdFieldName] ?? "0");
    }
    public function userUuid(): string {
        return $this->user["uuid"] ?? "";
    }
    public function userMail(): String {
        return $this->user[$this->users->userMailFieldName] ?? "-";
    }

    public function userRole(): string {
        return $this->user["role"] ?? Users::getInstance()->anonymousRole;
    }

    /**
     * Assign a different role to the user, usually for test purposes. Use the Menu class to check whether the user
     * has the right ro get the required role.
     * @param string $newRole the new role to assign to the user.
     * @return void
     */
    public function modifyUserRole(string $newRole): void {
        Runner::getInstance()->logger->log(LoggerSeverity::INFO, "Sessions->modifyUserRole()",
            "Modified user role for user " . $this->userFullName() . " from " . $this->user["role"] .
            " to " . $newRole);
        $this->user["role"] = $newRole;
    }
    public function userSubscriptions(): string {
        return ($this->users->useSubscriptions) ? intval($this->user["subscriptions"]) : 0;
    }
    public function userWorkflows(): string {
        return ($this->users->useWorkflows) ? intval($this->user["workflows"]) : 0;
    }
    public function userConcessions(): string {
        return ($this->users->useConcessions) ? intval($this->user["concessions"]) : 0;
    }
    public function userPreferences(): string {
        return $this->user["preferences"] ?? "";
    }

    /* -------------------------------------------------------- */
    /* ---------- HANDLE THE SESSION LIFETIME ----------------- */
    /* -------------------------------------------------------- */

    /**
     * Start a session, i.e. create or update an application session file. The user must be authenticated and
     * authorised before. This will not change an existing PHP session. The global $_SESSION variable is not available
     * for API transaction handling. Application sessions pooling is used to control the number of concurrent
     *  users because api-sessions do not know of each other nor of web-sessions.
     * NB: $isSecondaryApiSession must not be set except when opening a secondary api-session for the web-session user.
     * In this case the $userId is ignored, instead $this->userId() is used.
     * @param int $userId the user id of the user owning the session.
     * @param bool $isSecondaryApiSession true if this is a secondary api-session for the web-session user.
     * @return bool true if the session was started, false otherwise.
     */
    public function sessionStart(int $userId, bool $isSecondaryApiSession = false): bool
    {
        // remove all obsolete sessions first to prevent from reuse
        $openSessionsCount = $this->cleanseAndCountSessions();
        $logger = Runner::getInstance()->logger;

        // identify the session id
        if (($this->sessionType == "api") || $isSecondaryApiSession) {
            // try to find an existing session first.
            $sessionId = $this->getApiSessionId(($isSecondaryApiSession) ? $this->userId() : $userId);
            // if there is no session available, create one.
            if ((strlen($sessionId) == 0) || ($this->readSession($sessionId) === false))
                $sessionId = "~" . Ids::generateUid(30); // this session id will have 41 characters.
        } else {
            // load the web-session context.
            $startRes = true;
            if (session_status() === PHP_SESSION_NONE)
                $startRes = session_start();
            if (!$startRes) {
                $error_message = "Failed to start a web-session context. Most probably some text was already echoed. " .
                    "This can also happen, if a class file has an invisible character before the '&lt;?php' tag.";
                $logger->log(LoggerSeverity::ERROR, "sessionStart", $error_message);
                return false;
            }
            // get session and user ids.
            $sessionId = session_id();  // In a web context the above $sessionId function argument is ignored
        }

        $this->sessionId = $sessionId;
        $existingSession = $this->readSession($sessionId);
        if ($existingSession !== false) // use an existing session
            return $this->sessionVerifyAndUpdate($userId, $sessionId);
        else // or start a new one
            return $this->sessionCreate($userId, $sessionId, $openSessionsCount);
    }

    /**
     * This updates the current api-sessions lifetime and keep-alive period to self::$grace_period seconds
     * from now and starts a new api-session. Note the sequence: the lifetime will always be updated, even if
     * the start of a new session fails.
     * @param string $sessionId the id of the session to update.
     * @return bool true if the session was updated, false otherwise.
     */
    public function sessionRegenerate(string $sessionId): bool
    {
        $logger = Runner::getInstance()->logger;
        // get existing session
        $session = $this->readSession($sessionId);
        if ($session === false) {
            $message = "Someone tried to regenerate a non-existing session";
            $logger->log(LoggerSeverity::ERROR, "sessionRegenerateId", $message);
            return false;
        }
        $userId = $session["user_id"];
        $message = "Limiting '$sessionId' for '$userId'";
        $logger->log(LoggerSeverity::INFO, "sessionRegenerateId", $message);
        // the current session is kept alive for self::$gracePeriod seconds in case of network errors
        $grace_period = microtime(true) + self::$gracePeriod;
        $this->writeSessionAndSetUser($grace_period, $grace_period, $userId, $sessionId);
        // start the new session
        return $this->sessionStart($userId, "new");
    }

    /**
     * Close a user session. This will also remove the session file and the PHP session. Closing the session helps to
     * avoid overload situations detected by the Monitor.
     * @param string $cause the reason for closing the session.
     * @param string $sessionId the id of the session to close.
     * @return void
     */
    public function sessionClose(string $cause, string $sessionId = ""): void {

        $logger = Runner::getInstance()->logger;
        // Remove the user and the session ID
        $this->user = Users::getInstance()->getEmptyUserRow();
        $this->sessionId = "";

        // get the web session id (= PHP session ID) if no session id is provided.
        if (strlen($sessionId) == 0) {
            if (session_status() === PHP_SESSION_NONE)
                session_start();
            $sessionId = session_id();
        }
        if (strlen($sessionId) == 0)
            $logger->log(LoggerSeverity::WARNING, "sessionClose",
                "No active web session to remove.");

        // remove the session file
        $unlinkSuccess = !file_exists(self::$sessionsDir . $sessionId) || unlink(
                self::$sessionsDir . $sessionId);
        if (!$unlinkSuccess)
            $logger->log(LoggerSeverity::WARNING, "sessionClose",
                "Unable to remove session file '$sessionId'. Closing reason: " . $cause);

        // close the web session if no session ID was provided.
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();
            session_destroy();
            $logger->log(LoggerSeverity::INFO, "sessionClose",
                "Destroying session '$sessionId'. Cause: " . $cause);
        }
    }

    /* -------------------------------------------------------- */
    /* ---------- HANDLE PHP INI SECURITY SETTINGS ------------ */
    /* -------------------------------------------------------- */

    /**
     * Initialise session security settings
     */
    private function initSecurity(): void
    {
        foreach (self::$phpIniDefaults as $key => $default) {
            $value = ini_get($key);
            $secure = self::$phpIniSecurity[$key] ?? false;
            if ($secure !== false) {
                if (strcmp($value, $secure) != 0)
                    ini_set($key, $secure);
            } elseif (strcmp($value, $default) != 0)
                ini_set($key, $default);
        }
    }

    /**
     * Log the session security settings.
     */
    private function logSecurity(): string
    {
        $securityLog = "PHP ini settings log.\n";
        $securityLog .= "Checking against upgraded security and PHP default values.\n";
        foreach (self::$phpIniDefaults as $key => $default) {
            $value = ini_get($key);
            if (array_key_exists($key, self::$phpIniSecurity)) {
                $secure = self::$phpIniSecurity[$key];
                if (strcmp($value, $secure) == 0)
                    $securityLog .= "-- '$key' value is secure '$secure'.\n";
                elseif (strcmp($value, $default) == 0)
                    $securityLog .= "-! '$key' value '$value' is not secure but default '$default'.\n";
                else
                    $securityLog .= "!! '$key' value '$value' is neither secure '$secure' nor default '$default'.\n";
            } else {
                if (strcmp($value, $default) == 0)
                    $securityLog .= "-- '$key' value is default '$default'.\n";
                else
                    $securityLog .= "-! '$key' value '$value' is not default '$default'.\n";
            }
        }
        $sessionSavePath = session_save_path();
        if (!$sessionSavePath)
            $securityLog .= "!! session_save_path() is false.\n";
        else
            $securityLog .= "session path '$sessionSavePath' properties:\n";
        $sessionDirStat = stat(session_save_path());
        if (!$sessionDirStat)
            $securityLog .= "!! stat() failed..\n";
        else
            foreach ($sessionDirStat as $key => $value)
                if (!is_numeric($key))
                    $securityLog .= ".. '$key' = '$value'.\n";
        $sessionDirPermissions = fileperms($sessionSavePath);
        if (!$sessionDirPermissions)
            $securityLog .= "!! fileperms() failed.\n";
        else
            $securityLog .= ".. file permissions of directory = '" . $sessionDirPermissions . "'.\n";

        return $securityLog;
    }

    /* -------------------------------------------------------- */
    /* ---------- SESSION HANDLING UTILITY FUNCTIONS ---------- */
    /* -------------------------------------------------------- */

    /**
     * Get a hash of the client's IP-address. This is used to differentiate sessions of different clients which may use
     * the very same user ID. This is not 100% safe, but 99% should be OK.
     */
    private function addressHash(): string
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        return substr(md5(strval($ipAddress)), 0, 10);
    }

    /**
     * Write a session file. If this is an update, the file is read before, and if the session lifetime is
     * lower or equal (now + self::$grace_period + 1 second), the update is refused.
     * @param float $aliveUntil the time until when the session will be kept alive without any user action.
     * @param float $endsOn the time when the session will end.
     * @param int $userId the user id of the user owning the session.
     * @param string $sessionId the id of the session to update.
     * @return bool true if the session was updated, false otherwise.
     */
    private function writeSessionAndSetUser(float $aliveUntil, float $endsOn, int $userId, string $sessionId): bool
    {
        if ($userId < 0)
            // do not open a counted session for anonymous users.
            return true; // i. e. no session limit hit.

        $existingSession = $this->readSession($sessionId);
        if (($existingSession !== false) &&
            ($existingSession["ends_on"] < (microtime(true) + self::$gracePeriod + 1))) {
            Runner::getInstance()->logger->log(LoggerSeverity::WARNING, "writeSessions",
                "Writing session '$sessionId', no more updates, too close to lifetime end.");
            return false;
        }

        // write session file
        $sessionFileContents = $aliveUntil . ";" . $endsOn . ";" . $userId . ";" . $this->addressHash() .
            ";" . "alive until " . date("Y-m-d H:i:s", intval($aliveUntil)) . ", ends on " .
            date("Y-m-d H:i:s", intval($endsOn));
        $sessionFile = self::$sessionsDir . $sessionId;
        $success = (file_put_contents($sessionFile, $sessionFileContents) > 0);
        if ($success) {
            // set user
            $users = Users::getInstance();
            $dbc = DatabaseConnector::getInstance();
            $appUser = $dbc->find($users->userTableName, $users->userIdFieldName, $userId);
            if ($appUser === false)
                return false;
            $this->user = $appUser;
            $dbc->timestampAccess(false);
            return true;
        }
        return false;
    }

    /**
     * Read and parse a session file into "alive_until", "ends_on", "user_id", "address_hash"
     * @param string $sessionId the id of the session to read.
     * @return bool|array false if the session file does not exist, or an array with the session data.
     */
    private function readSession(string $sessionId): bool|array
    {
        $sessionFile = self::$sessionsDir . $sessionId;
        if (!file_exists($sessionFile))
            return false; // This is a normal situation when starting a new web session.
        $sessionFileContents = file_get_contents($sessionFile);
        if ($sessionFileContents === false) {
            Runner::getInstance()->logger->log(LoggerSeverity::ERROR, "readSession",
                "Failed to read existing session '$sessionFile'.");
            return false;
        }
        $parts = explode(";", $sessionFileContents);
        if (count($parts) < 3) {
            Runner::getInstance()->logger->log(LoggerSeverity::ERROR, "readSession",
                "Wrongly formatted session file for '$sessionFile'");
            return false;
        }
        $session = array();
        $session["alive_until"] = floatval($parts[0]);
        $session["ends_on"] = floatval($parts[1]);
        $session["user_id"] = intval($parts[2]);
        $session["address_hash"] = $parts[3];
        return $session;
    }

    /**
     * Cleanse the application sessions pool from expired sessions' files and count the remainder. Cleansing
     * uses $this->session_close() to also completely remove the associated PHP session. This is called before
     * every session starts.
     */
    private function cleanseAndCountSessions(): int
    {
        $sessionFiles = scandir(self::$sessionsDir);
        $openSessionsCount = 0;
        foreach ($sessionFiles as $sessionFile) {
            if (!str_starts_with($sessionFile, ".") && ($sessionFile != "php_security.log")) {
                $session = $this->readSession($sessionFile);
                if ($session === false) {
                    if (file_exists(self::$sessionsDir . $sessionFile))
                        unlink(self::$sessionsDir . $sessionFile);
                } else {
                    $openSessionsCount++;
                    $now = time();
                    $causeToRemove = ($session["alive_until"] < $now) ?
                        "Session inactivity timeout" : (($session["ends_on"] < $now) ? "Session lifetime end" : false);
                    if ($causeToRemove !== false) {
                        $openSessionsCount--;
                        $this->sessionClose($causeToRemove, $sessionFile);
                    }
                }
            }
        }
        return $openSessionsCount;
    }

    /**
     * Get the longest living API session of the user with $user_id and the current client's token.
     * @param int $userId the user id of the user owning the session.
     * @return string the id of the longest living API session of the user, or an empty string if no session is found.
     */
    private function getApiSessionId(int $userId): string
    {
        // collect all API sessions for this user and detect the maximum lifetime
        $sessionFiles = scandir(self::$sessionsDir);
        // default is ascending filename order, thus always the same sequence.
        $apiSessionsOfUser = [];
        $maxLifetime = 0;
        $addressHash = $this->addressHash();
        // iterate over all session files
        foreach ($sessionFiles as $sessionFile) {
            if (str_starts_with($sessionFile, "~")) {
                $session = $this->readSession($sessionFile);
                // filter on those with the same user id and address hash
                if ((intval($session["user_id"]) == $userId) &&
                    (strcmp($addressHash, $session["address_hash"]) == 0)) {
                    if ($session["ends_on"] > $maxLifetime)
                        $maxLifetime = $session["ends_on"];
                    $session["session_id"] = $sessionFile;
                    $apiSessionsOfUser[] = $session;
                }
            }
        }
        // close all extra sessions and return the session id.
        foreach ($apiSessionsOfUser as $apiSessionOfUser)
            if ($apiSessionOfUser["ends_on"] == $maxLifetime)
                return $apiSessionOfUser["session_id"];
        return "";
    }

    /**
     * Create a not yet existing web- or api-session-file and write it into the application sessions pool.
     * @param int $userId the user id of the user owning the session.
     * @param string $sessionId the id of the session to create.
     * @param int $openSessionsCount the number of open sessions.
     * @return bool true if the session was created, false otherwise.
     */
    private function sessionCreate(int $userId, string $sessionId, int $openSessionsCount): bool
    {
        $logger = Runner::getInstance()->logger;
        $sessionFile = self::$sessionsDir . $sessionId;
        if (file_exists($sessionFile)) {
            $logger->log(LoggerSeverity::ERROR, "sessionCreate",
                "Creating new session file '$sessionFile'");
            // keep user and sessionId, the session is existing and may be used
            return false;
        }
        if ($openSessionsCount >= $this->settings["max_concurrent_sessions"]) {
            $logger->log(LoggerSeverity::ERROR, "sessionCreate",
                "Starting new session. Current count of open sessions: " . $openSessionsCount);
            // no session is created. Remove the user and the session ID
            $this->user = Users::getInstance()->getEmptyUserRow();
            $this->sessionId = "";
            return false;
        }
        $now = microtime(true);
        return $this->writeSessionAndSetUser($now + $this->settings["max_session_keepalive"],
            $now + $this->settings["max_session_duration"], $userId, $sessionId);
    }

    /**
     * Update an existing web- or api-session's keep-alive timestamp. Sessions are cleansed first, so if the
     * session was already outdated, it will not be updated. If the $user_id is not consistent with the
     * session user id, the session is closed. If the session file with $session_id does not exist, only an
     * error is logged, but nothing changed in the session context.
     * @param int $userId the user id of the user owning the session.
     * @param string $sessionId the id of the session to update.
     * @return bool true if the session was updated, false otherwise.
     */
    public function sessionVerifyAndUpdate(int $userId, string $sessionId): bool
    {
        $logger = Runner::getInstance()->logger;
        $this->cleanseAndCountSessions();

        // read the session, if after cleansing still existing
        $existingSession = $this->readSession($sessionId);
        // session does not exist
        if (!$existingSession) {
            $errorMessage = "User $userId tried to use invalid session '$sessionId'";
            $logger->log(LoggerSeverity::ERROR, "sessionVerifyAndUpdate", $errorMessage);
            // no session is available or it was ended. Remove the user and the session ID
            $this->user = Users::getInstance()->getEmptyUserRow();
            $this->sessionId = "";
            return false;
        }
        // check whether a session belongs to a different user (not relevant for web sessions)
        $sessionUser = intval($existingSession["user_id"]);
        if (($this->sessionType() !== "web") && ($sessionUser != $userId) && ($sessionUser >= 0)) {
            $cause = "User $userId tried to use session $sessionId of user " . $existingSession["user_id"];
            $this->sessionClose($cause);
            return false;
        }
        // session check is OK. Update the keep-alive
        $endsOn = $existingSession["ends_on"];
        $aliveUntil = microtime(true) + $this->settings["max_session_keepalive"];
        $this->writeSessionAndSetUser($aliveUntil, $endsOn, $sessionUser, $sessionId);
        return true; // even if the session writing fails, this indicates that the session was valid.
    }

    /**
     * Set the session user, who is needed for the database bootstrap only. Therefor is only possible if the
     * database has no table.
     * @param array $adminRecord the record of the admin user.
     * @return bool true if the user was set, false otherwise.
     */
    public function setAdminUserForBootstrap(array $adminRecord): bool {
        $dbc = DatabaseConnector::getInstance();
        $tableNames = $dbc->tableNames();
        if (count($tableNames) > 0)
            return false;
        $this->user = $adminRecord;
        return true;
    }
    /**
     * Update the session user based on the userId by reading its record from the database and check whether it has
     * admin rights. Return true, if so.
     * @param bool $logViolations whether to log violations.
     * @return bool true if the user is an admin, false otherwise.
     */
    public function isAdminSessionUser(bool $logViolations): bool
    {
        $users = Users::getInstance();
        $runner = Runner::getInstance();
        if (!isset($this->user) || !isset($this->user[$users->userIdFieldName])) {
            $runner->logger->log(LoggerSeverity::WARNING, "Sessions->isAdminSessionUser",
                "Data base manipulation cancelled. User not valid.");
            return false;
        }
        // cache the user record for re-insertion after table reset.
        $userId = $this->userId();
        $appUserRecord = DatabaseConnector::getInstance()->find($users->userTableName, $users->userIdFieldName, $userId);
        if ($appUserRecord === false) {
            if ($logViolations)
                $runner->logger->log(LoggerSeverity::WARNING, $userId,
                    "Data base manipulation prohibited for unknown user '$userId'.");
            return false;
        }
        if (!isset($appUserRecord["role"]) ||
            (strcasecmp($appUserRecord["role"], $users->userAdminRole) != 0)) {
            if ($logViolations)
                $runner->logger->log(LoggerSeverity::WARNING, $userId,
                    "Data base manipulation prohibited for User '" .
                    $appUserRecord[$users->userIdFieldName] . "'. Insufficient access privileges.");
            return false;
        }
        $this->user = $appUserRecord;
        return true;
    }

}
    
