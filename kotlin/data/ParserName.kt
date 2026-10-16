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

enum class ParserName {
    BOOLEAN, INT, LONG, DOUBLE, // native numeric
    DATE, DATETIME, // native LocalDate, LocalDateTime
    TIME, // native int
    STRING, // native String. No parsing, no formatting applied
    INT_LIST, STRING_LIST, // Strings which shall be parsed as array
    NONE; // no value accepted, will always parse to ""
    // case NONE is used within the descriptor definition to reference to the value parser as
    // parser for the properties value_actual, default_value, value_min, and value_max which
    // is not fix like the parser for all other properties.

    companion object {

        fun valueOfOrNone(name: String): ParserName {
            var p: ParserName = NONE
            try {
                p = ParserName.valueOf(name.uppercase())
            } catch (_: Exception) {}
            return p
        }

        fun isList(parserName: ParserName) = (parserName == INT_LIST) || (parserName == STRING_LIST)
    }

}