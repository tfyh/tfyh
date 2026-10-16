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

@Suppress("Unused")
internal enum class PropertyName {

    // Identification properties
    // both _PATH and _NAME start be purpose not with an uppercase letter to keep 'name' and 'path' free for child names.
    @Suppress("EnumEntryName")
    _PATH,
    @Suppress("EnumEntryName")
    _NAME,
    // default for the user facing properties
    ITEM_LABEL, ITEM_DESCRIPTION, ITEM_VALUE,
    // node handling properties
    NODE_ADDABLE_TYPE, NODE_WRITE_PERMISSIONS, NODE_READ_PERMISSIONS, NODE_HANDLING,
    // properties of the associated value
    VALUE_TYPE, VALUE_MIN, VALUE_MAX, VALUE_SIZE, VALUE_UNIT,
    // handling of the associated value
    VALUE_REFERENCE, VALIDATION_RULES,
    // SQL representation of the associated value
    SQL_TYPE, SQL_NULL, SQL_INDEXED,
    // input form properties
    INPUT_TYPE, INPUT_MODIFIER, RECORD_EDIT_FORM,
    // any other name
    INVALID;

    companion object {

        fun valueOfOrInvalid(name: String): PropertyName {
            var p: PropertyName = INVALID
            try {
                p = PropertyName.valueOf(name.uppercase())
            } catch (_: Exception) {}
            return p
        }

        /**
         * Immutable properties must never change, i.e. must not be set except on type or item instantiation.
         */
        fun isImmutable(propertyName: PropertyName) =
            listOf(_NAME, _PATH, VALUE_TYPE).contains(propertyName)
        /**
         * Value properties have no fixed parser but use the parser of the type.
         */
        fun isValue(propertyName: PropertyName) =
            listOf(ITEM_VALUE, VALUE_MIN, VALUE_MAX).contains(propertyName)

    }
}
