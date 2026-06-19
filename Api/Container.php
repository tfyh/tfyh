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

use JetBrains\PhpStorm\NoReturn;

use tfyh\control\LoggerSeverity;
use tfyh\control\Monitor;
use tfyh\control\Runner;
use tfyh\control\Sessions;
use tfyh\data\Codec;

/**
 * A singleton class to handle the transaction container.
 */
class Container
{

    /**
     * The message separator String and its replacement, if hit within a transaction, as well as csv special
     * characters.
     */
    private static string $ms = "\n-#|#-\n";

    private static string $msr = "\n_#|#_\n";

    /**
     * The API versions supported. Version 1 through 3 are for efaCloud usage.
     */
    private static array $apiSupportedVersions = [4
    ];
    private static Container $instance;
    /**
     * the currently handled request container.
     */
    public array $txc;
    /**
     * @var array the transactions of currently handled transaction container.
     */
    public array $txs;

    /**
     * @param string $txcBase64Api the base64-encoded transaction container.
     * @return String the error message, if any, or an empty String if parsing was successful.
     */
    public function parseRequest(string $txcBase64Api): string
    {
        // create container header array
        $this->txc = [];
        $this->txc["version"] = 0;
        $this->txc["containerId"] = 0;
        $this->txc["userId"] = 0;
        $this->txc["sessionId"] = 'none';
        $this->txc["containerResultCode"] = 60;
        $this->txc["containerResultMessage"] = 'Syntax error in request.';
        $this->txs = [];

        if (($txcBase64Api == null) || (strlen($txcBase64Api) < 5)) {
            $this->txc["containerResultCode"] = ResultForContainer::SYNTAX_ERROR->value;
            $this->txc["containerResultMessage"] = 'Transaction container missing, empty or too short.';
            return $this->txc["containerResultMessage"];
        }

        // decode base64-API and split into header and requests
        $txcPlain = base64_decode(
            str_replace("_", "=", str_replace("-", "/", str_replace("*", "+", $txcBase64Api))));
        $cElements = explode(";", $txcPlain, 5);
        // check container syntax
        if (count($cElements) != 5) {
            $this->txc["containerResultCode"] = ResultForContainer::SYNTAX_ERROR->value;
            $this->txc["containerResultMessage"] .= 'Decoded transaction container has too few elements: ' .
                count($cElements);
            return $this->txc["containerResultMessage"];
        }

        // check API version
        $apiRequestVersion = intval($cElements[0]);
        if (!in_array($apiRequestVersion, self::$apiSupportedVersions)) {
            $this->txc["containerResultCode"] = ResultForContainer::API_VERSION_NOT_SUPPORTED->value;
            $this->txc["containerResultMessage"] = "API version $apiRequestVersion of container not supported.";
            return $this->txc["containerResultMessage"];
        }

        // check container and user Ids
        $cID = intval($cElements[1]);
        $userId = intval($cElements[2]);
        $sessionId = $cElements[3];
        $this->txc["version"] = $apiRequestVersion;
        $this->txc["containerId"] = $cID;
        $this->txc["userId"] = $userId;
        if ($this->txc["containerId"] * $this->txc["userId"] == 0) {
            $this->txc["containerResultCode"] = ResultForContainer::SYNTAX_ERROR->value;
            $this->txc["containerResultMessage"] .= "container Id or user Id are either not numeric or missing.";
            return $this->txc["containerResultMessage"];
        }

        // container syntax checks completed
        $this->txc["sessionId"] = $sessionId;
        $this->txc["containerResultCode"] = 20;
        $this->txc["containerResultMessage"] = "Syntax ok. User to be verified.";

        // parse requests and add them to the container array
        $txcRequests = explode(self::$ms, $cElements[4]);
        $this->txs = [];
        foreach ($txcRequests as $txRequest) {
            // split request header and record
            $elements = str_getcsv($txRequest, ";");
            $tx = [];
            if ((count($elements) < 3) || (count($elements) % 2 != 1)) {
                // Note: this check above is different from efaCloud, since the retry field is no more
                // provided. It was ((count($elements) < 4) || (count($elements) % 2 != 0)).
                $tx["transactionId"] = (isset($elements[0])) ? $elements[0] : "0";
                $tx["type"] = (isset($elements[1])) ? $elements[1] : "parsingError";
                $tx["tableName"] = (isset($elements[2])) ? $elements[2] : "undefined";
                $tx["resultCode"] = 60;
                $tx["resultMessage"] = "invalid count of parameters in transaction request: " .
                    count($elements);
            } else {
                $tx["transactionId"] = $elements[0];
                $tx["type"] = $elements[1];
                $tx["tableName"] = $elements[2];
                $tx["resultCode"] = 0;
                $tx["resultMessage"] = "not yet parsed nor processed";
                $txRecord = [];
                for ($i = 3; $i < count($elements); $i = $i + 2)
                    $txRecord[$elements[$i]] = $elements[$i + 1];
                $tx["record"] = $txRecord;
            }
            $this->txs[] = $tx;
        }
        return "";
    }

