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

use tfyh\data\Ids;

/**
 * A utility class to create one-time tokens for user identification.
 */

class TokenHandler
{

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
