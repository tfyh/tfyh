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

use JetBrains\PhpStorm\NoReturn;

include_once '../../tfyh/Api/Container.php';
include_once '../../tfyh/Api/ResultForContainer.php';
use Api\Container;
use Api\ResultForContainer;

include_once '../../tfyh/Authentication/AuthProvider.php';
use Authentication\AuthProvider;

include_once '../../tfyh/Data/Codec.php';
include_once '../../tfyh/Data/Config.php';
include_once '../../tfyh/Data/DatabaseConnector.php';
include_once '../../tfyh/Data/Ids.php';
include_once '../../tfyh/Data/Record.php';
use Data\Codec;
use Data\Config;
use Data\DatabaseConnector;
use Data\Ids;
use Data\Record;

// internationalisation support on needed to translate the login workflow messages
include_once '../../tfyh/Util/I18n.php';
include_once '../../tfyh/Util/Language.php';
include_once '../../tfyh/Util/MailHandler.php';
include_once '../../tfyh/Util/TokenHandler.php';
use Util\I18n;
use Util\Language;
use Util\MailHandler;
use Util\TokenHandler;


/**
 * The Runner is the main class of the session. It is responsible for the user authentication, session management, Menu
 * control asf. It steers the Logger, User, Session, and Menu classes.
 */
class Runner
{
    private static Runner $instance;
    /**
     * The runner shall be called only after the Monitor has started.
     */
    public static function getInstance(): Runner {
        if (! isset(self::$instance))
                self::$instance = new self();
        return self::$instance;
    }

    public Logger $logger;
    private String $userRequestedAction = "";
    public String $userRequestedFile = "";
    private bool $isUserRequestForForm = false;
    private String $sessionType;

    public String $appRoot = "";
    public String $appDomain = "";
    // in shutdown situations the current working directory may switch to "/"
    public String $workingDirectory = "";

    public bool $debugOn = false;

    public Users $users;
    public Sessions $sessions;
    public Menu $menu;

    public string $fsId = "";
    public int $done = 0;
    public String $tokenTarget = "";

    /**
     * The constructor is private, so that the getInstance() method is used to get an instance of the Runner.
     * The constructor is called only after the Monitor has started.
     */
    private function __construct() {
        $this->workingDirectory = getcwd();
        $this->sessionType = Monitor::getInstance()->getSessionType();
        $this->logger = Monitor::getInstance()->getLogger();
    }

    /**
     * The application holds all executable code in directories at the application root. Multiple applications of such
     * a type may reside in one web server serving different tenants. The session must recognise if the application
     * root was changed to prevent users from using their access rights in any other tenant.
    */
    private function checkContext(): void
    {
        // ===== identify the current context, i.e. the parent directory's parent.
        $cwd = getcwd();
        $subcontext = substr($cwd, 0, strrpos($cwd, "/"));
        $context = substr($subcontext, 0, strrpos($subcontext, "/"));
        $i18n = I18n::getInstance();
        if ($this->debugOn) {
            $sessionContextPrev = (isset($_SESSION["context"])) ? $_SESSION["context"] : "[not available]";
            $this->logger->log(LoggerSeverity::DEBUG, "Runner->checkContext",
                "Session context: $sessionContextPrev, current: $context");
        }
        // ===== add the context, if not yet added and check it.
        if (! isset($_SESSION["context"]))
            $_SESSION["context"] = $context;
        elseif (strcmp($_SESSION["context"], $context) != 0) {
            // wrong tenant. Clear all user settings because they stem from a different tenant.
            $prevContext = $_SESSION["context"];
            $this->sessions->sessionClose($i18n->t("tysZww|Forbidden session contex..."));
            Monitor::getInstance()->scriptCompleted = true;
            $this->displayError($i18n->t("AAJv1G|Forbidden session contex..."),
                $i18n->t("XtZapR|A change from context: %...", $prevContext, $context), $this->userRequestedAction);
        }
    }

