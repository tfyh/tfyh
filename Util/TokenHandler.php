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

namespace tfyh\util;

use tfyh\control\LoggerSeverity;
use tfyh\control\Runner;
use tfyh\data\Ids;
use const tfyh\data\base64charsPlus;

/**
 * A utility class to create one-time tokens for user identification.
 */

class TokenHandler
{

    private const obfuscator = "jtzOjk6IjEyNy4wLjAuMSI7czoxMToiZGJfYWNjb3VudHMiO2E6MTp7czo0OiJyb290IjtzOjg6IlNmeDFubHAuIjt9czo3OiJkYl9uYW1lIjtzOjU6ImZ2c3NiIjtzO";

    /**
     * Obfuscate a base64 String by xor-operation with an obfuscating String. Apply the same procedure to restore it.
     * @param String $plainBase64 the base64 String to obfuscate
     * @return string
     */
    public static function obfuscate (String $plainBase64): string
    {
        $bitsForChar64 = [];
        $charsForBits64 = [];
        for ($b = 0; $b < 65; $b ++) {
            $character = substr(base64charsPlus, $b, 1);
            $charsForBits64[$b] = $character;
            $bitsForChar64[$character] = $b;
        }
        $xor64 = "";
        // the key must not contain a padding character ('=')
        $kLen = strlen(self::obfuscator);
        $pLen = strlen($plainBase64);
        $k = 0;
        for ($p = 0; $p < $pLen; $p ++) {
            $ki = $bitsForChar64[substr(self::obfuscator, $k, 1)];
            $pi = $bitsForChar64[substr($plainBase64, $p, 1)];
            // do not xor the padding part.
            if ($pi == 64)
                $xor64 .= "=";
            else
                $xor64 .= $charsForBits64[$pi ^ $ki];
            $k ++;
            if ($k == $kLen)
                $k = 0;
        }
        return $xor64;
    }

    /**
     * Encode the timestamp + validity and the user Mail to create a user login token. It will have the user
     * mail in the middle, braced by two changing parts, the timestamp, and padding. The result will be a
     * base64 encoded String in which three characters are replaced to be URL-compatible: "=" by "_",
     * "/" by "-", "+" by "*".
     * @param String $userMail the user mail address
     * @param int $validity the validity period in days
     * @param String $deepLink the deep link to be used for login
     * @return string the login token
     */
    public static function createLoginToken (String $userMail, int $validity, String $deepLink): string
    {
        $message = (microtime(true) + $validity * 24 * 3600) . "::" . $userMail . "::" . $deepLink . "::" .
            substr(str_shuffle(base64charsPlus), 0, 16);
        Runner::getInstance()->logger->log(LoggerSeverity::INFO, "DilboIds::createLoginToken",
            "created: " . $message);
        return str_replace("=", "_",
            str_replace("/", "-",
                str_replace("+", "*", self::obfuscate(base64_encode($message)))));
    }

    /**
     * Decode the user login token and validate it. Returns an array [valid until, user mail, deep link] or false, if
     * the token is no longer valid.
     * @param String $token the login token
     * @return array|bool the array [valid until, user mail, deep link] or false, if the token is no longer valid.
     */
    public static function decodeLoginToken (String $token): array|bool
    {
        $plainText = explode("::",
            base64_decode(
                self::obfuscate(str_replace("_", "=",
                    str_replace("-", "/", str_replace("*", "+", $token))))));
        if (intval($plainText[0]) >= microtime(true))
            return $plainText;
        else
            return false;
    }

    /**
     * file name to which tokens are written
     */
    private String $tokenFile;

    /**
     * Validity period of a token in seconds
     */
    public int $tokenValidityPeriod = 1200;

    /**
     * Monitoring period of all tokens used to check whether a user has too many tokens created.
     */
    private int $tokenMonitorPeriod = 86400;

    // tokens are monitored a full day
    /**
     * Maximum count of tokens a user can get per monitoring period.
     */
    private int $tokensAllowedInMonitorPeriod = 10;

    public function __construct ($tokenFile) { $this->tokenFile = $tokenFile; }

    /**
     * Get a new token for the user. Returns "---" if the maximum number of tokens per user and day is
     * exceeded.
     * @param int $userId the id of the user for whom the token is requested.
     * @return string the new token.
     */
    public function getNewToken (int $userId): string
    {
        $usersTokenCount = $this->cleanseTokens($userId);
        if ($usersTokenCount >= $this->tokensAllowedInMonitorPeriod)
            return "---";
        $token = substr(strtoupper(Ids::generateUid(6)), 0, 6);
        if ($userId >= 0) {
            $nowSeconds = microtime(true);
            $contents = $token . ";" . $nowSeconds . ";" . $userId;
            file_put_contents($this->tokenFile, $contents, FILE_APPEND);
        }
        return $token;
    }

    /**
     * Remove all overdue tokens. Returns the count of tokens of the user with the $userId. Set $userId == 0 to count
     * all tokens.
     * @param int $userId the id of the user whose tokens are to be cleaned. Use userId == 0 to clean up for all users.
     * @return int the count of tokens of the user with the $userId.
     */
    private function cleanseTokens (int $userId): int
    {
        // read session file, split lines and check one by one
        $tokenFileIn = file_get_contents($this->tokenFile);
        $tokenFileLines = explode("\n", $tokenFileIn);
        $tokenFileOut = "";
        $nowSeconds = microtime(true);
        $usersTokensCount = 0;
        foreach ($tokenFileLines as $line) {
            $tokenParts = explode(";", $line);
            if (count($tokenParts) >= 3) {
                $period = $nowSeconds - intval($tokenParts[1]);
                if ($period < $this->tokenMonitorPeriod) {
                    // keep token if it is within the monitoring period.
                    $tokenFileOut .= $line . "\n";
                    if (($userId == intval($tokenParts[1])) || ($userId == 0))
                        $usersTokensCount++;
                }
            }
        }
        // write cleansed file.
        file_put_contents($this->tokenFile, $tokenFileOut);
        return $usersTokensCount;
    }

    /**
     * Retrieves the user ID associated with the given token.
     *
     * This method checks the validity of the provided token by comparing it
     * against stored tokens to determine if the user session is still active.
     *
     * @param string $token The session token to be validated and used to retrieve the user ID.
     * @return int Returns the user ID if the token is valid, or -1 if the token is invalid or expired.
     */
    public function getUserId (string $token): Int
    {
        $this->cleanseTokens(0);
        // read token file, split lines and check one by one
        $tokenFileIn = file_get_contents($this->tokenFile);
        $tokenFileLines = explode("\n", $tokenFileIn);
        // Identify user for this session first.
        $nowSeconds = microtime(true);
        foreach ($tokenFileLines as $line) {
            $tokenParts = explode(";", $line);
            if (count($tokenParts) >= 3) {
                $period = $nowSeconds - intval($tokenParts[1]);
                if (($period < $this->tokenValidityPeriod) && (strcasecmp($token, $tokenParts[0]) == 0))
                    return intval($tokenParts[2]);
            }
        }
        return -1;
    }
}