    /**
     * Take the current transaction container, build an appropriate response container from it, and send it
     * back. Transactions must be handled before building the response. The response will have the same
     * version as the request. If the container result code indicates an error, all transactions get as well an
     * error result code and are logged as dropped.
     */
    #[NoReturn]
    public function sendResponseAndExit(): void
    {
        $runner = Runner::getInstance();
        $runner->logger->log(LoggerSeverity::DEBUG, "sendResponseAndExit",
            "Sending response " . self::containerToLog());
        // log errors
        $userId = Sessions::getInstance()->userId();
        if (intval($this->txc["containerResultCode"]) >= 40) {
            $runner->logger->log(LoggerSeverity::ERROR, "sendResponseAndExit",
                "Container failed for user " . $userId . ". Result code: " . $this->txc["containerResultCode"] .
                ". Result message: " . $this->txc["containerResultMessage"]);
            self::logDroppedTransactions();
        }
        // add quotation for the result message
        $containerResultMessage = $this->txc["containerResultMessage"];
        $containerResultMessage = str_replace(self::$ms, self::$msr, $containerResultMessage);
        if ((str_contains($containerResultMessage, ";")) || (str_contains($containerResultMessage, '"')))
            $containerResultMessage = '"' . str_replace('"', '""', $containerResultMessage) . '"';
        // Build the response container plain text
        $response = $this->txc["version"] . ";" . $this->txc["containerId"] . ";" . $this->txc["containerResultCode"] . ";" .
            $containerResultMessage . ";";
        for ($i = 0; $i < count($this->txs); $i++) {
            $response .= $this->txs[$i]["transactionId"] . ";" . $this->txs[$i]["resultCode"] . ";";
            $resultMessage = $this->txs[$i]["resultMessage"];
            $resultMessage = str_replace(self::$ms, self::$msr, $resultMessage);
            // the result message neither needs utf-8 encoding (the values are already encoded) nor
            // csv encoding (the values are as well already appropriately quoted).
            $response .= $resultMessage . self::$ms;
        }
        if (count($this->txs) > 0)
            $response = substr($response, 0, strlen($response) - strlen(self::$ms));
        // encode the container and measure its length
        $response = Codec::apiEncode($response);
        $contentSize = (isset($_SERVER['CONTENT_LENGTH'])) ? intval($_SERVER['CONTENT_LENGTH']) : 0;
        $this->logContentSize($contentSize, strlen($response), $userId);
        if ($runner->debugOn)
            $runner->logger->log(LoggerSeverity::DEBUG, "sendResponseAndExit",
                "Starting streaming of " . strlen($response) . " characters.");
        // echo to send the response
        echo $response;
        // debug logging and exit
        $timestampContainer = (count($this->txs) == 1) ? "api/" .
            strtolower($this->txs[0]["type"]) : "api/multiple";
        $monitor = Monitor::getInstance();
        $monitor->monitorActivity($userId, $timestampContainer);
        if ($runner->debugOn)
            $runner->logger->log(LoggerSeverity::DEBUG, "sendResponseAndExit",
                "Sending completed.");
        $runner->endScript(false);
    }

    /**
     * Parse a request container according to the API format definition and put it to the $this->txc
     * variable for further processing. The header is checked for a version, containerID, user and password
     * entry, and at least one transaction. All these elements must be present, the first three numeric. If an error
     * occurs, parsing stops and returns the error description, which is also put to $this->txc[ "containerResultMessage"].
     * If all is fine, it returns an empty String.
     */

    /**
     * @return Container the singleton instance of the Container class.
     */
    public static function getInstance(): Container
    {
        if (!isset(self::$instance))
            self::$instance = new Container();
        return self::$instance;
    }

    /**
     * Return a string with all transaction container information for logging
     */
    private function containerToLog(): string
    {
        $logString = "version:" . $this->txc["version"] . ", ";
        $logString .= "cID:" . $this->txc["containerId"] . ", ";
        $logString .= "userID:" . $this->txc["userId"] . ", ";
        $logString .= "password (length):" . strlen($this->txc["sessionId"]) . ", ";
        $logString .= "containerResultCode:" . $this->txc["containerResultCode"] . ", ";
        $logString .= "containerResultMessage:" . $this->txc["containerResultMessage"];
        return $logString;
    }

