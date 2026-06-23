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
 * The Type class represents a descriptor for a specific type, containing properties, its parser,
 * and related metadata used in managing and interpreting data values within the system. Types are immutable
 * and do never have actual values.
 *
 * This class provides functionality for initialising, retrieving, and working with types
 * and their associated metadata. It ensures the immutability of types and encapsulates logic
 * for interpreting type properties, constraints, and behaviours.
 */
class Type {

    // The list of types available
    static #types = {}
    static invalid = new Type({name: "invalid", default_label: "invalid", parser: "none"} )

    /**
     * Initialise the descriptor and types
     */
    static init(descriptorCsv, typesCsv) {
        // initialise descriptor
        let definitionsArray = Codec.csvToMap(descriptorCsv)
        Property.descriptor = {}
        Property.descriptor[PropertyName.INVALID] = Property.invalid
        for (let definition of definitionsArray)
            Property.descriptor[PropertyName.valueOfOrInvalid(definition["name"])] = new Property(definition)
        // initialise the type catalogue.
        definitionsArray = Codec.csvToMap(typesCsv)
        Type.#types = {}
        for (let definition of definitionsArray)
            Type.#types[definition["_name"] ?? "Oops! No name."] = new Type(definition)
    }

    /**
     * Return the requested type. If there is no match, return Type.invalidType, which is
     * created here during bootstrap.
     */
    static get(typeName) { return Type.#types[typeName] ?? Type.invalid }

    #name = "";
    #parser = "";
    #properties = [];
    constructor(definition) {
        // the type properties define the content of the actual value, e.g. its limits, its parser,
        // its form input and SQL representation asf.
        this.#name = definition["_name"] ?? "missing_type_name"
        this.#parser = ParserName.valueOfOrNone(definition["parser"])
        // set the properties. Parse them first and ensure the immutable properties are set.
        this.#properties = Property.parseProperties(definition, this)
        this.#properties[PropertyName._NAME] = this.#name
        this.#properties[PropertyName._PATH] = ""
        this.#properties[PropertyName.VALUE_TYPE] = this.#name
    }

    name() { return this.#name }
    label() { return this.defaultLabel() }
    description() { return this.defaultDescription() }
    parser() { return this.#parser }
    // property getter functions
    #stringProperty(propertyName) { return this.#properties[propertyName] ?? "" }
    defaultValue() {
        return (this.#properties[PropertyName.DEFAULT_VALUE]) ?
            this.#properties[PropertyName.DEFAULT_VALUE] : ParserConstraints.empty(this.#parser)
    }
    /**
     * Return the localised, i.e. translated property
     */
    defaultLabel() { return i18n.t(this.#stringProperty(PropertyName.DEFAULT_LABEL)); }
    /**
     * Return the localised, i.e. translated property
     */
    defaultDescription() { return i18n.t(this.#stringProperty(PropertyName.DEFAULT_DESCRIPTION)); }

    nodeHandling() { return this.#stringProperty(PropertyName.NODE_HANDLING); }
    nodeAddableType() { return this.#stringProperty(PropertyName.NODE_ADDABLE_TYPE); }
    nodeWritePermissions() { return this.#stringProperty(PropertyName.NODE_WRITE_PERMISSIONS); }
    nodeReadPermissions() { return this.#stringProperty(PropertyName.NODE_READ_PERMISSIONS); }

    // Types are immutable and do not have actual values.
    valueMin() {
        return (this.#properties[PropertyName.VALUE_MIN]) ?
            this.#properties[PropertyName.VALUE_MIN] :
            ParserConstraints.min(this.#parser)
    }
    valueMax() {
        return (this.#properties[PropertyName.VALUE_MAX]) ?
            this.#properties[PropertyName.VALUE_MAX] :
            ParserConstraints.max(this.#parser)
    }
    valueSize() {
        return (this.#properties[PropertyName.VALUE_SIZE]) ?
            this.#properties[PropertyName.VALUE_SIZE] : 0
    }
    valueUnit() { return this.#stringProperty(PropertyName.VALUE_UNIT); }

    valueReference() { return this.#stringProperty(PropertyName.VALUE_REFERENCE); }
    validationRules() { return this.#stringProperty(PropertyName.VALIDATION_RULES); }

    sqlType() { return this.#stringProperty(PropertyName.SQL_TYPE); }
    sqlNull() {
        return (this.#properties[PropertyName.SQL_NULL]) ?
            this.#properties[PropertyName.SQL_NULL] : false
    }
    sqlIndexed() { return this.#stringProperty(PropertyName.SQL_INDEXED); }

    inputType() {
        return (this.#properties[PropertyName.INPUT_TYPE]) ?
            this.#properties[PropertyName.INPUT_TYPE] : "text"
    }
    inputModifier() { return this.#stringProperty(PropertyName.INPUT_MODIFIER); }
    recordEditForm() { return this.#stringProperty(PropertyName.RECORD_EDIT_FORM); }

}
