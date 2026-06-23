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

class Item {

    #parentItem;
    #name;
    #type;
    #properties = [];
    #children = []

    #isPackaged = false;

    // for display management only, temporary property
    state = 0;
    position = -1;

    /**
     * Names of properties and functions of the  Array and object type. Using these
     * names may lead to issues.
     */
    static #discouragedNames = [
        //  Array type
        "at","concat","copyWithin","entries","every",
        "fill","filter","find","findIndex","findLast","findLastIndex","flat","flatMap","forEach","from",
        "fromAsync","includes","indexOf","isArray","join","keys","lastIndexOf","length","map","of","pop","push",
        "reduce","reduceRight","reverse","shift","slice","some","sort","splice","toLocaleString",
        "toReversed","toSorted","toSpliced","toString","unshift","values","with",
        //  Object
        "__defineGetter__","__defineSetter__","__lookupGetter__",
        "__lookupSetter__","assign","create","defineProperties","defineProperty","entries","freeze",
        "fromEntries","getOwnPropertyDescriptor","getOwnPropertyDescriptors","getOwnPropertyNames",
        "getOwnPropertySymbols","getPrototypeOf","groupBy","hasOwn","hasOwnProperty","is","isExtensible",
        "isFrozen","isPrototypeOf","isSealed","keys","preventExtensions","propertyIsEnumerable","seal",
        "setPrototypeOf","toLocaleString","toString","valueOf","values"
    ]

    // Create a free floating item. To be used for the "invalid item" and the config root node.
    static getFloating(definition) { return new Item(null, definition) }

    /**
     * Sort all top-level branches according to the canonical sequence.
     */
    static sortTopLevel() {
        let sortCache = []
        for (let topBranchName of Config.allSettingsFiles)
            if (config.getItem("." + topBranchName) !== config.rootItem) // happens with the descriptor e.g.
                sortCache.push(config.getItem("." + topBranchName))
        config.rootItem.#children = sortCache
    }

    constructor(parentItemSetter, definition, isPackaged) {
        this.#name = (definition["_name"]) ?? "missing_name";
        this.#isPackaged = isPackaged;
        if (PropertyName.valueOfOrInvalid(this.#name) !== PropertyName.INVALID) {
            let errorMessage = "Forbidden child name " + this.#name + " detected at " + parentItemSetter.getPath() +
                ". Aborting.";
            console.log( errorMessage);
            _stopDirty();
        }
        if (Item.#discouragedNames.indexOf(this.#name) >= 0) {
            let errorMessage = "Discouraged child name " + this.#name + " detected at " + parentItemSetter.getPath() +
                ". Changed to " + this.#name + "!";
            console.log( errorMessage);
            this.#name += "!";
        }
        if (parentItemSetter == null)
            this.#parentItem = this
        else {
            this.#parentItem = parentItemSetter
            this.#parentItem.#children.push(this)
        }
        // set the immutable properties
        this.#properties = []
        this.#properties[PropertyName._NAME] = this.#name
        this.#properties[PropertyName._PATH] = (parentItemSetter == null) ? "#none" : parentItemSetter.getPath()
        this.#type = Type.get(definition["value_type"] ?? "none") // the null case must never happen
        this.#properties[PropertyName.VALUE_TYPE] = this.#type.name()
        // set the children
        this.#children = [];
        if (definition["value_type"] === "template") {
            // if it is a template, copy the template
            let templatePath = definition["value_reference"] ?? "...";
            let templateItem = config.getItem(templatePath);
            if (templateItem.isValid()) {
                for (let templateChild of templateItem.#children) {
                    let newChild = new Item(this, {
                        _name: templateChild.name(),
                        value_type: templateChild.valueType()})
                    newChild.#mergeProperties(templateChild.#properties)
                }
            }
        }
        // parse the definition as properties and children's actual values.
        this.parseDefinition(definition)
    }

    /**
     * Convenience function to simplify the validity check.
     */
    isValid() { return this !== config.invalidItem }

    // There is no find() Method like in PHP, because finding is only available at the server side.

    // setter functions for properties
    // ===============================
    parseProperty(key, value, language) {
        let propertyName = PropertyName.valueOfOrInvalid(key)
        let property = Property.descriptor[propertyName] ?? Property.invalid
        let propertyParser = property.parser(this.#type)
        // parse and take in, if not empty.
        if (propertyName !== PropertyName.INVALID) {
            let parsedProperty = Parser.parse(value, propertyParser, language)
            if (!ParserConstraints.isEmpty(parsedProperty, propertyParser))
                this.#properties[propertyName] = parsedProperty
        }
    }
    /**
     * Parse a definition map into the item's properties and its children's actual values. Overwrite but keep existing
     * properties which are not in $definition. Immutable properties and unmatched fields are skipped.
     */
    parseDefinition(definition) {
        let newProperties = Property.parseProperties(definition, this.#type)
        this.#mergeProperties(newProperties)
        for (let child of this.#children)
            if (definition[child.name()])
                child.parseProperty("actual_value", definition[child.name()], config.language())
    }
    /**
     * Copy all $sourceProperties values into $this->properties except the immutable ones. Overwrite the existing,
     * but keep those which are not part of the $sourceProperties set.
     */
    #mergeProperties(sourceProperties) {
        for (let propertyName in sourceProperties)
            if (!Property.isImmutable(propertyName)) {
                let sourceProperty = sourceProperties[propertyName]
                if (sourceProperty != null)
                    this.#properties[propertyName] = Property.copyOfValue(sourceProperty)
            }
    }

    /**
     * Clear this item from all children and properties and do this with all items of its entire
     * branch recursively. The item itself will stay as an empty stub. Remove it by the caller.
     */
    destroy() {
        // delete all information
        this.#properties = {}
        // then drill down
        for (let child of this.#children)
            child.destroy()
        // clear the own children after they have cleared their properties
        this.#children = []
    }

    name() { return this.#name }
    /**
     * Return the path property, which is different from teh getPath(), because it is the path of the parent. Cf getPath()
     */
    path() { return this.#properties[PropertyName._PATH] ?? ".invalid" }
    type() { return this.#type }
    parent() { return this.#parentItem }

    // The defaultValue() is also used by the Record class therefore, it is not private
    defaultValue() { return this.#properties[PropertyName.DEFAULT_VALUE ?? this.#type.defaultValue()] }
    #defaultLabel() {
        return (this.#properties[PropertyName.DEFAULT_LABEL])
            ? i18n.t(this.#properties[PropertyName.DEFAULT_LABEL]) : this.#type.defaultLabel()
    }
    #defaultDescription() {
        return (this.#properties[PropertyName.DEFAULT_DESCRIPTION])
            ? i18n.t(this.#properties[PropertyName.DEFAULT_DESCRIPTION]) : this.#type.defaultDescription()
    }
    nodeHandling() { return this.#properties[PropertyName.NODE_HANDLING] ?? this.#type.nodeHandling() }
    nodeAddableType() { return this.#properties[PropertyName.NODE_ADDABLE_TYPE] ?? this.#type.nodeAddableType() }
    nodeWritePermissions() { return this.#properties[PropertyName.NODE_WRITE_PERMISSIONS] ?? this.#type.nodeWritePermissions() }
    nodeReadPermissions() { return this.#properties[PropertyName.NODE_READ_PERMISSIONS] ?? this.#type.nodeReadPermissions() }

    valueType() { return this.#type.name() }
    valueMin() { return this.#properties[PropertyName.VALUE_MIN] ?? this.#type.valueMin() }
    valueMax() { return this.#properties[PropertyName.VALUE_MAX] ?? this.#type.valueMax() }
    valueSize() { return this.#properties[PropertyName.VALUE_SIZE] ?? this.#type.valueSize() }
    valueUnit() { return this.#properties[PropertyName.VALUE_UNIT] ?? this.#type.valueUnit() }
    valueReference() { return this.#properties[PropertyName.VALUE_REFERENCE] ?? this.#type.valueReference() }
    validationRules() { return this.#properties[PropertyName.VALIDATION_RULES] ?? this.#type.validationRules() }

    sqlType() { return this.#properties[PropertyName.SQL_TYPE] ?? this.#type.sqlType() }
    sqlNull() { return this.#properties[PropertyName.SQL_NULL] ?? this.#type.sqlNull() }
    sqlIndexed() { return this.#properties[PropertyName.SQL_INDEXED] ?? this.#type.sqlIndexed() }

    inputType() { return this.#properties[PropertyName.INPUT_TYPE] ?? this.#type.inputType() }
    inputModifier() { return this.#properties[PropertyName.INPUT_MODIFIER] ?? this.#type.inputModifier() }
    recordEditForm() { return this.#properties[PropertyName.RECORD_EDIT_FORM] ?? this.#type.recordEditForm() }

    // value getter
    // ============

    // no "getActualValues() in JavaScript like int kotlin, because this is only used to store these on the server

    /**
     * Get the value. This will return the actual or the item default if no actual was set. If the
     * item default is also not set, the type default is used.
     */
    value() {
        return (ParserConstraints.isEmpty(this.#properties[PropertyName.ACTUAL_VALUE], this.#type.parser()))
            ? this.defaultValue()
            : this.#properties[PropertyName.ACTUAL_VALUE] }
    // shorthand functions to get the value as String.
    valueCsv() { return Formatter.formatCsv(this.value(), this.#type.parser()) }
    valueSql() { return Formatter.format(this.value(), this.#type.parser(), Language.SQL) }
    valueStr() { return Formatter.format(this.value(), this.#type.parser()) }
    // localised, i.e. translated properties
    label() {
        return (! this.#properties[PropertyName.ACTUAL_LABEL]) ? this.#defaultLabel()
            : i18n.t(this.#properties[PropertyName.ACTUAL_LABEL]); }
    description() {
        return (! this.#properties[PropertyName.ACTUAL_DESCRIPTION]) ? this.#defaultDescription()
            : i18n.t(this.#properties[PropertyName.ACTUAL_DESCRIPTION]); }

    isPackaged() { return this.#isPackaged; }

    isOfAddableType(item) {
        let itemTypeName = item.type().name()
        return ((itemTypeName === this.nodeAddableType()) ||
            ((itemTypeName === "template") && (item.defaultValue() === this.nodeAddableType())));
    }

    // Format the property value the "CSV language", but no csv encoding
    propertyCsv(propertyName) {
        let propertyValue = this.#properties[propertyName]
        if (!propertyValue)
            return "";
        let property = Property.descriptor[propertyName] ?? Property.invalid
        return Formatter.format(propertyValue, property.parser(this.#type), Language.CSV)
    }

    /**
     * Iterates through all children and returns true if the id was matched. If not,
     * false is returned.
     */
    hasChild(name) { return (this.getChild(name) != null) }

    /**
     * Returns the child with the given name, if existing, else null.
     */
    getChild(name) {
        for (let child of this.#children)
            if (child.#name === name)
                return child
        return null
    }

    /**
     * Return all children as a mutable array. Be careful not to change those.
     */
    getChildren() { return this.#children }

    /**
     * Returns the full path of the Item. The Item.path() will return the path property, which is the parent item's
     * path. For top level items getPath() will return ".topLevelName" and path() ""; for root and invalid getPath()
     * will return "" and path() "#none".
     */
    getPath() {
        if (this === config.rootItem)
            return "."
        let path = this.#name
        let current = this
        let passed = [ this.path() ] // path is a unique immutable String property of the item, not the "getPath" dynamic result.
        while (current.parent() !== current) {
            current = current.parent()
            if (passed.indexOf(current.path()) >= 0)
                return current.#name + "(#recursion#)." + path
            passed.push(current.path())
            path = (current === config.rootItem) ? "." + path : current.#name + "." + path
        }
        return path
    }

    /**
     * Copy the sourceItem's children to this item. Used by the Record class to propagate common record fields. No
     * drill down.
     */
    copyChildren(sourceItem) {
        for (let sourceChild of sourceItem.#children)
            if (!this.hasChild(sourceChild.#name)) {
                let ownChild = new Item(this,{
                    name: sourceChild.#name,
                    value_type: sourceChild.#type.name()
                })
                ownChild.#mergeProperties(sourceChild.#properties)
            }
    }

    /**
     * Reads the definition into a child item. This will create a new child if the child with the
     * name that is given in the definition does not exist. It will merge the properties if the
     * child exists. Returns false, only if the name or - for a not yet existing child - the value
     * type are missing in the definition or if the provided value type for a new child is invalid.
     */
    putChild(definition, isPackaged) {
        // a name and valid type must be provided in the definition
        if (! definition["_name"]) return false
        let childName = definition["_name"]
        // check whether the child already exists
        let child = this.getChild(childName)
        if (child != null) {
            // the child exists to replace the properties, but not the children
            child.parseDefinition(definition)
            return true
        }
        // for new children valid type must be provided in the definition
        if (! definition["value_type"]) return false
        let childTypeString = definition["value_type"]
        let childType = Type[childTypeString]
        if (childType === Type.invalid)
            return false
        new Item(this, definition, isPackaged)
        return true
    }

    /**
     * Remove the child item from this item's children array.
     */
    removeChild(child) {
        if (child != null)
            this.#children.splice(this.#children.indexOf(this), 1)
    }

    /**
     * Validate value against this item's constraints and validation rules. Returns an updated value.
     * e.g. when adjusted by the limit checks. If a value is left out or set null, the item's actual
     * value will be validated, updated, and returned. See the Findings class to get errors and warnings.
     */
    validate(value = null) {
        let validated
        if (value == null)
            validated = this.#properties[PropertyName.ACTUAL_VALUE] ?? ParserConstraints.empty(this.#type.parser())
        else {
            // empty values are always syntactically compliant
            if (ParserConstraints.isEmpty(value, this.#type.parser()))
                return value
            validated = value
        }
        // limit conformance
        validated = Validator.adjustToLimits(validated, this.#type, this.valueMin(), this.valueMax(), this.valueSize())
        // validation rules conformance
        Validator.checkAgainstRule(validated, this.validationRules());
        return validated
    }

    // get a readable String for debugging purposes
    toString() {
        if (this.valueCsv().length === 0)
            return this.#name + " (" + this.#type.name() + " => " + this.parent().getPath() + ")"
        return this.#name + "=" + this.valueCsv() + " (" + this.#type.name() + " => " + this.parent().getPath() + ")"
    }

    /**
     * Read a full branch from its definition array
     */
    readBranch(definitionsArray) {
        for (let definition of definitionsArray) {
            // read the relative path
            let path = definition["_path"]
            let name = definition["_name"]
            if ((path != null) && (name != null)) {
                let parent = config.getItem(path)
                if (!parent.isValid())
                    // an invalid parent means that the path could not be resolved
                    return "Failed to find parent '" + path + "' for child '" + name + "'"
                else {
                    let success = parent.putChild(definition)
                    if (!success)
                        // adding can fail if child names are duplicate
                        return "Failed to add child '" + name + "' at " + path
                }
            }
        }
        return ""
    }

    // no "readActualSettings()" in JavaScript like int kotlin, because the packaged settings are read from the server

    /**
     * Collect all items of this branch into a flat list rather than a tree
     */
    #collectItems(items, fieldNames, drillDown, level = 0) {
        items.push(this)
        for (let propertyName in this.#properties)
            if ((fieldNames.indexOf(propertyName) < 0))
                fieldNames.push(propertyName)
        if (level < drillDown)
            for (let child of this.#children)
                if (child !== this)
                    // avoid endless drill down loops. Misconfiguration can cause such situations
                    child.#collectItems(items, fieldNames, drillDown, level + 1)
    }

    /**
     * Sort all children of this item in alphabetical order of their names. No drill down.
     */
    sortChildrenByName() {
        this.#children.sort(
            function(a, b) { return a.name().localeCompare(b.name()) }
        );
    }

    /**
     * Sort all children to get all branches first or last, but do not change the inner sequence of
     * branches and leaves.
     */
    sortChildren(drillDown, branchesFirst) {
        // split children into branches and leafs
        let branchItems = [];
        let leafItems = [];
        for (let child of this.#children) {
            if ((child.#children.length > 0) || (child.nodeAddableType().length > 0))
                branchItems.push(child);
            else
                leafItems.push(child);
        }
        // now rearrange the children according to the rearranged names.
        this.#children = (branchesFirst) ? branchItems.concat(leafItems) : leafItems.concat(branchItems)

        // go for further levels, if required.
        if (drillDown > 0)
            for (let child of this.#children)
                if (child === this) {
                    let childrenPaths = ""
                    for (child of this.#children)
                        childrenPaths += child.getPath() + ", "
                    alert("Misconfiguration error! Item " + this + " has children: " + childrenPaths + " including itself. Aborting.");
                    _stopDirty()
                } else
                    // avoid endless drill down loops. Misconfiguration can cause such situations
                    child.sortChildren(drillDown - 1, branchesFirst);
        return true;
    }

    /**
     * Get the entire branch as a csv table.
     */
    branchToCsv(drillDown) {
        let items = [ this ]
        let fieldNames = [ this.#properties.keys() ]
        this.sortChildren(drillDown, false)
        this.#collectItems(items, fieldNames, drillDown)
        fieldNames = Property.sortProperties(fieldNames)
        let header = ""
        for (let fieldName of fieldNames)
            header += ";" + fieldName
        let csv = header.substring(1) + "\n"
        for (let item of items) {
            let rowCsv = ""
            for (let fieldName of fieldNames)
                rowCsv += ";" + Codec.encodeCsvEntry(item.propertyCsv(fieldName))
            csv += rowCsv.substring(1) + "\n"
        }
        return csv
    }

    getLevel() {
        if ((this === config.rootItem) || (this === config.invalidItem))
            return 0;
        return this.getPath().split(".").length - 1
    }

    /**
     * Move a child branch within the children sequence. The sequence is the one
     * created by adding the items. (See:
     * https://stackoverflow.com/questions/5525795/does-javascript-guarantee-object-property-order)
     */
    moveChild (item, by)
    {
        if (by === 0) // nothing to move
            return true;

        // identify the current and new item position
        let parent = item.parent()
        let fromPosition = parent.#children.indexOf(item);
        let toPosition = fromPosition + by;
        // do not move if target position is beyond the ends
        if ((toPosition >= parent.#children.length) || (toPosition < 0))
            return false;

        // now move the items in between fromPosition and toPosition
        // this will duplicate the name at the $to_position
        let end = Math.abs(by);
        let fwd = by / end;
        for (let i = 1; i <= end; i ++)
            parent.#children[fromPosition + ((i - 1) * fwd)] = parent.#children[fromPosition + (i * fwd)];
        // replace the name at the toPosition by the cached name
        parent.#children[toPosition] = item;
        return true;
    }

    /* -----------------------------------------------------------------*/
    /* ------ JAVASCRIPT ONLY CODE -------------------------------------*/
    /* -----------------------------------------------------------------*/

    /**
     * Add an empty child. This is used to provide a form for child creation. If the child contains a template,
     * all grandchildren are as well created. The child's name is empty, i.e. invalid, and must be given in the
     * insert form.
     */
    addEmptyChild() {
        let isTemplate = this.nodeAddableType().startsWith(".")
        return new Item(this, {
            name: "",
            value_type: (isTemplate) ? "template" : this.nodeAddableType(),
            value_reference: (isTemplate) ? this.nodeAddableType() : ""
        })
    }

    /**
     * Return the record as an HTML table: key, value, type. The history field is providing a history link.
     */
    toHtmlTable(language) {
        if (typeof language == 'undefined')
            language = config.language()
        let html = "<h4>" + this.#name + " <small>" + this.getPath() + "</small></h4>"
        html += "<table><tr><th>" + i18n.t("sC5sYJ|property") + "</th><th colspan='2'>" +
            i18n.t("o474TC|value") + "</th></tr>";
        let nullValues = "";
        for (let propertyName in this.#properties) {
            let label = i18n.t(Property.descriptor[propertyName].label())
            let value = this.#properties[propertyName]
            let property = Property.descriptor[propertyName] ?? Property.invalid
            let parser = property.parser(this.#type)
            if (ParserConstraints.isEmpty(value, parser))
                nullValues += ", " + propertyName.label()
            else if (this.#type.name() === "micro_time") {
                if (value >= ParserConstraints.FOREVER_SECONDS)
                    html += "<tr><td>" + label + "</td><td>" + i18n.t("2xog20|never") + "</td><td>" + value + "</td></tr>\n";
                else {
                    html += "<tr><td>" + label + "</td><td>" + Formatter.microTimeToString(value,language) +
                        "</td><td>" + value + "</td></tr>\n";
                }
            } else if (propertyName !== "_path") {
                let valueStr = Formatter.format(value, parser, language);
                if ((parser === "string") && i18n.isValidI18nReference(valueStr))
                    valueStr = i18n.t(valueStr)
                html += "<tr><td>" + label + "</td><td>" + valueStr + "</td><td>(" + parser + ")</td></tr>\n";
            }
        }
        if (nullValues.length > 2)
            html += "<tr><td>" + i18n.t("eiCoTk|empty data fields") + "</td><td>" +
                nullValues.substring(2) + "</td><td></td></tr>\n"
        return html + "</table>"
    }

    childrenToTableHtml() {
        if (this.#children.length === 0)
            return ""
        let html = "<h4>" + i18n.t("T9viNy|Properties") + "</h4>"
        html += "<table><tr><th>" + i18n.t("sC5sYJ|property") + "</th><th colspan='2'>" +
            i18n.t("o474TC|value") + "</th></tr>";
        for (let child of this.#children) {
            let label = child.label();
            let valueStr = child.valueStr()
            let technical = child.valueType()
            html += "<tr><td>" + label + "</td><td>" + valueStr + "</td><td>(" + technical + ")</td></tr>\n";
        }
        return html + "</table>"
    }

}
