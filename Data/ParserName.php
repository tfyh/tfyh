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

/**
 * Defines an enumeration of parser names representing various data types and parsing strategies.
 *
 * The `ParserName` enum is used to facilitate the selection of a specific parser for interpreting or
 * processing values. Each case corresponds to a unique data type or behaviour, including support for
 * lists and formatting of values.
 *
 * - `BOOLEAN`: Represents a boolean value.
 * - `INT`: Represents an integer value.
 * - `INT_LIST`: Represents a list of integers.
 * - `LONG`: Represents a long integer value.
 * - `DOUBLE`: Represents a double-precision floating-point value.
 * - `DATE`: Represents a date value. Compatible with native date types.
 * - `DATETIME`: Represents a date-time value. Compatible with native LocalDate or LocalDateTime.
 * - `TIME`: Represents a time value stored natively as an integer.
 * - `STRING`: Represents a string value with no parsing or formatting applied.
 * - `STRING_LIST`: Represents a list of strings with no parsing or formatting applied.
 * - `NONE`: Represents a state where no value is accepted and will always parse to an empty string ("").
 *   Typically used for property definitions, such as default values or range constraints, where
 *   the parser is not fixed like other properties.
 */
enum ParserName: String {
    case BOOLEAN = "boolean";
    case INT = "int";
    case INT_LIST = "int_list";
    case LONG = "long";
    case DOUBLE = "double";
    // native numeric
    case DATE = "date";
    case DATETIME = "datetime";
    // native LocalDate, LocalDateTime
    case TIME = "time"; // native int
    case STRING = "string"; // native String. No parsing, no formatting applied
    case STRING_LIST = "string_list"; // native String. No parsing, no formatting applied
    case NONE = "none"; // no value accepted will always parse to ""
    // case NONE is used within the descriptor definition to reference to the value parser as
    // parser for the properties default_value, value_min, and value_max which is not fix
    // like the parser for all other properties.

    /**
     * Resolves the provided name to a corresponding ParserName instance or returns ParserName::NONE if the name is null or does not match.
     *
     * @param string|null $name The name to resolve. Can be null.
     * @return ParserName The corresponding ParserName instance if resolved, otherwise ParserName::NONE.
     */
    public static function valueOfOrNone(?String $name): ParserName {
        return (!is_null($name)) ? (ParserName::from(strtolower($name)) ?? ParserName::NONE) : ParserName::NONE;
    }

    /**
     * Determines if the provided parser name corresponds to a list type.
     *
     * @param ParserName $parserName The parser name to check.
     * @return bool True if the parser name represents a list type, false otherwise.
     */
    public static function isList(ParserName $parserName): bool {
        return ($parserName == self::INT_LIST) || ($parserName == self::STRING_LIST);
    }
}