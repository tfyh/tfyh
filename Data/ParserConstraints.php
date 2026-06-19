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

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Provides constraints and predefined values (minimum, maximum, empty, and default)
 * for various parser types, ensuring consistency and boundaries for operations.
 */
class ParserConstraints
{
    /**
     * The absolute limits for size constraints. They assume 64-Bit operation.
     */
    // for 32 bit - 2,147,483,648
    private const INT_EMPTY = -2_147_483_647;
    const INT_MIN = -2_147_483_646;
    const INT_MAX = 2_147_483_647;

    // for 32 bit: long must be parsed into a float to get the full range. This will be at precision cost.
    private const LONG_EMPTY = -9.2233720368547E+018; // other implementations: -9_223_372_036_854_775_807
    const LONG_MIN = -9.223372036854E+018; // other implementations: -9_223_372_036_854_775_806
    const LONG_MAX = 9.223372036854E+018; // other implementations: 9_223_372_036_854_775_807

    // for 32 bit: - 3.40E+38
    private const DOUBLE_EMPTY = -1.791E+308;
    const DOUBLE_MIN = -1.790E+308;
    const DOUBLE_MAX = 1.790E+308;

    // time shall be limited to -99:25;29 ... 99:25;29, 100 hours is 360.000 seconds
    private const TIME_EMPTY = -360_000;
    const TIME_MIN = -359_999;
    const TIME_MAX = 359_999;

    const FOREVER_SECONDS = 9.223372e+15;

    // the native value for local dates is also localDateTime to simplify handling
    private static DateTimeImmutable $DATETIME_EMPTY;
    public static DateTimeImmutable $DATETIME_MIN;
    public static DateTimeImmutable $DATETIME_MAX;

    // only for the PHP part, because the class initialiser cannot handle expressions
    public static function init(): void
    {
        // for setting the limits, the timezone does not matter.
        $utc_timezone = new DateTimeZone("UTC");
        // in October 1582 there was a gap between the 4th and the 15th to change from the Julian
        // to the Gregorian calendar. Do not allow dates before that point in time.
        try {
            self::$DATETIME_EMPTY = new DateTimeImmutable("1582-12-31 00:00:00", $utc_timezone);
            self::$DATETIME_MIN = new DateTimeImmutable("1583-01-01 00:00:00", $utc_timezone);
            self::$DATETIME_MAX = new DateTimeImmutable("2999-12-31 23:59:59", $utc_timezone);
        } catch (Exception) {
            // ignored. DateTimeFormat Exceptions cannot appear because al values are proven literals
        }
    }

    // this is an arbitrary limit which will fit into a MySQL data field
    private const STRING_EMPTY = "";
    const STRING_MIN = "";
    /**
     * There is no maximum String. 50 times z is an approximation to a String which most probably
     * ends up at the end of all sorting. Be careful on using it
     */
    const STRING_MAX = "zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz";
    const STRING_SIZE = 4096;
    const TEXT_SIZE = 65_536;

    /* ------------------------------------------------------------------------ */
    /* ----- MIN, MAX, DEFAULT AND EMPTY VALUES ------------------------------- */
    /* ------------------------------------------------------------------------ */

    public static function max(ParserName $parser): bool|int|float|DateTimeImmutable|string|array
    {
        return match ($parser) {
            ParserName::BOOLEAN => true,
            ParserName::INT => self::INT_MAX,
            ParserName::LONG => self::LONG_MAX,
            ParserName::DOUBLE => self::DOUBLE_MAX,
            ParserName::DATE,
            ParserName::DATETIME => self::$DATETIME_MAX,
            ParserName::TIME => self::TIME_MAX,
            ParserName::STRING => self::STRING_MAX,
            ParserName::NONE => "",
            ParserName::INT_LIST, ParserName::STRING_LIST => []
        };
    }

    public static function min(ParserName $parser): bool|int|float|DateTimeImmutable|string|array
    {
        return match ($parser) {
            ParserName::BOOLEAN => true,
            ParserName::INT => self::INT_MIN,
            ParserName::LONG => self::LONG_MIN,
            ParserName::DOUBLE => self::DOUBLE_MIN,
            ParserName::DATE,
            ParserName::DATETIME => self::$DATETIME_MIN,
            ParserName::TIME => self::TIME_MIN,
            ParserName::STRING => self::STRING_MIN,
            ParserName::NONE => "",
            ParserName::INT_LIST, ParserName::STRING_LIST => []
        };
    }

    public static function empty(ParserName $parser): bool|int|float|DateTimeImmutable|string|array
    {
        return match ($parser) {
            ParserName::BOOLEAN => false,
            ParserName::INT => self::INT_EMPTY,
            ParserName::LONG => self::LONG_EMPTY,
            ParserName::DOUBLE => self::DOUBLE_EMPTY,
            ParserName::DATE,
            ParserName::DATETIME => self::$DATETIME_EMPTY,
            ParserName::TIME => self::TIME_EMPTY,
            ParserName::STRING, ParserName::NONE => self::STRING_EMPTY,
            ParserName::INT_LIST, ParserName::STRING_LIST => []
        };
    }

    /**
     * Return true if the value equals the empty value. Returns false on all errors.
     */
    public static function isEmpty(mixed $value, ParserName $parser): bool {
        if (is_null($value))
            return true;
        return match ($parser) {
            ParserName::BOOLEAN => false,
            ParserName::INT => ($value == self::INT_EMPTY),
            ParserName::LONG => ($value == self::LONG_EMPTY),
            ParserName::DOUBLE => ($value == self::DOUBLE_EMPTY),
            ParserName::DATE,
            ParserName::DATETIME => ($value == self::$DATETIME_EMPTY),
            ParserName::TIME => ($value == self::TIME_EMPTY),
            ParserName::STRING => ($value == self::STRING_EMPTY),
            ParserName::NONE => true,  // none values are empty by definition
            ParserName::INT_LIST, ParserName::STRING_LIST => is_array($value) && (count($value) == 0)
        };
    }

}