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

use Util\Language;
include_once "../../tfyh/Util/Language.php";
include_once "../../tfyh/Util/LanguageSettings.php";

/**
 * A utility to hold datetime data.
 */
class NoZoneDateTime {

    private array $parts;
    private int $epochMins;
    private int $partialMillis;


    public function __construct(String $dateTimeCleansed = "")
    {
        if (strlen($dateTimeCleansed) == 0) {
            $this->parts = explode("-", str_replace(" ", "-", str_replace(":", "-", self::nowAsString())));
        } else
            $this->parts = explode("-", str_replace(" ", "-", str_replace(":", "-", $dateTimeCleansed)));
        $this->epochMins = self::getEpochMins($dateTimeCleansed);
        $this->partialMillis = self::getMillis($dateTimeCleansed);

    }

    public function compareTo(NoZoneDateTime $other): Int {
        if (($this->epochMins == $other->epochMins) && ($this->partialMillis == $other->partialMillis))
            return 0;
        if ($this->microtime() < $other->microtime())
            return -1;
        return 1;
    }

    public function toDateTimeString(Language $language) : String {
        return $this->toDateString($language) . " " . $this->toTimeString();
    }

    public function toTimeString() : String {
        $H = (count($this->parts) < 4) ? "00" : $this->parts[3];
        $i = (count($this->parts) < 5) ? "00" : $this->parts[4];
        $s = (count($this->parts) < 6) ? "00" : $this->parts[5];
        return "$H:$i:$s";
    }

    // not implemented: public function millis()

    public function toDateString(Language $language) : String {
        return str_replace("Y", strval($this->year()), str_replace("m", self::twoChars($this->month()),
            str_replace("d", self::twoChars($this->day()), Language::settingsOf($language)->dateTemplate)));
    }

    public function year() : Int { return intval($this->parts[0]); }
    public function month() : Int { if (count($this->parts) > 1) return intval($this->parts[1]); else return 0; }
    public function day() : Int { if (count($this->parts) > 2) return intval($this->parts[2]); else return 0; }
    public function hour() : Int { if (count($this->parts) > 3) return intval($this->parts[3]); else return 0; }
    public function minute() : Int { if (count($this->parts) > 4) return intval($this->parts[4]); else return 0; }
    public function second() : Int { if (count($this->parts) > 5) return intval($this->parts[5]); else return 0; }
    public function microtime() : float { return floatval($this->epochMins) * 60 + floatval($this->partialMillis) / 1000; }

    // clientToServerOffset not implemented. The server needs no knowledge of the client time.

    private static array $inYearMinutes = [ 0 => 0,
        1 => 44640,
        2 => 84960,
        3 => 129600,
        4 => 172800,
        5 => 217440,
        6 => 260640,
        7 => 305280,
        8 => 349920,
        9 => 393120,
        10 => 437760,
        11 => 480960
    ];
    private static array $inLeapYearMinutes = [ 0 => 0,
        1 => 44640,
        2 => 86400,
        3 => 131040,
        4 => 174240,
        5 => 218880,
        6 => 262080,
        7 => 306720,
        8 => 351360,
        9 => 394560,
        10 => 439200,
        11 => 482400
    ];

    const EPOCH_MINS_OF_01_01_0001 = 1035593280;
        // count of minutes from the 1. of January of year 1 to 1970-01-01
        // in the extrapolated Gregorian Calendar
    const MINUTES_PER_DAY = 1440; // 24 * 60
    const MINUTES_PER_YEAR = 525600; // 365 days

    public static function twoChars(int $i) : String { if ($i < 10) return "0$i"; else return strval($i); }

    // setClientToServerOffset is not implemented. The server needs no knowledge of the client time.

    private static function isLeapYear(int $year) : int {
        return ($year % 400 == 0) || (($year % 4 == 0) && ($year % 100 != 0));
    }

    private static function leapYearsBefore(int $year) : int {
        return intval(($year - 1) / 4) - intval(($year - 1) / 100) + intval(($year - 1) / 400);
    }

    private static function yearOneMinsAtYearStart(int $year) : int {
        return ($year - 1) * self::MINUTES_PER_YEAR + self::leapYearsBefore($year) * self::MINUTES_PER_DAY;
    }