    /**
     * The runner provides web request information to the application, based on the requested file. This information is
     * accessible via variables in the global scope through the Runner singleton instance.
     * @param String $userRequestedFile the requested file, e.g. "pages/webApp.php"
     * @return void
     */
    public function setFields(String $userRequestedFile): void {
        // parse the call parameter for later use
        $filePathElements = explode("/", $userRequestedFile);
        $indexLast = count($filePathElements) - 1;
        $this->userRequestedAction = $filePathElements[$indexLast - 2] . "/" . $filePathElements[$indexLast - 1] . "/" . $filePathElements[$indexLast];
        $this->userRequestedFile = $filePathElements[$indexLast];
        $this->isUserRequestForForm = strcmp($filePathElements[$indexLast - 1], "forms") == 0;

        // resolve util root URL for use in scripts.
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $host = $_SERVER['HTTP_HOST'] ?? "localhost";
        $uri = $_SERVER['REQUEST_URI'] ?? $userRequestedFile;
        $this->appDomain = $protocol . "://$host";
        $appRoot = "$protocol://$host$uri"; // e.g. "https://rcwb.de/app/tfyh/forms/login.php"
        // cut off get parameters
        $appRoot = (str_contains($appRoot, "?")) ? substr($appRoot, 0, strrpos($appRoot, "?")) : $appRoot;
        // cut off last three path elements
        $appRoot = substr($appRoot, 0, strrpos($appRoot, "/")); // e.g. "https://rcwb.de/app/tfyh/forms"
        $appRoot = substr($appRoot, 0, strrpos($appRoot, "/")); // e.g.: "https://rcwb.de/app/tfyh/"
        $this->appRoot = substr($appRoot, 0, strrpos($appRoot, "/")); // e.g.: "https://rcwb.de/app"
    }

    /**
     * Form sequence check. Using the superglobal $_GET[ "fSeq"]. Using this random id of any form, different form tabs
     * can be distinguished in a multi-tab user session. Actually, these tokens are generated for all pages, not only
     * forms, but for forms they are crucial to assign the input to the correct tab's transaction.
     * @param string $userRequestedFile the requested file, e.g. "pages/webApp.php". is only used for error messages.
     * @return void
     */
    public function sequenceControl(string $userRequestedFile): void
    {
        if (isset($_GET["fSeq"])) {
            $i18n = I18n::getInstance();
            $seqErrorHead = $i18n->t("sTQcju|Error in sequence of for...");
            $seqErrorText = $i18n->t("usHKvV|An invalid form sequence...");
            $monitor = Monitor::getInstance();
            $monitor->scriptCompleted = true; // for any of the following errors
            if (strlen($_GET["fSeq"]) != 6)
                $this->displayError($seqErrorHead, $seqErrorText, $userRequestedFile);
            $this->done = intval(substr($_GET["fSeq"], 5, 1));
            if ($this->done == 0)
                $this->displayError($seqErrorHead, $seqErrorText, $userRequestedFile);
            $this->fsId = substr($_GET["fSeq"], 0, 5);
            if (! isset($_SESSION["forms"])) {
                $this->displayError($i18n->t("x8hxVv|Timeout due to inactivit..."),
                    $i18n->t("yf8erz|Unfortunately, form proc..."), $userRequestedFile);
            }
            if (! isset($_SESSION["forms"][$this->fsId]))
                $this->displayError($seqErrorHead, $seqErrorText, $userRequestedFile);
            $monitor->scriptCompleted = false; // continued execution
        } else {
            $this->fsId = substr(Ids::generateUid(6), 3); // five-digit token
            $_SESSION["forms"][$this->fsId] = [];
            $_SESSION["get_parameters"][$this->fsId] = [];
        }
        // ===== collect all values of the Get parameter, merge them over all form sequence steps
        foreach ($_GET as $gKey => $gValue)
            $_SESSION["get_parameters"][$this->fsId][$gKey] = $gValue;
    }