    /**
     * This method logs the dropped transactions of a failed container.
     */
    private function logDroppedTransactions(): void
    {
        $logger = Runner::getInstance()->logger;
        for ($i = 0; $i < count($this->txs); $i++) {
            $this->txs[$i]["resultCode"] = ResultForTransaction::CONTAINER_ERROR->value;
            $this->txs[$i]["resultMessage"] = "Transaction dropped due to container error";
            $logger->log(LoggerSeverity::INFO, "logDroppedContainerTransactions",
                "Transaction dropped due to container error: " .
                self::transactionToLog($i, false));
        }
    }

    /**
     * Print a log string with all transaction information for logging
     * @param int $i the index of the transaction to log
     * @param bool $withMessageAndRecord if true, the record is also logged, otherwise only the record length and the
     * result message length are logged.
     * @return string the log string
     */
    public function transactionToLog(int $i, bool $withMessageAndRecord): string
    {
        $txRequest = $this->txs[$i];
        $logString = "(V" . $this->txc["version"] . ") ";
        $logString .= "client:" . $this->txc["userId"] . ", ";
        $logString .= "ID:" . $txRequest["transactionId"] . ", ";
        $logString .= "type:" . $txRequest["type"] . ", ";
        // for the list transaction the table name is the list name, which is plain text rather than a
        // technical name.
        $logString .= "tableName:" . $txRequest["tableName"] . ", ";
        $logString .= "resultCode:" . $txRequest["resultCode"] . ", ";
        if ($withMessageAndRecord) {
            $contents = json_encode($txRequest["record"]);
            if (strlen($contents) > 1000)
                $contents = substr($contents, 0, 997) . "...";
            $logString .= "record:" . json_encode($contents) . " // ";
            $logString .= "resultMessage:" . str_replace("\n", " // ", trim($txRequest["resultMessage"]));
        } else {
            $logString .= "record length:" .
                ((isset($txRequest["record"])) ? count($txRequest["record"]) : 0);
            $logString .= ", resultMessage length:" . strlen($txRequest["resultMessage"]);
            $logString .= ", lines:" . count(explode("\n", trim($txRequest["resultMessage"])));
        }
        return $logString;
    }

    /**
     * Add the request and response sizes of a transaction to the statistics on the exchanged content size for those
     * who have bandwidth limitations.
     */
    private function logContentSize(int $requestSize, int $responseSize, int $userId): void
    {
        // create a new directory for a new client, if necessary
        $sizeFilename = "../../var/Run/contentSize";
        if (!file_exists($sizeFilename))
            mkdir($sizeFilename);
        $sizeFilename .= "/" . $userId;

        // read the collected size statistics for the last 14 days
        $today = date("Y-m-d");
        $latestRecent = time() - 1209600; // 14 days in seconds
        if (file_exists($sizeFilename))
            $sizes = Codec::csvFileToMap($sizeFilename);
        else
            $sizes = [];

        // add the current transaction to the statistics of this day
        $todayIn = false;
        $start = (count($sizes) > 14) ? count($sizes) - 14 : 0;
        for ($i = $start; $i < count($sizes); $i++) {
            $size = $sizes[$i];
            if ($sizes[$i]["Date"] == $today) {
                $sizes[$i]["requests"] = strval(intval($sizes[$i]["requests"]) + 1);
                $sizes[$i]["requestSize"] = strval(intval($sizes[$i]["requestSize"]) + $requestSize);
                $sizes[$i]["responseSize"] = strval(intval($sizes[$i]["responseSize"]) + $responseSize);
                $todayIn = true;
            }
        }
        // if for this day no statistic existed, create a new day
        if (!$todayIn) {
            $size["Date"] = $today;
            $size["requests"] = 1;
            $size["requestSize"] = $requestSize;
            $size["responseSize"] = $responseSize;
            $sizes[] = $size;
        }

        // write the updated statistics file
        $out = "Date;requests;requestSize;responseSize\n";
        foreach ($sizes as $size) {
            if (strtotime($size["Date"]) >= $latestRecent)
                $out .= $size["Date"] . ";" . $size["requests"] . ";" . $size["requestSize"] . ";" .
                    $size["responseSize"] . "\n";
        }
        file_put_contents($sizeFilename, $out);
    }

}
