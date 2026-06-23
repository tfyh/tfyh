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

class Property {

    #propertyName
    #propertyLabel
    #propertyDescription
    #propertyParser

    constructor(definition) {
        this.#propertyName = definition["name"] ?? "missing_property_field__name"
        this.#propertyLabel = definition["label"] ?? "missing_property_field__label"
        this.#propertyDescription = definition["description"] ?? "missing_property_field__description"
        this.#propertyParser = ParserName.valueOfOrNone(definition["parser"])
    }

    name() { return this.#propertyName }
    label() { return i18n.t(this.#propertyLabel) }
    description() { return i18n.t(this.#propertyDescription) }
    parser(valueType) { return (this.#propertyParser === ParserName.NONE) ? valueType.parser() : this.#propertyParser }

    static descriptor = {}
    static #invalidPropertyDefinition = { name: "invalid", label: "invalid",
        description: "invalid name for property used.", parser: "none" }
    static invalid = new Property(Property.#invalidPropertyDefinition)

    /**
     * Parse a definition map of properties. Return those which are not empty and neither "_name" nor "parser".
     * Used for Type and Item.
     */
    static parseProperties(definition, type) {
        let properties = {}
        for (name of Object.keys(definition)) {
            let propertyDefinition = definition[name];
            if (typeof propertyDefinition != 'undefined') {
                // identify the parser to apply
                let propertyName = PropertyName.valueOfOrInvalid(name)
                let property = Property.descriptor[propertyName] ?? Property.invalid
                let propertyParser = property.parser(type)
                // parse and take in, if not empty.
                if (propertyName !== PropertyName.INVALID) {
                    let parsedProperty = Parser.parse(propertyDefinition, propertyParser, Language.CSV)
                    if (!ParserConstraints.isEmpty(parsedProperty, propertyParser))
                        properties[propertyName] = parsedProperty
                }
            }
        }
        return properties
    }

    /**
     * Sort a set of property name Strings according to the order provided in the PropertyName Enum.
     */
    static sortProperties(propertyNames) {
        let sorted = []
        for (let propertyName of Object.values(PropertyName))
            if (propertyNames.indexOf(propertyName) >= 0)
                sorted.push(propertyName)
        return sorted
    }

    /**
     * Make sure that objects are really copied for date and datetime.
     */
    static copyOfValue(value) {
        if (value instanceof Date)
            return Parser.parse(Formatter.format(value, ParserName.DATETIME, Language.CSV), ParserName.DATETIME, Language.CSV)
        else
            return value
    }

    static #isImmutable = [PropertyName._NAME, PropertyName._PATH, PropertyName.VALUE_TYPE]
    static #isValue = [PropertyName.DEFAULT_VALUE, PropertyName.VALUE_MIN, PropertyName.VALUE_MAX,
        PropertyName.ACTUAL_VALUE]
    static #isActual = [PropertyName.ACTUAL_VALUE, PropertyName.ACTUAL_LABEL,
        PropertyName.ACTUAL_DESCRIPTION]
    /**
     * Immutable properties must never change, i.e. must not be set except on type or item instantiation.
     */
    static isImmutable(propertyName){ return Property.#isImmutable.indexOf(propertyName) >= 0; }
    /**
     * Value properties have no fixed parser but use the parser of the type.
     */
    static isValue(propertyName) { return Property.#isValue.indexOf(propertyName) >= 0; }
    /**
     * Actual properties are the ones which are set by the tenant. They are stored in a separate file
     */
    static isActual(propertyName) { return Property.#isActual.indexOf(propertyName) >= 0; }

}