    /**
     * Check whether the conditions are met and provide, if so, a one-time password per mail to the user. Returns
     * an error message on failure and an empty String on success. Sets the $_SESSION[ "Registering_user"] global
     * on success.
     * @param String $accountInformation the user's email address, userid, or account name.'
     * @return string the error message or an empty String.
     */
    public function provideOneTimePassword(String $accountInformation): string
    {
        $i18n = I18n::getInstance();
        if (filter_var($accountInformation, FILTER_VALIDATE_EMAIL) === false) {
            $errorMessage = $i18n->t("VMcexT|Please provide an email ...");
            $this->rejectUser(-3, $errorMessage);
        }
        $dbc = DatabaseConnector::getInstance();
        $userToLogin = $dbc->find($this->users->userTableName, $this->users->userMailFieldName, $accountInformation);
        if ($userToLogin === false) {
            $errorMessage = $i18n->t("nNGaVa|User mail unknown.");
            $this->rejectUser(-3, $errorMessage);
        } elseif (strlen($userToLogin["password_hash"]) > 10) {
            // The user has defined a permanent password, then it must be used.
            // He may reset this permanent password to get one-time session tokens.
            $errorMessage = $i18n->t("gvdW5g|If a permanent password ...");
            $errorMessage .= $i18n->t("5fKVft|The permanent password c...");
            $this->rejectUser(-4, $errorMessage);
        } else {
            $otpSenSuccess = $this->sendOneTimePassword($userToLogin);
            $errorMessage = ($otpSenSuccess) ? "" : $i18n->t("H6nWWu|The one time passwrd cou...");
        }
        if (strlen($errorMessage) == 0) {
            $_SESSION["login_failures"] = 0;
            $_SESSION["Registering_user"] = $userToLogin;
        }
        return $errorMessage;
    }

    /**
     * Create a one-time password and send it to the user. Returns the sending success.
     * @param array $userToLogin the user's data as an associative array.'
     * @return bool true on success, false on failure.
     */
    private function sendOneTimePassword(array $userToLogin): bool {
        $i18n = I18n::getInstance();
        $config = Config::getInstance();
        $mailAddress = MailHandler::stripAddressPrefix($userToLogin[$this->users->userMailFieldName]);
        // user has no permanent password, send token.
        $userId = $userToLogin[$this->users->userIdFieldName];
        $tokenHandler = new TokenHandler("../../var/Run/tokens.txt");
        $mailHandler = new MailHandler($config->getItem(".app.mailer"));
        $userIsAnonymous = (strcasecmp($userToLogin["role"], $this->users->anonymousRole) == 0);
        $token = ($userIsAnonymous) ? "" : $tokenHandler->getNewToken($userId);
        // Compile Mail to user.
        $subject = $i18n->t("M4m15E|One-time password for %1...",
            $config->getItem(".framework.app.name")->valueStr(), $token);
        $body = "<p>" . $i18n->t("rtBEhk|Dear %1 %2,",
            $userToLogin[$this->users->userFirstNameFieldName],
            $userToLogin[$this->users->userLastNameFieldName]) . "</p>";
        // user with user rights "anonym" shall not get a token
        if ($userIsAnonymous) {
            $body .= "<p>" . $i18n->t("fZg2zo|The registration must st...") . "<p>";
        } elseif ($token == "---") {
            $body .= "<p>" . $i18n->t("EONiUI|No more one time passwor...") . "<p>";
        } else {
            // user shall get a token
            $body .= "<p>" . $i18n->t("OKImlH|With the one-time passwo...", $token,
                    strval($tokenHandler->tokenValidityPeriod / 60)) . ".<p>";
        }
        $body .= $mailHandler->mailSubscript;
        $body .= "<p>" . $i18n->t("CMC17q|PS: In the user profile,...") . "<p>";
        $body .= $mailHandler->mailFooter;
        return $mailHandler->send_mail($mailHandler->systemMailSender,
            $mailHandler->systemMailSender, $mailAddress, "", "", $subject, $body);
    }

