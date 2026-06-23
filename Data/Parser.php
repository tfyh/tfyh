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

use DateTimeImmutable;
use DateTimeZone;
use Exception;

use Util\Language;

/**
 * The Parser class provides methods for cleansing and normalising date, time, and datetime strings
 * into standardised formats. It includes functionality to handle different language and regional
 * date formats, as well as error handling and heuristic date parsing in specific edge cases. It is fully static
 * and the counterpart to the Formatter. Its main method is parse(), which takes a value and a ParserName.
 */
class Parser
{
    private static Language $language = Language::EN;
    private static DateTimeZone $timeZone;

    public static function setLocale(Language $language = null, DateTimeZone $timeZone = null): void {
        self::$language = (is_null($language)) ? self::$language : $language;
        self::$timeZone = (is_null($timeZone)) ? new DateTimeZone(DEFAULT_TIME_ZONE) : $timeZone;
    }

    /* ------------------------------------------------------------------------ */
    /* ----- STRING CLEANSING ------------------------------------------------- */
    /* ----- Errors are documented in the public $validation_error. ----------- */
    /* ------------------------------------------------------------------------ */

    private static function cleanseDate (String $dateString, Language $language): string|null
    {
        // an empty String shall be an empty date
        if (strlen($dateString) == 0)
            return "";
        // split the string to identify day, month and year
        $dateTemplate = Language::settingsOf($language)->dateTemplate;
        $parts = explode(substr($dateTemplate, 1, 1), $dateString);

        // the result may not match if the format is not according to the language expected.
        if ((count($parts) == 1) && ! is_numeric($parts[0])) {
            if (count(explode("-", $dateString)) == 3)
                // assume ISO formatting, typically a result of a form entry
                $dateTemplate = Language::settingsOf(Language::CSV)->dateTemplate;
            elseif (count(explode(".", $dateString)) == 3)
                // assume DE formatting
                $dateTemplate = Language::settingsOf(Language::DE)->dateTemplate;
            elseif (count(explode("/", $dateString)) == 3)
                // assume EN formatting
                $dateTemplate = Language::settingsOf(Language::EN)->dateTemplate;
            $parts = explode(substr($dateTemplate, 1, 1), $dateString);
        }
        // If a DateTime ist provided instead of a date, cut the time off of the last element
        if ((count($parts) == 3) && (str_contains($parts[2], " ")))
            $parts[2] = explode(" ", $parts[2])[0];
        // convert to Integer
        $partsInt = [];
        foreach ($parts as $part)
            $partsInt[] = intval($part);

        // if there is just one value, assume it to be the year, if > 31
        // else to be the day of the month and add month and year
        try {
            // during bootstrap self::$timeZone my not yet be initialised
            $now = new DateTimeImmutable("now", (isset(self::$timeZone)) ? self::$timeZone : null);
        } catch (Exception) {
            $now = ParserConstraints::empty(ParserName::DATE);
        }
        $nowMonth = intval($now->format("m"));
        $lastDayOfMonth = match ($nowMonth) {
            4,6,9,11 => 30,
            2 => 28,  // no leap year support in date autocompletion.
            default => 31
        };
        if (count($parts) == 1) {
            if (($partsInt[0] > 1000) && ($partsInt[0] < 2999))
                // a four-digit integer in the date range is taken to be a year. Add the first of Jánuary
                return "$dateString-01-01";
            else if (($partsInt[0] >= 1) && ($partsInt[0] <= $lastDayOfMonth)) {
                // an integer in the day of month range is taken ro be the actual month's day
                return date("Y-m") . "-" . sprintf("%'.02d", $partsInt[0]);
            } else {
                // any other value is regarded as an error
                Findings::addFinding(1, $dateString);
                return null;
            }
        }

        $yearIsFirst = str_starts_with(strtolower($dateTemplate), "y");
        // if just two integers were detected, assume that the year is missing and add the
        // current year
        $nowYear = intval($now->format("Y"));
        if ((count($parts) == 2) || ((count($parts) == 3) && (strlen($parts[2]) == 0))) {
            $y = $nowYear;
            $m = ($yearIsFirst) ? $partsInt[0] : $partsInt[1];
            $d = ($yearIsFirst) ? $partsInt[1] : $partsInt[0];
            if (($m >= 1) && ($m <= 12) && ($d >= 1) && ($d <= $lastDayOfMonth))
                // try to build a date, causes an exception if invalid
                try {
                    $date = new DateTimeImmutable("$y-$m-$d");
                    return $date->format("Y-m-d");
                } catch (Exception) {
                    Findings::addFinding(1, $dateString);
                    return null;
                }
        }

        // three numbers are given
        // if all are lower than 100, extend the year by a heuristic guess
        $y = ($yearIsFirst) ? $partsInt[0] : $partsInt[2];
        if ($y < 100) {
            // extend two digits. Get the century
            $yearNow2Digit = $nowYear % 100;
            $centuryNow = $nowYear - $yearNow2Digit;
            $centuryNext = $centuryNow + 100;
            $centuryPrevious = $centuryNow - 100;
            // apply heuristics: go 90 years back to 10 years forward
            $y = ($yearNow2Digit < 90) ?
                (($y > ($yearNow2Digit + 10)) ? ($centuryPrevious + $y) : ($centuryNow + $y)) :
                (($y > ($yearNow2Digit + 10) % 100) ? ($centuryNow + $y) : ($centuryNext + $y));
        }
        // try to build a date, causes an exception if invalid
        try {
            $m = $partsInt[1];
            $d = $partsInt[($yearIsFirst) ? 2 : 0];
            $date = new DateTimeImmutable("$y-$m-$d");
            return $date->format("Y-m-d");
        } catch (Exception) {
            Findings::addFinding(1, $dateString);
            return null;
        }
    }

