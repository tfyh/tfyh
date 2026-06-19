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

use tfyh\api\Transactions;
use tfyh\util\ListHandler;
use tfyh\util\Pdf;
use tfyh\data\Config;
use tfyh\data\DatabaseConnector;
use tfyh\data\Formatter;
use tfyh\data\ParserName;

/**
 * Class file for the security concept generation. The security concept may be used for privacy and security audits.
 */
class SecurityMonitor
{
    private array $variables;

    /**
     * public Constructor.
     */
    public function __construct() {}

    /**
     * Prepare the variables as needed by the PDF template of the security concept.
     */
    private function prepareVariables(): void
    {
        $this->variables = [];
        $config = Config::getInstance();
        // provided by the application
        $this->variables["printedOn"] = Formatter::format(new DateTimeImmutable("now"), ParserName::DATETIME);
        $this->variables["hostUrl"] = $_SERVER['HTTP_HOST'];
        $this->variables["appVersion"] = $config->appVersion;

        // tenant information and session control shall be added directly from the configuration

        // PHP
        $this->variables["phpVersion"] = phpversion();
        if (strlen($this->variables["phpVersion"]) == 0)
            $this->variables["phpVersion"] = "0.0.0";
        $phpExtensions = get_loaded_extensions();
        $this->variables["phpExtensions"] = "";
        foreach ($phpExtensions as $phpExtension)
            $this->variables["phpExtensions"] .= $phpExtension . ", ";
        if (strlen($this->variables["phpExtensions"]) == 0)
            $this->variables["phpExtensions"] = "-";

        // database
        $this->variables["mySqlVersion"] = DatabaseConnector::getInstance()->serverInfo();
        $this->variables["dbUserPwLength"] = DatabaseConnector::getInstance()->pwLength();

        // access allowance anonymous user: web
        $audit_menu = new Menu("public");
        $this->variables["accessibleWebAnonymous"] = $audit_menu->getAllowanceProfileHtml();
        // access allowance per role: web
        $audit_menu = new Menu("identified");
        $this->variables["accessibleWebPerRole"] = $audit_menu->getAllowanceProfileHtml();
        // access allowance per role: api
        $audit_menu = new Menu("api");
        $this->variables["accessibleApiPerRole"] = $audit_menu->getAllowanceProfileHtml();

        // activities at web interface: init, login, errors
        $monitor = Monitor::getInstance();
        $activities_table = $monitor->activitiesToHtml();
        $this->variables["activitiesWeb"] = $activities_table . "</table>";

        // activities at api interface: init, login, errors
        $this->variables["activitiesApi"] = Transactions::getLastAccessesApi();

        // all changes pivot
        $changesAll = "<table><tr><th>Author</th><th>Modifications: Count</th></tr>";
        $changesList = new ListHandler("administration", "changelog");
        $changesTable = [];
        $changesRecords = $changesList->getRows("csv");
        foreach ($changesRecords as $changesRecord) {
            $author = $changesRecord["author"];
            $modificationType = explode(":", $changesRecord["modification"], 2)[0];
            if ((strcasecmp($modificationType, "updated") != 0) &&
                (strcasecmp($modificationType, "deleted") != 0))
                $modificationType = "inserted";
            if (!isset($changesTable[$author]))
                $changesTable[$author] = [];
            if (!isset($changesTable[$author][$modificationType]))
                $changesTable[$author][$modificationType] = 1;
            else
                $changesTable[$author][$modificationType]++;
        }

        foreach ($changesTable as $author => $modification_types) {
            $changesAll .= "<tr><td>$author</td><td>";
            foreach ($modification_types as $modificationType => $count) {
                $changesAll .= $modificationType . ": " . $count . ", ";
            }
            $changesAll .= "</td></tr>";
        }
        $changesAll .= "</table>";
        $this->variables["changesAll"] = $changesAll;

        // the named privileged users
        $privilegedList = new ListHandler("administration", "privileged");
        $privilegedRecords = $privilegedList->getRows("csv");
        $privilegedStr = "";
        foreach ($privilegedRecords as $privilegedRecord)
            $privilegedStr .= $privilegedRecord["role"] . ": (" . $privilegedRecord["user_id"] . ") "
                . $privilegedRecord["first_name"] . " " . $privilegedRecord["first_name"] . "<br>";
        $this->variables["privilegedUsers"] = $privilegedStr;

        // an application audit log copy
        $this->variables["auditLog"] = str_replace("\n", "<br>",
            str_replace("  ", " &nbsp;",
                str_replace(">", "&gt;",
                    str_replace("<", "&lt;",
                        str_replace("&", "&amp;", file_get_contents("../../var/Log/app_audit.log"))))));
    }

    /**
     * Create the security concept Html representation for display or PDF creation.
     * @return string the HTML representation of the security concept.
     */
    public function create_HTML(): string
    {
        $this->prepareVariables();
        $template = "security_concept";
        $appName = Config::getInstance()->appName;
        $language = Config::getInstance()->language()->value;
        $templatePath = "../../$appName/Templates/$language/$template.html";
        $templateHtml = file_get_contents($templatePath);
        if ($templateHtml === false)
            return "Template for security concept not found in $templatePath";

        // replace configuration item references
        $config = Config::getInstance();
        while (str_contains($templateHtml, "{#.")) {
            $posStart = strpos($templateHtml, "{#.");
            $posEnd = strpos($templateHtml, "#}", $posStart);
            $path = substr($templateHtml, $posStart + 2, $posEnd - $posStart - 2);
            $replaceBy = $config->getItem($path)->valueStr();
            $templateHtml = str_replace("{#$path#}", $replaceBy, $templateHtml);
        }

        // replace variable references
        foreach ($this->variables as $key => $value) {
            $templateHtml = str_replace("{#" . $key . "#}", $value, $templateHtml);
        }
        return $templateHtml;
    }

    /**
     * Create the security concept PDF file. Return the file path to get it.
     * @return string the file path to the PDF file.
     */
    public function create_PDF(): string
    {
        include_once "../Util/Pdf.php";
        $pdf = new Pdf();
        $templateHtml = $this->create_HTML();
        $savedOn = $pdf->createPdfFromHtml($templateHtml, "Security Concept", "Security Concept");
        Runner::getInstance()->logger->log(LoggerSeverity::INFO, "SecurityMonitor->create_PDF",
            "Security concept created.");
        return $savedOn;
    }
}
    