    /**
     * User login with a one-time password. This will check the one-time password for existence and whether it is still
     * valid. If so, the user session is started. Returns an error message on failure and an empty String on success.
     * @param String $oneTimePassword the one-time password.
     * @return String the error message or an empty String.
     */
    public function loginByOneTimePassword(String $oneTimePassword):String {
        $i18n = I18n::getInstance();
        $tokenHandler = new TokenHandler("../../var/Run/tokens.txt");
        $userId = $tokenHandler->getUserId($oneTimePassword);
        if ($userId == -1) {
            $errorMessage = $i18n->t("1HuxvX|The one-time password is...");
            $this->rejectUser(-2, $errorMessage);
            return $errorMessage;
        } else {
            // login successful
            $this->loginUser($userId);
            $_SESSION["login_target"] = "../pages/webApp.php";
            return "";
        }
    }

    /**
     * User login with user and password. $userAccountInformation may be an email address, a userId, or an account name.
     * This will check the account information for existence and verify the permanent password. On verification success,
     * the user session is started. Returns an error message on failure and an empty String on success.
     */
    /**
     * @param String $userAccountInformation the user's email address, userid, or account name.
     * @param String $password the user's password.
     * @return String the error message or an empty String.
     */
    public function loginByCredentials(String $userAccountInformation, String $password): String {

        $i18n = I18n::getInstance();
        $users = Users::getInstance();
        $dbc = DatabaseConnector::getInstance();

        // verify the password length
        if (strlen($password) < 6) {
            $errorMessage = $i18n->t("QDFgHt|No password provided");
            $this->rejectUser(-2, $errorMessage);
            return $errorMessage;
        }

        // retrieve the user from the database
        if (filter_var($userAccountInformation, FILTER_VALIDATE_EMAIL) !== false)
            $userToLogin = $dbc->find($users->userTableName, $users->userMailFieldName, $userAccountInformation);
        elseif (is_numeric($userAccountInformation))
            $userToLogin = $dbc->find($users->userTableName, $users->userIdFieldName, $userAccountInformation);
        else
            $userToLogin = $dbc->find($users->userTableName, $users->userAccountFieldName, $userAccountInformation);
        if ($userToLogin === false)
            $userToLogin = Users::getInstance()->getEmptyUserRow();
        else
            $userToLogin = Record::parseRow($userToLogin, $users->userTableName, Language::SQL);
        $userId = $userToLogin[$users->userIdFieldName];

        // get the password hash. External authentication providers get preference, abort on error.
        $passwordHash = "-";
        $authProviderClassFile = "../Authentication/AuthProvider.php";
        if (file_exists($authProviderClassFile)) {
            include_once $authProviderClassFile;
            $authProvider = new AuthProvider();
            $passwordHash = $authProvider->getPwHash($userToLogin[$users->userIdFieldName]);
        }
        if (isset($userToLogin["password_hash"]) && ($passwordHash == "ignore"))
            $passwordHash = $userToLogin["password_hash"];
        if (strlen($passwordHash) <= 10) {
            $errorMessage = $i18n->t("UkRFrA|Authentication failed no...");
            $this->rejectUser(-2, $errorMessage);
            return $errorMessage;
        }

        // verify the password
        if (password_verify($password, $passwordHash)) {
            $this->loginUser($userId);
            return "";
        } else {
            $errorMessage = $i18n->t("nVVYew|Authentication failed, w...");
            $this->rejectUser(-2, $errorMessage);
            return $errorMessage;
        }
    }

    /**
     * Close the current session (usually for an unauthorised user) and open a new one for this user.
     * @param int $userId the user's id.'
     * @return void
     */
    private function loginUser(int $userId): void {
        $i18n = I18n::getInstance();
        if ($this->sessionType == "web")
            $this->sessions->sessionClose($i18n->t("Vxjyo7|Closing anonymous sessio..."));
        Monitor::getInstance()->monitorActivity($userId, "login");
        $this->sessions->sessionStart($userId);
        $_SESSION["login_failures"] = 0;
    }

