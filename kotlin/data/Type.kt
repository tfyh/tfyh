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

import org.tfyh.util.I18n

class Type(definition: Map<String, String>) {

    companion object {

        // The list of types available
        private val types: MutableMap<String, Type> = mutableMapOf()
        val invalid =
            Type(mapOf("_name" to "invalid", "default_label" to "invalid", "parser" to "none"))

        /* ------------------------------------------------------------------------ */
        /* ----- PROPERTY GETTER -------------------------------------------------- */
        /* ------------------------------------------------------------------------ */

        /**
         * Initialise the descriptor and types
         */
        fun init(descriptorCsv: String, typesCsv: String) {
            // initialise the descriptor
            var definitionsArray = Codec.csvToMap(descriptorCsv)
            Property.descriptor.clear()
            Property.descriptor[PropertyName.INVALID] = Property.invalid
            for (definition in definitionsArray)
                Property.descriptor[PropertyName.valueOfOrInvalid(definition["name"] ?: "invalid")] =
                    Property(definition)
            // initialise the types catalogue.
            definitionsArray = Codec.csvToMap(typesCsv)
            types.clear()
            for (definition in definitionsArray)
                 types[definition["_name"] ?: "Oops! No name."] = Type(definition)
        }

        /**
         * Return the requested type. If there is no match, return Type.invalidType, which is
         * created here during bootstrap.
         */
        operator fun get(typeName: String) : Type {
            return types[typeName] ?: invalid
        }
    }

    // the type properties define the content of the actual value, e.g. its limits, its parser,
    // its form input and SQL representation asf.
    private val _name: String = definition["_name"] ?: "missing_type_name"
    private val _parser: ParserName = ParserName.valueOfOrNone(definition["parser"] ?: "missing_type_parser")
    // set the properties. Parse them and ensure the immutable properties are set
    private var properties: MutableMap<PropertyName, Any> = Property.parseProperties(definition, this).toMutableMap()
    init {
        this.properties[PropertyName._NAME] = this._name
        this.properties[PropertyName._PATH] = ""
        this.properties[PropertyName.VALUE_TYPE] = this._name  // for a Type, the value type equals the name.
    }

    fun name() = this._name
    fun parser() = this._parser

    // property getter functions for defaults. Only used by Item and Record
    internal fun defaultValue() = properties[PropertyName.ITEM_VALUE] ?: ParserConstraints.empty(this.parser())
    /**
     * Return the localised, i.e. translated property
     */
    internal fun typeLabel() = I18n.getInstance().t(properties[PropertyName.ITEM_LABEL] as String? ?: "")
    /**
     * Return the localised, i.e. translated property
     */
    internal fun typeDescription() = I18n.getInstance().t(properties[PropertyName.ITEM_DESCRIPTION] as String? ?: "")

    // property getter functions for other
    fun nodeHandling() = properties[PropertyName.NODE_HANDLING] as String? ?: ""
    fun nodeAddableType() = properties[PropertyName.NODE_ADDABLE_TYPE] as String? ?: ""
    fun nodeWritePermissions() = properties[PropertyName.NODE_WRITE_PERMISSIONS] as String? ?: ""
    fun nodeReadPermissions() = properties[PropertyName.NODE_READ_PERMISSIONS] as String? ?: ""

    // Types are immutable and do not have actual values.
    fun valueMin() = properties[PropertyName.VALUE_MIN] ?: ParserConstraints.min(this.parser())
    fun valueMax() = properties[PropertyName.VALUE_MAX] ?: ParserConstraints.max(this.parser())
    fun valueSize() = properties[PropertyName.VALUE_SIZE] as Int? ?: 0
    fun valueUnit() = properties[PropertyName.VALUE_UNIT] as String? ?: ""

    fun valueReference() = properties[PropertyName.VALUE_REFERENCE] as String? ?: ""
    fun validationRules() = properties[PropertyName.VALIDATION_RULES] as String? ?: ""

    fun sqlType() = properties[PropertyName.SQL_TYPE] as String? ?: ""
    fun sqlNull() = properties[PropertyName.SQL_NULL] as Boolean? ?: false
    fun sqlIndexed() = properties[PropertyName.SQL_INDEXED] as String? ?: ""

    fun inputType() = properties[PropertyName.INPUT_TYPE] as String? ?: "text"
    fun inputModifier() = properties[PropertyName.INPUT_MODIFIER] as String? ?: ""
    fun recordEditForm() = properties[PropertyName.RECORD_EDIT_FORM] as String? ?: ""

    override fun toString() = _name

}