    /**
     * Return the full minutes from the epoch, i.e. the start of Jan, 1st, 1970. for the dateTimeCleansed.
     * Leap seconds are ignored, i.e. for 2026-03-04 17:25:36.233 this will use 29453760 minutes for 56 years,
     * including 14 leap years and 90325 minutes totalling to 29544085 minutes. The 36.233 seconds are ignored.
     * The seconds fraction and all leap seconds are ignored. 32-bit integer values cover +/- 4085 years.
     */
    private static function getEpochMins(String $dateTimeCleansed) : int {
        if (strlen($dateTimeCleansed) == 0)
            return self::epochMins();
        $splitDateTime = explode("-", str_replace(" ", "-", str_replace(":", "-", $dateTimeCleansed)));
        $year = intval($splitDateTime[0]);
        $minsFromYearOne = ($year - 1) *     // to get the minutes at the beginning of the year use ($year - 1)
            self::MINUTES_PER_YEAR + self::leapYearsBefore($year) * self::MINUTES_PER_DAY;
        $minutesFromEpoch = $minsFromYearOne - self::EPOCH_MINS_OF_01_01_0001;
        $isLeapYear = self::isLeapYear($year);
        $mm1 = intval($splitDateTime[1]) - 1;
        if ($mm1 < 0)
            $mm1 = 0;
        if ($isLeapYear) {
            $minutesFromYearStart = (is_null(self::$inLeapYearMinutes[$mm1])) ? 0 : intval(self::$inLeapYearMinutes[$mm1]);
        } else {
            $minutesFromYearStart = (is_null(self::$inYearMinutes[$mm1])) ? 0 : intval(self::$inYearMinutes[$mm1]);
        }
        $minutesFromYearStart += (intval($splitDateTime[2]) - 1) * self::MINUTES_PER_DAY;
        if (count($splitDateTime) > 3) $minutesFromYearStart += intval($splitDateTime[3]) * 60;
        if (count($splitDateTime) > 4) $minutesFromYearStart += intval($splitDateTime[4]);
        return $minutesFromEpoch + $minutesFromYearStart;
    }

    /**
     * Return the milliseconds part of the dateTimeCleansed String, i.e. for 2026-03-04 17:25:36.233
     * this will be 36,233,000.
     */
    private static function getMillis(String $dateTimeCleansed) : int {
        if (strlen($dateTimeCleansed) == 0)
            return (microtime(true) * 1000.0) % 60000;   // Integer modulo
        $parts = explode(":", $dateTimeCleansed);
        if (count($parts) < 3)
            return 0;
        return intval(doubleval($parts[2]) * 1000);
    }

    /**
     * Convert a timestamp into the date-time String.
     */
    public static function epochToDateTimeString(int $epochMins, int $partialMillis = 0, Language $language = Language::CSV) : String {

        // calculate the year
        $yearOneMins = $epochMins + self::EPOCH_MINS_OF_01_01_0001;
        $yearThis = intval($yearOneMins / 365.25 / self::MINUTES_PER_DAY);
        $y1minsAtYearStart = self::yearOneMinsAtYearStart($yearThis);
        while ($y1minsAtYearStart < $yearOneMins) {
            $yearThis++;
            $y1minsAtYearStart = self::yearOneMinsAtYearStart($yearThis);
        }
        $yearThis--;

        // calculate the month within the year
        $epochMinsAtThisYearStart = self::yearOneMinsAtYearStart($yearThis) - self::EPOCH_MINS_OF_01_01_0001;
        $minutesInThisYear = $epochMins - $epochMinsAtThisYearStart;
        $m = 0; // month 0...11
        $isLeapYearThis = self::isLeapYear($yearThis);
        if ($isLeapYearThis) {
            while (($m < 12) && ((self::$inLeapYearMinutes[$m] ?: 0) <= $minutesInThisYear)) $m++;
        } else {
            while (($m < 12) && ((self::$inYearMinutes[$m] ?: 0) <= $minutesInThisYear)) $m++;
        }
        $m--;
        // calculate the day and tine within the month
        if ($isLeapYearThis) {
            $inYearMinutesThisMonth = (is_null(self::$inLeapYearMinutes[$m])) ? 0 : self::$inLeapYearMinutes[$m];
        } else {
            $inYearMinutesThisMonth = (is_null(self::$inYearMinutes[$m])) ? 0 : self::$inYearMinutes[$m];
        }
        $minutesInMonth = $minutesInThisYear - $inYearMinutesThisMonth;
        $d = intval($minutesInMonth / 1440); // = integer division
        $hourInDay = intval(($minutesInMonth - $d * 1440) / 60);
        $minInHour = intval($minutesInMonth - $d * 1440 - $hourInDay * 60);
        $seconds = intval($partialMillis / 1000);  // = integer division

        return self::ymdToDateTimeString($yearThis, $m + 1, $d + 1,
            $hourInDay, $minInHour, $seconds, $language);
    }