    /**
     * Refuse a login due to errors like wrong password or overdue token. This will close the current session.
     * @param int $userId the user's id.
     * @param String $cause the reason for the refusal.
     * @return void
     */
    public function rejectUser(int $userId, String $cause): void {
        $i18n = I18n::getInstance();
        Monitor::getInstance()->monitorActivity($userId, "error");
        if ($this->sessionType == "web")
            $this->sessions->sessionClose($i18n->t("5Ddt11|Closing session due to l..."));
        $this->logger->log(LoggerSeverity::ERROR, "Runner->rejectUser()", $cause);
        if (isset($_SESSION["login_failures"]))
            $_SESSION["login_failures"] = $_SESSION["login_failures"] + 1;
    }

    /**
     * Fetches the current application version from the application product server. Returns the server String or false
     * in case of failure to get the version.
     * @param String $event the event that triggered the version check for server side logging purposes.
     * @return String|bool the server version or false on failure.
     */
    public function getCurrentApplicationVersion(String $event): String|bool {
        $config = Config::getInstance();
        $upgradePath = $config->getItem(".framework.app.upgrade_url")->valueStr();
        return file_get_contents($upgradePath . "/getVersion.php?" .
            "own=". $config->appVersion . "&app=" . $config->appName . "&by=" . $this->appRoot .
            "&event=$event");
    }

    /**
     * Return the page start including the correct html lang attribute and the user menu.
     */
    public function pageStart(): String {
        $html = file_get_contents('../../Config/snippets/page_01_start');
        $html = str_replace("{lang}", Config::getInstance()->language()->value, $html);
        $html .= $this->menu->getMenu();
        $html .= file_get_contents('../../Config/snippets/page_02_nav_to_body');
        return $html;
    }

