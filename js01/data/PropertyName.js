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

const PropertyName = Object.freeze({
    // Identification properties
    // both _PATH and _NAME start be purpose not with an uppercase letter to keep 'name' and 'path' free for child names.
    _PATH: "_path",
    _NAME: "_name",
    // default for the user-facing properties
    DEFAULT_VALUE: "default_value",
    DEFAULT_LABEL: "default_label",
    DEFAULT_DESCRIPTION: "default_description",
    // node handling properties
    NODE_HANDLING: "node_handling",
    NODE_ADDABLE_TYPE: "node_addable_type",
    NODE_WRITE_PERMISSIONS: "node_write_permissions",
    NODE_READ_PERMISSIONS: "node_read_permissions",
    // properties of the associated value
    VALUE_TYPE: "value_type",
    VALUE_MIN: "value_min",
    VALUE_MAX: "value_max",
    VALUE_SIZE: "value_size",
    VALUE_UNIT: "value_unit",
    VALUE_REFERENCE: "value_reference",
    // handling of the associated value
    VALIDATION_RULES: "validation_rules",
    // SQL representation of the associated value
    SQL_TYPE: "sql_type",
    SQL_NULL: "sql_null",
    SQL_INDEXED: "sql_indexed",
    // input form properties
    INPUT_TYPE: "input_type",
    INPUT_MODIFIER: "input_modifier",
    RECORD_EDIT_FORM: "record_edit_form",
    // actual for the user-facing properties
    ACTUAL_VALUE: "actual_value",
    ACTUAL_LABEL: "actual_label",
    ACTUAL_DESCRIPTION: "actual_description",
    // any other name
    INVALID: "invalid",

    // name String to PropertyName resolution function
    valueOfOrInvalid: function(name) {
        for (let propertyName of Object.keys(PropertyName))
            if (name === PropertyName[propertyName])
                return PropertyName[propertyName]
        return PropertyName.INVALID
    }

});