    /**
     * Parse a cleansed date or datetime String into a LocalDateTime.
     *
     * @param String $dateTimeCleansed a CSV formatted local date time string, e.g. 2026-07-02 12:12:45 or 2026-07-02
     * @return NoZoneDateTime the parsed LocalDateTime or ParserConstraints.empty(ParserName.DATETIME) for any parsing error.
     */
    public static function parse(String $dateTimeCleansed) : NoZoneDateTime { return new NoZoneDateTime($dateTimeCleansed); }

    /**
     * get a LocalDateTime object for now
     */
    public static function now(): NoZoneDateTime { return new NoZoneDateTime(self::nowAsString()); }


    public static function epochMins(): Int { return intval(microtime(true) / 60); }

    /**
     * get a String for Now
     */
    public static function nowAsString(): String {
        $now = microtime(true);
        $nowMins = intval($now / 60);
        $nowMillis = intval(($now * 1000.0) % 60000);
        return self::epochToDateTimeString($nowMins, $nowMillis);
    }

    /**
     * Check, whether a dateTime String represents a valid date and time within the years 1 through 3000.
     */
    public static function isValid(String $dateTimeCleansed): bool
    {
        $dt = new NoZoneDateTime($dateTimeCleansed);
        $y = $dt->year();
        $isLeapYear = self::isLeapYear($y);
        $m = $dt->month();
        if ($m == 2) {
            $daysOfMonth = ($isLeapYear) ? 29 : 28;
        } else if (($m == 4) || ($m == 6) || ($m == 9) || ($m == 11)) {
            $daysOfMonth = 30;
        } else {
            $daysOfMonth = 30;
        }
        $d = $dt->day();
        $h = $dt->hour();
        $i = $dt->minute();
        $s = $dt->second();
        return ($y >= 1) && ($y < 3000) && ($m >= 1) && ($m <= 12) && ($d >= 1) && ($d <= $daysOfMonth) &&
            ($h >= 0) && ($h <= 23) && ($i >= 0) && ($i <= 59) && ($s >= 0) && ($s <= 59);
    }

    /**
     * format a year, month, and day into a valid local date format.
     */
    public static function ymdToDateString(int $y, int $m, int $d, Language $language = Language::CSV) : String {
        return str_replace("Y", strval($y), str_replace("m", self::twoChars($m), str_replace("d", self::twoChars($d),
            Language::settingsOf($language)->dateTemplate)));
    }

    /**
     * format a year, month, day, hour, minute (i), second into a valid local date-time format.
     */
    public static function ymdToDateTimeString (int $y, int $m, int $d, int $h, int $i, int $s, Language $language = Language::CSV) : String {
        return self::ymdToDateString($y, $m, $d, $language) . " " . self::twoChars($h) . ":" . self::twoChars($i) . ":" . self::twoChars($s);
    }

    /**
     * Run a NoZoneDateTime class test whoch creates a new NoZoneDateTime per day, running forward or backwards from now.
     * @param int $days days to run
     * @param NoZoneDateTime $now this day. The object will be modified to hold the previous day or the next day,
     * depending on the run direction
     * @param string $res the result as csv so far
     * @return string the result aftzer looping through $days days.
     */
    public static function testLoop(int $days, bool $ascending, NoZoneDateTime $now, string $res): string
    {
        for ($i = 1; $i <= $days; $i++) {
            if ($ascending)
                $now->epochMins += self::MINUTES_PER_DAY;
            else
                $now->epochMins -= self::MINUTES_PER_DAY;
            $nowStr = self::epochToDateTimeString($now->epochMins, $now->partialMillis);
            $nowReconverted = new NoZoneDateTime($nowStr);
            $res .= $now->epochMins . ";" . $now->partialMillis . ";" . $nowReconverted->epochMins . ";" . $nowReconverted->partialMillis . ";" . $nowStr . "\n";
        }
        return $res;
    }

    public static function tester(int $days): void
    {
        $now = new NoZoneDateTime();
        $res = $now->epochMins . ";" . $now->partialMillis . ";;; ____________ starting descent\n";
        $res = self::testLoop($days,true, $now, $res);
        $now = new NoZoneDateTime();
        $res .= $now->epochMins . ";" . $now->partialMillis . ";;; ____________ starting descent\n";
        $res = self::testLoop($days, false, $now, $res);
        file_put_contents("../../var/Log/NoZoneDateTimeTester.csv", $res);
    }

}