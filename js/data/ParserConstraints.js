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

/**
 * A utility to hold empty, default, min, and max values for any parser
 */
class ParserConstraints {
    /**
     * The absolute limits for size constraints. They assume 64-Bit operation.
     */
    // for 32 bit - 2,147,483,648
    static #INT_EMPTY = -2_147_483_647
    static INT_MIN = -2_147_483_646
    static INT_MAX = 2_147_483_647

    // for 32 bit: - 3.40E+38
    static #LONG_EMPTY = -9_223_372_036_854_775_807
    static LONG_MIN = -9_223_372_036_854_775_806
    static LONG_MAX = 9_223_372_036_854_775_807

    // for 32 bit: - 3.40E+38
    static #DOUBLE_EMPTY = -1.791E+308
    static DOUBLE_MIN = -1.790E+308
    static DOUBLE_MAX = 1.790E+308

    // time shall be limited to -99:25;29 ... 99:25;29, 100 hours is 360.000 seconds
    static #TIME_EMPTY = -360_000
    static TIME_MIN = -359_999
    static TIME_MAX = 359_999

    static FOREVER_SECONDS = 9.223372e+15

    // the native value for local dates is also localDateTime to simplify handling
    static #DATETIME_EMPTY = new Date(1582, 11, 31, 0, 0)
    static DATETIME_MIN = new Date(1583, 0, 1, 0, 0)
    static DATETIME_MAX = new Date(2999, 12, 31, 23, 59, 59)

    // this is an arbitrary limit which will fit into a MySQL data field
    static #STRING_EMPTY = ""
    static STRING_MIN = ""
    /**
     * There is no maximum String. 50 times z is an approximation to a String which most probably
     * ends up at the end of all sorting. Be careful on using it
     */
    static STRING_MAX = "zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz"
    static STRING_SIZE = 4096
    static TEXT_SIZE = 65_536

    /* ------------------------------------------------------------------------ */
    /* ----- MIN, MAX, DEFAULT AND EMPTY VALUES ------------------------------- */
    /* ------------------------------------------------------------------------ */

    static max(parser) {
        switch (parser) {
            case ParserName.BOOLEAN: return true
            case ParserName.INT: return ParserConstraints.INT_MAX
            case ParserName.LONG: return ParserConstraints.LONG_MAX
            case ParserName.DOUBLE: return ParserConstraints.DOUBLE_MAX
            case ParserName.DATE:
            case ParserName.DATETIME: return ParserConstraints.DATETIME_MAX
            case ParserName.TIME: return ParserConstraints.TIME_MAX
            case ParserName.STRING: return ParserConstraints.STRING_MAX
            case ParserName.NONE: return ""
            case ParserName.INT_LIST: return []
            case ParserName.STRING_LIST: return []
            default: return null // try to raise an error
        }
    }

    static min(parser) {
        switch (parser) {
            case ParserName.BOOLEAN: return false
            case ParserName.INT: return ParserConstraints.INT_MIN
            case ParserName.LONG: return ParserConstraints.LONG_MIN
            case ParserName.DOUBLE: return ParserConstraints.DOUBLE_MIN
            case ParserName.DATE:
            case ParserName.DATETIME: return ParserConstraints.DATETIME_MIN
            case ParserName.TIME: return ParserConstraints.TIME_MIN
            case ParserName.STRING:
            case ParserName.NONE: return ""
            case ParserName.INT_LIST: return []
            case ParserName.STRING_LIST: return []
            default: return null // try to raise an error
        }
    }

    /**
     * The "empty"-value represents null. It will be formatted in any language and CSV to an
     * empty String, and an empty String will be parsed to an empty value. At the SQL interface
     * the empty value is formatted to NULL if the value allows null, else to its numeric
     * value, and the result of parsing an empty String or NULL instead.
     * To get a memory representation, empty values are the lowest possible number and date,
     * an empty String or a Boolean false.
     * NB: that reduces the numeric range at the lower level. For a type Boolean this will
     * always return false.
     */
    static empty(parser) {
        switch (parser) {
            case ParserName.BOOLEAN: return false
            case ParserName.INT: return ParserConstraints.#INT_EMPTY
            case ParserName.LONG: return ParserConstraints.#LONG_EMPTY
            case ParserName.DOUBLE: return ParserConstraints.#DOUBLE_EMPTY
            case ParserName.DATE:
            case ParserName.DATETIME: return ParserConstraints.#DATETIME_EMPTY
            case ParserName.TIME: return ParserConstraints.#TIME_EMPTY
            case ParserName.STRING: return ParserConstraints.#STRING_EMPTY
            case ParserName.NONE: return ""
            case ParserName.INT_LIST: return []
            case ParserName.STRING_LIST: return []
            default: return null // try to raise an error
        }
    }

    static isEmpty(value, parser) {
        if (value == null)
            return true
        switch (parser) {
            case ParserName.BOOLEAN: return false
            case ParserName.INT: return (value === ParserConstraints.#INT_EMPTY)
            case ParserName.LONG: return (value === ParserConstraints.#LONG_EMPTY)
            case ParserName.DOUBLE: return (value === ParserConstraints.#DOUBLE_EMPTY)
            case ParserName.DATE:
            case ParserName.DATETIME: return (value === ParserConstraints.#DATETIME_EMPTY) // Local Datetime is an Object, no cast needed
            case ParserName.TIME: return (value === ParserConstraints.#TIME_EMPTY)
            case ParserName.STRING: return (value === ParserConstraints.#STRING_EMPTY)
            case ParserName.NONE: return true // none values are empty by definition
            case ParserName.INT_LIST:
            case ParserName.STRING_LIST: return (Array.isArray(value)) ? (value.length === 0) : false
        }
    }

}