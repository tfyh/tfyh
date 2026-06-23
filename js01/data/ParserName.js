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

const ParserName = Object.freeze({
    BOOLEAN: "boolean",
    INT: "int",
    INT_LIST: "int_list",
    LONG: "long",
    DOUBLE: "double",
    // native numeric
    DATE: "date",
    DATETIME: "datetime",
    // native LocalDate, LocalDateTime
    TIME: "time", // native int
    STRING: "string", // native String. No parsing, no formatting applied
    STRING_LIST: "string_list", // native String. No parsing, no formatting applied
    NONE: "none", // no value accepted, will always parse to ""
    // NONE is used within the descriptor definition to reference to the value parser as
    // parser for the properties default_value, value_min, and value_max which is not fix
    // like the parser for all other properties.

    // name String to PropertyName resolution function
    valueOfOrNone: function(name) {
        for (let parserName of Object.keys(ParserName))
            if (name === ParserName[parserName])
                return ParserName[parserName]
        return ParserName.NONE
    },

    // check whether a parser expects a list rather than a single value
    isList: function(parserName) {
        return (parserName === ParserName.INT_LIST) || (parserName === ParserName.STRING_LIST)
    }
});