    /**
     * Run all checks and set all settings at script execution start.
     * @param String $userRequestedFile the file that was requested by the user.
     * @return void
     */
    public function startScript(String $userRequestedFile): void
    {
        // ===== init.php must have initialised Config and Monitor
        $config = Config::getInstance();
        $monitor = Monitor::getInstance();
        $isApi = ($this->sessionType == "api");

        // ===== load the configuration
        $this->setFields($userRequestedFile);
        $config->load();
        $this->debugOn = $config->getItem(".app.operations.debug_on")->value();

        // ===== initialize the internationalization support
        $i18n = I18n::getInstance();
        $i18n->loadResource($config->language());
        $this->logger->log(LoggerSeverity::INFO, "Runner->startScript",
            "Starting script execution for " . $this->userRequestedAction);

        // ===== initialize the database connector.
        $dbc = DatabaseConnector::getInstance();
        $connected = $dbc->open();
        if ($connected !== true) {
            if ($isApi) {
                echo ResultForContainer::SERVER_ERROR->value . ";Database connection refused.";
                $this->endScript(false);
            } else
                $this->displayError("Database connection failed.",
                    "Unable to connect to the database '". $dbc->dbName() ."', please ask your admin for help.",
                    $userRequestedFile);
        }

        $this->users = Users::getInstance();
        $this->sessions = Sessions::getInstance($this->sessionType);
        // start or resume the API session
        if ($this->sessionType == "api") {
            $container = Container::getInstance();
            if (strlen($container->txc["sessionId"]) < 35) {
                // api session Ids are 41 characters long, the password length is limited to 32 characters
                $errorMessage = $this->loginByCredentials($container->txc["userId"], $container->txc["sessionId"]);
                // return an error message and exit on authentication failure
                if (strlen($errorMessage) > 0) {
                    $container->txc["containerResultCode"] = ResultForContainer::AUTHENTICATION_FAILED->value;
                    $container->txc["containerResultMessage"] = $errorMessage;
                    $this->logger->log(LoggerSeverity::ERROR, "Runner->startScript",
                        "Login failed for " . $container->txc["userId"] . " because of " . $errorMessage);
                    $container->sendResponseAndExit();
                } else {
                    $container->txc["containerResultCode"] = ResultForContainer::REQUEST_AUTHENTICATED->value;
                    $container->txc["containerResultMessage"] = ResultForContainer::text(
                        ResultForContainer::REQUEST_AUTHENTICATED->value);
                }
            } else {
                $sessionOk = $this->sessions->sessionVerifyAndUpdate($container->txc["userId"],
                    $container->txc["sessionId"]);
                if (!$sessionOk) {
                    $container->txc["containerResultCode"] = ResultForContainer::AUTHENTICATION_FAILED->value;
                    $container->txc["containerResultMessage"] = "The session id could not be verified.";
                    $this->logger->log(LoggerSeverity::ERROR, "Runner->startScript",
                        "Login failed for " . $container->txc["userId"] . "because of " .
                        $container->txc["containerResultMessage"]);
                    $container->sendResponseAndExit();
                } else {
                    $container->txc["containerResultCode"] = ResultForContainer::REQUEST_AUTHENTICATED->value;
                    $container->txc["containerResultMessage"] = ResultForContainer::text(
                        ResultForContainer::REQUEST_AUTHENTICATED->value);
                }
            }
        } else {
            // ===== Start or resume the web the session
            $sessionStarted = $this->sessions->sessionStart(-1);
            if (!$sessionStarted)
                $this->displayError(Sessions::$tooManySessionsErrorHeadline,
                    $i18n->t("ATSnFO|There are too many users..."), $userRequestedFile);
        }
        $monitor->monitorActivity($this->sessions->userId(), ($isApi ? "api" : "init"));

        // ===== load the menu
        $userId = $this->sessions->userId();
        $userRole = $this->sessions->userRole();
        $accessType = (($this->sessions->sessionType() == "api")) ? "api" :
            ((strcasecmp($userRole, $this->users->anonymousRole) == 0) ? "public" : "identified");
        if ($this->debugOn)
            $this->logger->log(LoggerSeverity::DEBUG, "Runner->startScript",
                "User after DB check: appUserID: $userId, role: $userRole, access type: $accessType.");
        $this->menu = new Menu($accessType);

        // Use this to trigger daily jobs. It will only be performed once per day, so the performance
        // impact is low. If the API is used, also api login will tri
        if ($userId >= 0)
            CronJobs::runDailyJobs();

        // exit here if this is an API request. The following checks apply for web access only.
        if ($isApi)
            return;

        // ===== check the context continuity for the initialised session.
        $this->checkContext();

        // ===== control the form sequence, except for calls of the jsGet.php page or the api.
        $isJsGet = $this->userRequestedAction === "../../tfyh/pages/jsGet.php";
        if (! $isJsGet) {
            if (!$this->isUserRequestForForm && ($userId == -1)) {
                // drop web session if an anonymous user requests anything different from a form.
                $this->sessions->sessionClose(
                    $i18n->t("CW7uhM|anonymous request for no...", $this->userRequestedAction));
                $this->fsId = "";
                $this->done = 0;
            } else
                $this->sequenceControl($userRequestedFile);
        }

        // ===== change session role, if in test mode
        if (isset($_SESSION["User_test_role"]) &&
            $this->menu->isAllowedRoleChange($userRole, $_SESSION["User_test_role"]))
                $this->sessions->modifyUserRole($_SESSION["User_test_role"]);
        else
            unset($_SESSION["User_test_role"]);

        // ===== authorize user for action
        if (! $this->menu->isAllowedMenuItem($userRequestedFile) && ! $isJsGet) {
            $monitor->scriptCompleted = true;
            if (strcasecmp($this->users->anonymousRole, $userRole) == 0)
                $this->displayError($i18n->t("Wkz0N4|Session terminated."),
                    $i18n->t("EGWtVL|The session was terminat..."), $userRequestedFile);
            else
                $this->displayError($i18n->t("lTNFEv|Not allowed."),
                    $i18n->t("D7SPTM|The role °%1° has no per...", $userRole,
                        $this->userRequestedAction), $userRequestedFile);
        }
        if ($this->debugOn)
            $this->logger->log(LoggerSeverity::DEBUG, "Runner->startScript", "Script successfully started.");
    }

