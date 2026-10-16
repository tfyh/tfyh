/**
 * tools-for-your-hobby
 * https://www.tfyh.org
 * Copyright  2023-2025  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except
 * in compliance with the License. You may obtain a copy of the License at
 * https://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software distributed under the License
 * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 * or implied. See the License for the specific language governing permissions and limitations under
 * the License.
 */

package org.tfyh.data

/**
 * A utility to hold empty, default, min, and max values for any parser
 */
object ParserConstraints {
    /**
     * The absolute limits for size constraints. They assume 64 Bit operation.
     */
    // for 32 bit - 2147483648
    private const val INT_EMPTY = -2_147_483_647
    internal const val INT_MIN = -2_147_483_646
    internal const val INT_MAX = 2_147_483_647

    // for 32 bit: - 3.40E+38
    private const val LONG_EMPTY = -9_223_372_036_854_775_807L
    internal const val LONG_MIN = -9_223_372_036_854_775_806L
    internal const val LONG_MAX = 9_223_372_036_854_775_807L

    // for 32 bit: - 3.40E+38
    private const val DOUBLE_EMPTY = -1.791E+308
    internal const val DOUBLE_MIN = -1.790E+308
    internal const val DOUBLE_MAX = 1.790E+308

    // time shall be limited to -99:25;29 .. 99:25;29, 100 hours is 360.000 seconds
    private const val TIME_EMPTY = -360_000
    internal const val TIME_MIN = -359_999
    internal const val TIME_MAX = 359_999
    
    const val FOREVER_SECONDS = 9.223372e+15

    /* The MySQL server limits the DATE data type to dates from 1000-01-01 onwards, and the
     * MS SQL Server limits the DATETIME data type to dates from 1753-01-01 onwards. See:
     * https://learn.microsoft.com/en-us/sql/t-sql/data-types/datetime-transact-sql?view=sql-server-ver17
     * For a wider range of dates one could use the Datetime2 data type, but this is not
     * available in MySQL. And the framework is meant to be implementation agnostic.
     */
    private val DATE_EMPTY = NoZoneDateTime("1753-01-01")
    internal val DATE_MIN = NoZoneDateTime("1753-01-02")
    internal val DATE_MAX = NoZoneDateTime("2999-12-31")
    private val DATETIME_EMPTY = NoZoneDateTime("1753-01-01 00:00:00")
    internal val DATETIME_MIN = NoZoneDateTime("1753-01-02 00:00:00")
    internal val DATETIME_MAX = NoZoneDateTime("2999-12-31 23:59:59")

    // this is an arbitrary limit which will fit into a MySQL data field
    private const val STRING_EMPTY = ""
    @Suppress("Unused")
    internal const val STRING_MIN = ""
    /**
     * There is no maximum String. 50 times z is an approximation to a String which most probably
     * ends up at the end of all sorting. Be careful on using it
     */
    private const val STRING_MAX = "zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz"
    internal const val STRING_SIZE = 4096
    internal const val TEXT_SIZE = 65_536

    /* ------------------------------------------------------------------------ */
    /* ----- MIN, MAX, DEFAULT AND EMPTY VALUES ------------------------------- */
    /* ------------------------------------------------------------------------ */

    fun max(parser: ParserName): Any {
        return when (parser) {
            ParserName.BOOLEAN -> true
            ParserName.INT -> INT_MAX
            ParserName.LONG -> LONG_MAX
            ParserName.DOUBLE -> DOUBLE_MAX
            ParserName.DATE -> DATE_MAX
            ParserName.DATETIME -> DATETIME_MAX
            ParserName.TIME -> TIME_MAX
            ParserName.STRING -> STRING_MAX
            ParserName.NONE -> ""
            ParserName.INT_LIST -> mutableListOf<Int>()
            ParserName.STRING_LIST -> mutableListOf<String>()
        }
    }

    fun min(parser: ParserName): Any {
        return when (parser) {
            ParserName.BOOLEAN -> false
            ParserName.INT -> INT_MIN
            ParserName.LONG -> LONG_MIN
            ParserName.DOUBLE -> DOUBLE_MIN
            ParserName.DATE -> DATE_MIN
            ParserName.DATETIME -> DATETIME_MIN
            ParserName.TIME -> TIME_MIN
            ParserName.STRING,
            ParserName.NONE -> ""
            ParserName.INT_LIST -> mutableListOf<Int>()
            ParserName.STRING_LIST -> mutableListOf<String>()
        }
    }

    /**
     * The "empty"-value represents null. It will be formatted in any language and CSV to an
     * empty String and an empty String will be parsed to an empty value. At the SQL interface
     * the empty value is formatted to NULL, if the value allows null, else to its numeric
     * value, and the result of parsing an empty String or NULL instead.
     * To get a memory representation empty values are the lowest possible number and date,
     * an empty String or a Boolean false.
     * NB: that reduces the numeric range at the lower level. For a type Boolean this will
     * always return false.
     */
    internal fun empty(parser: ParserName): Any {
        return when (parser) {
            ParserName.BOOLEAN -> false
            ParserName.INT -> INT_EMPTY
            ParserName.LONG -> LONG_EMPTY
            ParserName.DOUBLE -> DOUBLE_EMPTY
            ParserName.DATE -> DATE_EMPTY
            ParserName.DATETIME -> DATETIME_EMPTY
            ParserName.TIME -> TIME_EMPTY
            ParserName.STRING -> STRING_EMPTY
            ParserName.NONE -> ""
            ParserName.INT_LIST -> mutableListOf<Int>()
            ParserName.STRING_LIST -> mutableListOf<String>()
        }
    }

    /**
     * return true if the value is null or identical to the empty-value defined by the parser
     */
    fun isEmpty(value: Any?, parser: ParserName): Boolean {
        return value == null || when (parser) {
            ParserName.BOOLEAN -> false
            ParserName.INT -> value is Int && (value <= INT_MIN)
            ParserName.LONG -> value is Long && (value == LONG_EMPTY)
            ParserName.DOUBLE -> value is Double && (value == DOUBLE_EMPTY)
            ParserName.DATE -> value == DATE_EMPTY // NoZoneDateTime is an Object, no cast needed
            ParserName.DATETIME -> value == DATETIME_EMPTY // NoZoneDateTime is an Object, no cast needed
            ParserName.TIME -> value is Int && (value == TIME_EMPTY)
            ParserName.STRING -> value is String && (value == STRING_EMPTY)
            ParserName.NONE -> true // none values are empty by definition
            ParserName.INT_LIST, ParserName.STRING_LIST -> value is MutableList<*> && value.isEmpty()
        }
    }

}