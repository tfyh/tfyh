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

/**
 * Enum PropertyName
 *
 * Represents a collection of predefined property names, each associated with a string value.
 * These properties are categorised into different groups for identification, user-facing settings,
 * node handling, value characteristics, validation rules, SQL representation, and input form-specific functionality.
 *
 * Categories:
 * - Identification properties: Utilised for unique identification of paths and names.
 * - Default user-facing properties: Define default values, labels, and descriptions.
 * - Node handling properties: Manage aspects of node handling, including permissions and addable types.
 * - Associated value properties: Characterise the allowable range, type, size, or unit of the value.
 * - Value handling rules: Define validation rules applicable to the associated value.
 * - SQL representation: Specify SQL-related attributes like data types, nullability, and indexing.
 * - Input form properties: Focus on user-input mechanisms, modifiers, and forms for record editing.
 * - Actual user-facing properties: Capture the actual values, labels, and descriptions relevant to end users.
 * - Fallback: Provides an "INVALID" property for unrecognised or unsupported names.
 *
 * Methods:
 * - valueOfOrInvalid(String $name): Attempts to match the given name to an existing case. Returns the
 *   corresponding PropertyName if found, or the INVALID case if no match exists.
 */
enum PropertyName: String {
    // Identification properties
    // both _PATH and _NAME start be purpose not with an uppercase letter to keep 'name' and 'path' free for child names.
    case _PATH = "_path";
    case _NAME = "_name";
    // default for the user-facing properties
    case DEFAULT_VALUE = "default_value";
    case DEFAULT_LABEL = "default_label";
    case DEFAULT_DESCRIPTION = "default_description";
    // node handling properties
    case NODE_HANDLING = "node_handling";
    case NODE_ADDABLE_TYPE = "node_addable_type";
    case NODE_WRITE_PERMISSIONS = "node_write_permissions";
    case NODE_READ_PERMISSIONS = "node_read_permissions";
    // properties of the associated value
    case VALUE_TYPE = "value_type";
    case VALUE_MIN = "value_min";
    case VALUE_MAX = "value_max";
    case VALUE_SIZE = "value_size";
    case VALUE_UNIT = "value_unit";
    case VALUE_REFERENCE = "value_reference";
    // handling of the associated value
    case VALIDATION_RULES = "validation_rules";
    // SQL representation of the associated value
    case SQL_TYPE = "sql_type";
    case SQL_NULL =  "sql_null";
    case SQL_INDEXED = "sql_indexed";
    // input form properties
    case INPUT_TYPE = "input_type";
    case INPUT_MODIFIER = "input_modifier";
    case RECORD_EDIT_FORM = "record_edit_form";
    // actual for the user-facing properties
    case ACTUAL_VALUE = "actual_value";
    case ACTUAL_LABEL = "actual_label";
    case ACTUAL_DESCRIPTION = "actual_description";
    // node handling properties

    // any other name
    case INVALID = "invalid";

    public static function valueOfOrInvalid(String $name): PropertyName {
        return PropertyName::tryFrom($name) ?? PropertyName::INVALID;
    }

}
