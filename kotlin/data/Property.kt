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
import org.tfyh.util.Language

@Suppress("Unused")
class Property (definition: Map<String, String>) {
        private val propertyName: String = definition["_name"] ?: "missing_property_field__name"
        private val propertyLabel: String = definition["label"] ?: "missing_property_field__label"
        private val propertyDescription: String =
            definition["description"] ?: "missing_property_field__description"
        private val propertyParser: ParserName = ParserName.valueOfOrNone(
            definition["parser"] ?: "missing_property_field__description")

    fun name(): String { return propertyName }
    fun label(): String { return I18n.getInstance().t(propertyLabel) }
    fun description(): String { return I18n.getInstance().t(propertyDescription) }
    fun parser(valueType: Type): ParserName { return if (propertyParser == ParserName.NONE) valueType.parser() else propertyParser }
    fun usesParserOfValue(): Boolean { return (propertyParser == ParserName.NONE) }

    companion object {

        private val invalidPropertyDefinition = mapOf(
            "_name" to "invalid", "default_label" to "invalid",
            "default_description" to "invalid name for property used."
        )
        val invalid = Property(invalidPropertyDefinition)

        // and the properties descriptor
        internal val descriptor: MutableMap<PropertyName, Property> = mutableMapOf()

        /**
         * Parse a definition map of properties. Return those which are not empty.
         */
        internal fun parseProperties(definition: Map<String, String>, type: Type): Map<PropertyName, Any> {
            val properties: MutableMap<PropertyName, Any> = mutableMapOf()
            for (name in definition.keys) {
                val propertyDefinition = definition[name]
                if (propertyDefinition != null) {
                    // identify the parser to apply
                    val propertyName = PropertyName.valueOfOrInvalid(name)
                    val property = descriptor[propertyName] ?: invalid
                    val propertyParser = property.parser(type)
                    // parse and take in, if not empty.
                    if (propertyName != PropertyName.INVALID) {
                        val parsedProperty = Parser.parse(
                            propertyDefinition, propertyParser, Language.CSV)
                        if (!ParserConstraints.isEmpty(parsedProperty, propertyParser))
                            properties[propertyName] = parsedProperty
                    }
                }
            }
            return properties.toMap()
        }

        /**
         * Sort a set of property name strings according to the order provided in the PropertyName Enum.
         */
        fun sortProperties(propertyNames: MutableList<String>): MutableList<String> {
            val sorted = mutableListOf<String>()
            for (propertyName in PropertyName.entries)
                if (propertyNames.contains(propertyName.name.lowercase()))
                    sorted.add(propertyName.name.lowercase())
            return sorted
        }

        /**
         * Make sure that objects are really copied for date and datetime.
         */
        internal fun copyOfValue(value: Any): Any {
            return when (value) {
                is NoZoneDateTime -> NoZoneDateTime.parse(value.toString())
                else -> value
            }
        }

        private val isReadOnlySet = listOf(PropertyName._NAME, PropertyName._PATH, PropertyName.VALUE_TYPE)
        private val isValueSet = listOf(PropertyName.ITEM_VALUE, PropertyName.VALUE_MIN, PropertyName.VALUE_MAX)
        /**
         * Immutable properties must never change, i.e. must not be set except on type or item instantiation.
         */
        internal fun isImmutable(propertyName: PropertyName) = isReadOnlySet.contains(propertyName)
        /**
         * Value properties have no fixed parser but use the parser of the type.
         */
        internal fun isValue(propertyName: PropertyName) = isValueSet.contains(propertyName)

    }
}