    private static function cleanseTime (String $timeString): string|null
    {
        // an empty String shall be an empty time
        if (strlen($timeString) == 0)
            return "";
        // split off the "minus", if existing.
        $sign = "";
        if (str_starts_with($timeString, "-")) {
            $timeString = trim(substr($timeString, 1));
            $sign = "-";
        }
        // cleanse the remainder
        $hms = explode(":", $timeString);
        if ((count($hms) < 2) || (count($hms) > 3))
            return null;
        $hms0 = sprintf("%'.02d", intval($hms[0]));
        $hms1 = sprintf("%'.02d", intval($hms[1]));
        if (count($hms) == 2)
            return $sign . $hms0 . ":" . $hms1 . ":00";
        $hms2 = sprintf("%'.02d", intval($hms[2]));
        return $sign . $hms0 . ":" . $hms1 . ":" . $hms2;
    }

    /**
     * Cleanse a datetime string to YYYY-MM-DD HH:MM:SS format. Milliseconds are dropped. If no date is given,
     * insert the current date. If no time is given, insert the current time.
     */
    private static function cleanseDatetime (String $datetimeString, Language $language): string|null
    {
        // an empty String shall be an empty datetime
        if (strlen($datetimeString) == 0)
            return "";
        $dt = explode(" ", trim($datetimeString));
        if (count($dt) == 1) {
            // try both, date or time
            $date = self::cleanseDate($dt[0], $language);
            $time = self::cleanseTime($dt[0]); // always with hours
            if (!is_null($date))
                return $date . " 00:00:00";
            elseif (! is_null($time))
                return date("Y-m-d") . " " . $time;
            else {
                Findings::addFinding(1, trim($datetimeString));
                return null;
            }
        } else {
            $date = self::cleanseDate($dt[0], $language);
            $time = self::cleanseTime($dt[1]); // always with hours
            if (is_null($date) || is_null($time)) {
                Findings::addFinding(1, trim($datetimeString));
                return null;
            }
            return $date . " " . $time;
        }
    }