    /**
     * Get a "span" DOM element with the user information for the JavaScript program part.
     */
    public function user2js(): String {
        $sessionUserCsv = "userId;firstName;lastName;uuid;role;workflows;subscriptions;concessions;preferences\n";
        $sessionUserCsv .= $this->sessions->userId() . ";"
            . Codec::encodeCsvEntry($this->sessions->userFirstName()) . ";"
            . Codec::encodeCsvEntry($this->sessions->userLastName()) . ";"
            . $this->sessions->userWorkflows() . ";" . $this->sessions->userConcessions() . ";"
            . $this->sessions->userSubscriptions() . ";"
            . Codec::encodeCsvEntry($this->sessions->userPreferences());
        return "<span id='session_user' style='display:none'>$sessionUserCsv</span>";
    }
    /**
     * Do all closing actions when ending the script, i.e. close the database connection and echo the footer at the
     * end of the page echoed.
     */
    #[NoReturn] function endScript (bool $addFooter = true): void
    {
        if ($addFooter)
            echo file_get_contents('../../Config/snippets/page_03_footer');
        DatabaseConnector::getInstance()->close();
        if ($this->debugOn)
            $this->logger->log(LoggerSeverity::DEBUG, "endScript","script closed at " . date("Y-m-d H:i:s"));
        $monitor = Monitor::getInstance();
        $monitor->monitorResponseTime($this->sessions->userId(), $this->userRequestedAction);
        $monitor->scriptCompleted = true;
        exit();
    }

    /**
     * Displays an error message and redirects the user to an error page. Logs the error details
     * to a file and monitors the error activity. If the redirect fails, the method outputs a plain
     * error message and terminates execution.
     *
     * @param string $errorHeadline The headline of the error message to display.
     * @param string $errorText The detailed description of the error to display.
     * @param string $callingPage The name or path of the page from which the error originated.
     * @return void This method does not return anything as it redirects or terminates execution.
     */
    public function displayError (String $errorHeadline, String $errorText, String $callingPage): void
    {
        // no endless error loop.
        if (strrpos($callingPage, "error.php") !== false)
            return;
        $get_params = "-";
        if (count($_GET) > 0) {
            foreach ($_GET as $key => $value)
                $get_params .= $key . "=" . $value . "&";
            $get_params = mb_substr($get_params, 0, mb_strlen($get_params) - 1);
        }
        file_put_contents("../../var/Run/lastError.txt",
            explode(";", $callingPage)[0] . ";" . $errorHeadline . ";" . $errorText . ";" . $get_params);
        Monitor::getInstance()->monitorActivity(Sessions::getInstance()->userId(), "error");
        header("Location: ../../tfyh/pages/error.php");
        // if the header statement above fails, display plain error.
        echo "<h1>Error:</h1><h2>" . $errorHeadline . "</h2><p>" . $errorText . "</p>";
        exit(); // really exit. No test case left over.
    }

    // if the script end was not reached, which happens typically in file download scripts, but also
    // in error cases, shut down the database connection.
    function shutdown (): void
    {
        $monitor = Monitor::getInstance();
        if ($monitor->scriptCompleted)
            return;
        if (DatabaseConnector::isOpen())
            DatabaseConnector::getInstance()->close();
        $i18n = I18n::getInstance();
        $error = error_get_last();
        // in shutdown situations the current working directory may switch to "/"
        chdir($this->workingDirectory);
        if (($error !== NULL) && isset($error["type"]) && (intval($error["type"]) == E_ERROR)) {
            $message = "File : " . $error["file"] . ", Line : " . $error["line"] . ", Message : " . $error["message"];
            $this->logger->log(LoggerSeverity::ERROR, "shutdown", "Fatal error: " . $message);
            echo "<h1>" . $i18n->t("Pj5VdW|Oops! A fatal error.") . "</h1><p>" . str_replace("#", "<br>#", $message) .
                ".</p><p>" . $i18n->t("IGCugZ|Please help to improve t...") . "</p>";
        }
        $this->logger->log(LoggerSeverity::ERROR, "shutdown",
            "Shutting down " . $this->userRequestedAction . ". Script runtime " .
            intval(1000 * (microtime(true) - $monitor->scriptStartedOn)) . "ms");
    }
}
