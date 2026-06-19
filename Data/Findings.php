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

namespace tfyh\data;

// internationalisation support needed to translate warning and error messages
use tfyh\util\I18n;

/**
 * The Findings class is responsible for managing and categorising findings such as errors and warnings.
 * It allows adding new findings to specific categories (errors or warnings), retrieving them, and
 * analysing their counts.
 */
class Findings
{
    private static array $errors = [];
    private static array $warnings = [];

    /**
     * Clear all findings from the list.
     * @return void
     */
    public static function clearFindings(): void
    {
        self::$errors = [];
        self::$warnings = [];
    }

    /**
     * Add a finding to the list. The reason code is used to determine the type of finding and provide a message.
     * @param int $reasonCode Reason codes are:
     *  ERRORS:
     *  1 Format error, 2 Numeric value required. 3 Exception raised, 4 mandatory field is missing, 5 illegal duplicate name
     *  6 any other error
     *  WARNINGS:
     *  10 too small. Replaced, 11 too big. Replaced, 12 Unknown data type, 13 The value°s native type does not match the
     *  data type, 14 The value°s data type does not match the native type, 15 String too long. Cut, 16 Value limits
     *  cannot be adjusted in lists, 17 any other warning.
     * @param string $violatingValueStr the name of the value that caused the finding.
     * @param string $violatedLimitStr the limit or value itself that caused the finding.
     * @return void
     */
    public static function addFinding(int $reasonCode, string $violatingValueStr, string $violatedLimitStr = ""): void
    {
        $i18n = I18n::getInstance();
        match ($reasonCode) {
            1 => self::$errors[] = $i18n->t("Im6RzC|Format error in °%1°.", $violatingValueStr),
            2 => self::$errors[] = $i18n->t("4j2U0W|Numeric value required i...", $violatingValueStr),
            3 => self::$errors[] = $i18n->t("2x5WNx|Exception raised when pa...", $violatingValueStr, $violatedLimitStr),
            4 => self::$errors[] = $i18n->t("nKI7OJ|The required field °%1° ...", $violatingValueStr),
            5 => self::$errors[] = $i18n->t("fu97I0|Name °%1° is already use...", $violatingValueStr, $violatedLimitStr),
            6 => self::$errors[] = $violatingValueStr, // any other error
            10 => self::$warnings[] = $i18n->t("IL4ihl|°%1° is too small. Repla...", $violatingValueStr, $violatedLimitStr),
            11 => self::$warnings[] = $i18n->t("O0EFCI|°%1° is too big. Replace...", $violatingValueStr, $violatedLimitStr),
            12 => self::$warnings[] = $i18n->t("jN5Dvb|Unknown data type / vali...", $violatingValueStr),
            13 => self::$warnings[] = $i18n->t("FHFWAq|The value°s native type ..."),
            14 => self::$warnings[] = $i18n->t("R3IpuB|The value°s data type °%...", $violatingValueStr),
            15 => self::$warnings[] = $i18n->t("fWxdb7|String °%1° too long. Cu...", $violatingValueStr, $violatedLimitStr),
            16 => self::$warnings[] = $i18n->t("vxuspU|Value limits can not be ...", $violatingValueStr),
            17 => self::$warnings[] = $violatingValueStr, // any other warning
        };
    }

    /**
     * get all the findings which are errors
     * @return array of Strings containing the errors as plain text
     */
    public static function getErrors(): array  { return Findings::$errors; }
    /**
     * get the count of findings which are errors
     * @return int the number of errors
     */
    public static function countErrors(): int { return count(Findings::$errors); }
    /**
     * get all the findings which are warnings
     * @return array of Strings containing the warnings as plain text
     */
    public static function getWarnings(): array { return Findings::$warnings; }
    /**
     * get the count of findings which are warnings
     * @return int the number of warnings
     */
    public static function countWarnings(): int { return count(Findings::$errors); }

    /**
     * get all the findings as text.
     * @return String containing the errors and if requested the warnings as plain text, one finding per line.
     */
    public static function getFindings(bool $includeWarnings): string
    {
        $findingsStr = "";
        foreach (Findings::$errors as $error) $findingsStr .= $error . "\n";
        if ($includeWarnings)
            foreach (Findings::$warnings as $warning) $findingsStr .= $warning . "\n";
        return $findingsStr;
    }
}