    /**
     * Parse a value from storage or the database for processing. Array values must start and end with square brackets
     * and comma separated (,), quoting is needed (like [a,b,", and c"]). Empty Strings are parsed into empty values
     * (see TypeConstraints) or empty Lists. For Language::SQL the String NULL without quotes is also parsed into an empty value.
     * Boolean values will be true for any non-empty String except the String "false" (not case-sensitive) and the String
     * "0". For Languages::CSV and Languages::SQL quoted Strings are unquoted before parsing. The function never returns null.
     * If the value is not a String but matches the target native type of the parser, it is returned unchanged.
     * @param mixed $value The value to be parsed.
     * @param ParserName $parser The parser to be used for parsing.
     * @param Language $language The language of the value used for parsing.
     * @return bool|int|float|DateTimeImmutable|string|array The parsed value.
     */
    public static function parse (mixed $value, ParserName $parser, Language $language):
        bool|int|float|DateTimeImmutable|string|array
    {
        if (gettype($value) != "string") {
            if (self::isMatchingNative($value, $parser))
                return $value;
            $valueForError = (is_scalar($value)) ? strval($value) : "[non-scalar value]";
            Findings::addFinding(3, $valueForError, gettype($value));
            return ParserConstraints::empty($parser);
        }
        // remove quotes, if existing.
        $toParse = $value;
        if (($language == Language::CSV)
            && str_starts_with($value, "\"") && str_ends_with($value, "\""))
            $toParse = str_replace("\"\"", "\"",
                trim(mb_substr($value, 1, mb_strlen($value) - 2)));
        elseif ($language == Language::SQL) {
            if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                $toParse = str_replace("\\'", "'",
                    trim(mb_substr($value, 1, mb_strlen($value) - 2)));
            } elseif (strtolower($value) == "null")
                    // Special case: unquoted NULL for Language.SQL
                    $toParse = null;
        }
        // Special case: unquoted NULL for Language.SQL
        if (is_null($toParse))
            return ParserConstraints::empty($parser);
        return match($parser) {
            ParserName::BOOLEAN => self::parseBoolean($toParse),
            ParserName::INT => self::parseInt($toParse, $language),
            ParserName::INT_LIST => self::parseList($toParse, $language, ParserName::INT),
            ParserName::LONG => self::parseLong($toParse, $language),
            ParserName::DOUBLE => self::parseDouble($toParse, $language),
            ParserName::DATE => self::parseDate($toParse, $language),
            ParserName::DATETIME => self::parseDateTime($toParse, $language),
            ParserName::TIME => self::parseTime($toParse),
            ParserName::STRING => $toParse,
            ParserName::STRING_LIST => self::parseList($toParse, $language, ParserName::STRING),
            ParserName::NONE => ""
         };
    }

    private static function parseBoolean (String $boolString): bool
    {
        return ((strlen($boolString) > 0) && (strcasecmp($boolString, "false") != 0)
                && (strcmp($boolString, "0") != 0));
    }

    /**
     * Convert a String representing a Long-value into a float number. If parsing fails, this will
     * return ParserConstraints::empty(ParserName::LONG).
     */
    private static function parseLong(String $longString, Language $language): float {
        if (strlen($longString) == 0)
            return ParserConstraints::empty(ParserName::LONG);
        $toParse = str_replace(" ", "", trim($longString));
        $toParse = (Language::settingsOf($language)->decimalPoint) ?
            str_replace(",", "", $toParse) :
            str_replace(".", "", $toParse);
        if (strlen($toParse) == 0)
            return ParserConstraints::empty(ParserName::LONG);
        if (is_numeric($toParse))
            return floatval($toParse);
        return ParserConstraints::empty(ParserName::LONG);
    }

    /**
     * Convert a not-empty String to an integer number. If parsing fails, this will
     * return Constraints.empty(Name.LONG).
     */
    private static function parseInt(String $intString, Language $language): int
    {
        if (strlen($intString) == 0)
            return ParserConstraints::empty(ParserName::INT);
        $floatVal = self::parseLong($intString, $language);
        if (ParserConstraints::isEmpty($floatVal, ParserName::DOUBLE))
            return ParserConstraints::empty(ParserName::INT);
        if ($floatVal <= floatVal(ParserConstraints::min(ParserName::INT)))
            return ParserConstraints::min(ParserName::INT);
        if ($floatVal >= floatval(ParserConstraints::max(ParserName::INT)))
            return ParserConstraints::max(ParserName::INT);
        return intval($intString);
    }

    private static function parseDouble(String $floatString, Language $language): float {
        $toParse = str_replace(" ", "", trim($floatString));
        $toParse = (Language::settingsOf($language)->decimalPoint) ?
            str_replace(",", "", $toParse) :
            str_replace(",", ".", str_replace(".", "", $toParse));
        if (strlen($toParse) == 0)
            return ParserConstraints::empty(ParserName::DOUBLE);
        if (is_numeric($toParse))
            return floatval($toParse);
        return ParserConstraints::empty(ParserName::DOUBLE);
    }

    private static function parseTime (String $time_string): int
    {
        $cleansed = self::cleanseTime($time_string);
        if (is_null($cleansed) || (strlen($cleansed) == 0))
            return ParserConstraints::empty(ParserName::TIME);
        $sign = (str_starts_with(trim($time_string), "-")) ? - 1 : 1;
        $hms = explode(":", $cleansed);
        return $sign * (abs(intval($hms[0])) * 3600 + intval($hms[1]) * 60 + intval($hms[2]));
    }

    private static function parseDate (String $date_string, Language $language): DateTimeImmutable
    {
        $dateString = self::cleanseDate($date_string, $language);
        if (is_null($dateString) || (strlen($dateString) == 0))
            return ParserConstraints::empty(ParserName::DATE);
        $dti = date_create_immutable($dateString);
        if ($dti === false) {
            Findings::addFinding(2, $date_string);
            return ParserConstraints::empty(ParserName::DATE);
        }
        return $dti;
    }

    private static function parseDatetime (String $datetime_string, Language $language): DateTimeImmutable
    {
        $datetime = self::cleanseDatetime($datetime_string, $language);
        if (is_null($datetime) || (strlen($datetime) == 0))
            return ParserConstraints::empty(ParserName::DATETIME);
        $dti = date_create_immutable($datetime);
        if ($dti === false) {
            Findings::addFinding(2, $datetime_string);
            return ParserConstraints::empty(ParserName::DATETIME);
        }
        return $dti;
    }

    /**
     * Convert a String with a List of Integer like [1,2,3,4] or Strings like [a,"b,c",d] (3 elements!) into an array
     * by parsing all values. Empty Strings return an empty array. If the brackets are missing, the $value is simply
     * split along all commas into an array. Empty elements result in Constraints.empty($singleParser).
     * @param String $value
     * @param Language $language
     * @param ParserName $singleParser
     * @return array<int, int|string>
     */
    private static function parseList(String $value, Language $language, ParserName $singleParser): array {
        $parsed = [];
        if (strlen($value) == 0)
            return $parsed;
        if (str_starts_with($value, "[") && str_ends_with($value, "]"))
            $value = trim(mb_substr($value, 1, mb_strlen($value) - 2));
        $values = Codec::splitCsvRow($value, ",");
        if ($singleParser == ParserName::INT)
            foreach ($values as $v)
                $parsed[] = self::parseInt(trim($v), $language);
        elseif ($singleParser == ParserName::STRING)
            foreach ($values as $v)
                $parsed[] = trim($v);
        return $parsed;
    }

    /**
     * Get the best matching parser for a native value of unknown Type
     * @param mixed $value The native value to be analysed.
     * @return ParserName The parser name that best matches the native value.
     */
    public static function nativeToParser(mixed $value): ParserName {
        if (is_array($value)) {
            if (count($value) == 0)
                return ParserName::STRING_LIST;
            if (is_int($value[0]))
                return ParserName::INT_LIST;
            if (is_string($value[0]))
                return ParserName::STRING_LIST;
            return ParserName::STRING_LIST;
        }
        if (is_bool($value))
            return ParserName::BOOLEAN;
        if (is_int($value)) {
            if (($value > ParserConstraints::max(ParserName::INT))
                || ($value < ParserConstraints::empty(ParserName::INT)))
                return ParserName::LONG;
            else
                return ParserName::INT;
        }
        if (is_float($value))
            // NB: Due to the 32-bit integer restriction, the native type for long is also float, but this cannot be
            // distinguished
            return ParserName::DOUBLE;
        if ($value instanceof DateTimeImmutable) {
            if ($value->format("H:i:s") === "00:00:00")
                return ParserName::DATE;
            else
                return ParserName::DATETIME;
        }
        if (is_string($value))
            return ParserName::STRING;
        return ParserName::NONE;
    }

    /**
     * Determines if the given value matches the expected native type or structure
     * based on the specified parser name.
     *
     * @param mixed $value The value to check for type compatibility.
     * @param ParserName $parserName The parser name that defines the expected type or structure.
     * @return bool Returns true if the value matches the expected type or structure; otherwise, false.
     */
    public static function isMatchingNative(mixed $value, ParserName $parserName): bool {
        return match($parserName) {
            ParserName::BOOLEAN => (gettype($value) =="boolean"),
            ParserName::TIME,
            ParserName::INT => (gettype($value) == "integer"),
            ParserName::INT_LIST => (gettype($value) == "array") && ((count($value) == 0) || (gettype($value[0]) == "integer")),
            ParserName::LONG,  // no native Long in PHP, use float
            ParserName::DOUBLE => (gettype($value) == "double"),
            ParserName::DATE,
            ParserName::DATETIME => (gettype($value) == "object") && (get_class($value) == "DateTimeImmutable"),
            ParserName::STRING => (gettype($value) == "string"),
            ParserName::STRING_LIST => (gettype($value) == "array") && ((count($value) == 0) || (gettype($value[0]) == "string")),
            ParserName::NONE => true,
         };
    }

}