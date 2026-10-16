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
 * The `Item` class represents a node within a hierarchical structure, containing a name, type, properties, and children.
 * It supports operations such as parsing definitions, validating its state, and searching through its hierarchy. The
 * Item is the core element of all configuration data and is the basis for the entire configuration system.
 *
 * This class also handles constraints and specific configurations, such as discouraged child names,
 * relationships with parent and child items, and mutability rules for properties.
 */
class Item {

    #_name;
    #_parentItem;
    #_type;
    #properties = [];
    #children = []

    #source = ""

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
    static getFloating(definition) { return new Item(null, definition, "") }

    /**
     * Sort all top-level branches according to the canonical sequence. Used for the Config-UI only, not in PHP, kotlin
     */
    static sortTopLevel() {
        let sortCache = []
        for (let topBranchName of Config.allSettingsFiles)
            if (!config.getItem("." + topBranchName).isRoot()) // happens with the descriptor e.g.
                sortCache.push(config.getItem("." + topBranchName))
        config.rootItem.#children = sortCache
    }

    constructor(parentItemSetter, definition, source) {
        this.#_name = (definition["_name"]) ?? "missing_name";
        this.#source = source;
        if (typeof this.#source === 'undefined')
            this.#source = ""
        if (PropertyName.valueOfOrInvalid(this.#_name) !== PropertyName.INVALID) {
            let errorMessage = "Forbidden child name " + this.#_name + " detected at " + parentItemSetter.fullPath() +
                ". Aborting.";
            console.log( errorMessage);
            _stopDirty();
        }
        if (Item.#discouragedNames.indexOf(this.#_name) >= 0) {
            let errorMessage = "Discouraged child name " + this.#_name + " detected at " + parentItemSetter.fullPath() +
                ". Changed to " + this.#_name + "!";
            console.log( errorMessage);
            this.#_name += "!";
        }
        if (parentItemSetter == null)
            this.#_parentItem = this
        else {
            this.#_parentItem = parentItemSetter
            this.#_parentItem.#children.push(this)
        }
        // set the immutable properties
        this.#properties = []
        this.#properties[PropertyName._NAME] = this.#_name
        this.#properties[PropertyName._PATH] = (parentItemSetter == null) ? "#none" : parentItemSetter.fullPath()
        this.#_type = Type.get(definition["value_type"] ?? "none") // the null case must never happen
        this.#properties[PropertyName.VALUE_TYPE] = this.#_type.name()
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
                        value_type: templateChild.valueType()
                    }, source)
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
    isRoot() { return ((this === config.rootItem) || (this === config.defaultsRootItem)) }

    // There is no find() Method like in PHP, because finding is only available at the server side.

    // setter functions for properties
    // ===============================
    parseProperty(key, value, language) {
        let propertyName = PropertyName.valueOfOrInvalid(key)
        let property = Property.descriptor[propertyName] ?? Property.invalid
        let propertyParser = property.parser(this.#_type)
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
        let newProperties = Property.parseProperties(definition, this.#_type)
        this.#mergeProperties(newProperties)
        for (let child of this.#children)
            if (definition[child.name()])
                child.parseProperty("item_value", definition[child.name()], config.language())
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

    name() { return this.#_name }
    /**
     * Return the path property, which is different from the fullPath(), because it is without the item's name part.
     */
    path() { return this.#properties[PropertyName._PATH] ?? ".invalid" }
    type() { return this.#_type }
    parent() { return this.#_parentItem }

    nodeHandling() { return this.#properties[PropertyName.NODE_HANDLING] ?? this.#_type.nodeHandling() }
    nodeAddableType() { return this.#properties[PropertyName.NODE_ADDABLE_TYPE] ?? this.#_type.nodeAddableType() }
    nodeWritePermissions() { return this.#properties[PropertyName.NODE_WRITE_PERMISSIONS] ?? this.#_type.nodeWritePermissions() }
    nodeReadPermissions() { return this.#properties[PropertyName.NODE_READ_PERMISSIONS] ?? this.#_type.nodeReadPermissions() }

    valueType() { return this.#_type.name() }
    valueMin() { return this.#properties[PropertyName.VALUE_MIN] ?? this.#_type.valueMin() }
    valueMax() { return this.#properties[PropertyName.VALUE_MAX] ?? this.#_type.valueMax() }
    valueSize() { return this.#properties[PropertyName.VALUE_SIZE] ?? this.#_type.valueSize() }
    valueUnit() { return this.#properties[PropertyName.VALUE_UNIT] ?? this.#_type.valueUnit() }
    valueReference() { return this.#properties[PropertyName.VALUE_REFERENCE] ?? this.#_type.valueReference() }
    validationRules() { return this.#properties[PropertyName.VALIDATION_RULES] ?? this.#_type.validationRules() }

    sqlType() { return this.#properties[PropertyName.SQL_TYPE] ?? this.#_type.sqlType() }
    sqlNull() { return this.#properties[PropertyName.SQL_NULL] ?? this.#_type.sqlNull() }
    sqlIndexed() { return this.#properties[PropertyName.SQL_INDEXED] ?? this.#_type.sqlIndexed() }

    inputType() { return this.#properties[PropertyName.INPUT_TYPE] ?? this.#_type.inputType() }
    inputModifier() { return this.#properties[PropertyName.INPUT_MODIFIER] ?? this.#_type.inputModifier() }
    recordEditForm() { return this.#properties[PropertyName.RECORD_EDIT_FORM] ?? this.#_type.recordEditForm() }

    // value getter
    // ============

    // no "getActualValues() in JavaScript like int kotlin, because this is only used to store these on the server

    /**
     * Get the value. This will return the default value of the item's value type, if the item's value is empty.
     * The value() function is also used by the Record class therefore, it is not private
     */
    value() { return this.#properties[PropertyName.ITEM_VALUE ?? this.#_type.defaultValue()] }
    // shorthand functions to get the value as String.
    valueCsv() { return Formatter.formatCsv(this.value(), this.#_type.parser()) }
    valueSql() { return Formatter.format(this.value(), this.#_type.parser(), Language.SQL) }
    valueStr() { return Formatter.format(this.value(), this.#_type.parser(), config.language()) }
    // localised, i.e. translated properties
    label() {
        return (this.#properties[PropertyName.ITEM_LABEL])
            ? i18n.t(this.#properties[PropertyName.ITEM_LABEL]) : this.#_type.typeLabel()
    }
    description() {
        return (this.#properties[PropertyName.ITEM_DESCRIPTION])
            ? i18n.t(this.#properties[PropertyName.ITEM_DESCRIPTION]) : this.#_type.typeDescription()
    }

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
        return Formatter.format(propertyValue, property.parser(this.#_type), Language.CSV)
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
            if (child.#_name === name)
                return child
        return null
    }

    /**
     * Return all children as a mutable array. Be careful not to change those.
     */
    getChildren() { return this.#children }

    /**
     * Returns the full path of the Item. The Item.path() will return the path property, which is the parent item's
     * path. For top level items fullPath() will return ".topLevelName" and path() ""; for root and invalid fullPath()
     * will return "" and path() "#none".
     */
    fullPath() {
        if (this === config.rootItem)
            return "."
        let path = this.#_name
        let current = this
        let passed = [ this.path() ] // path is a unique immutable String property of the item, not the "fullPath" dynamic result.
        while (current.parent() !== current) {
            current = current.parent()
            if (passed.indexOf(current.path()) >= 0)
                return current.#_name + "(#recursion#)." + path
            passed.push(current.path())
            path = (current.isRoot()) ? "." + path : current.#_name + "." + path
        }
        return path
    }

    /**
     * Copy the sourceItem's children to this item. Used by the Record class to propagate common record fields. No
     * drill down.
     */
    copyChildren(sourceItem) {
        for (let sourceChild of sourceItem.#children)
            if (!this.hasChild(sourceChild.#_name)) {
                let ownChild = new Item(this,{
                    name: sourceChild.#_name,
                    value_type: sourceChild.#_type.name()
                }, this.#source)
                ownChild.#mergeProperties(sourceChild.#properties)
            }
    }

    /**
     * Reads the definition into a child item. This will create a new child if the child with the
     * name that is given in the definition does not exist. It will merge the properties if the
     * child exists. Returns false, only if the name or - for a not yet existing child - the value
     * type are missing in the definition or if the provided value type for a new child is invalid.
     */
    putChild(definition, source) {
        // a name and valid type must be provided in the definition
        if (! definition["_name"]) return false
        let childName = definition["_name"]
        // check whether the child already exists
        let child = this.getChild(childName)
        if (child != null) {
            // the child exists to replace the properties, but not the children
            child.parseDefinition(definition)
            // replace the source information. It will thus always be the source of the last item change.
            if ((source.length > 0) && (child.#source.indexOf(source) < 0))
                child.#source = (child.#source + " " + source).trim()
            return true
        }

        // for new children a valid type must be provided in the definition
        if (! definition["value_type"]) return false

        let childTypeString = definition["value_type"]
        let childType = Type[childTypeString]
        if (childType === Type.invalid)
            return false
        // create a new item with this as parent, thus accessible
        new Item(this, definition, source)
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
            validated = this.#properties[PropertyName.ITEM_VALUE] ?? ParserConstraints.empty(this.#_type.parser())
        else {
            // empty values are always syntactically compliant
            if (ParserConstraints.isEmpty(value, this.#_type.parser()))
                return value
            validated = value
        }
        // limit conformance
        validated = Validator.adjustToLimits(validated, this.#_type, this.valueMin(), this.valueMax(), this.valueSize())
        // validation rules conformance
        Validator.checkAgainstRule(validated, this.validationRules());
        return validated
    }

    // get a readable String for debugging purposes
    toString() {
        if (this.valueCsv().length === 0)
            return this.#_name + " (" + this.#_type.name() + " => " + this.parent().fullPath() + ")"
        return this.#_name + "=" + this.valueCsv() + " (" + this.#_type.name() + " => " + this.parent().fullPath() + ")"
    }

    /**
     * Read a source file into the settings tree.  Existing items will be overwritten.
     */
    readSourceFile(source) {
        let settingsCsv = LocalCache.getInstance().getItem(source);
        if (!settingsCsv || (settingsCsv.length === 0))
            return
        let settings = Codec.csvToMap(settingsCsv);
        for (let setting of settings) {
            // read the relative path
            let path = setting["_path"]
            let name = setting["_name"]
            if ((path != null) && (name != null)) {
                let parent = config.getItem(path)
                if (!parent.isValid())
                    // an invalid parent means that the path could not be resolved
                    return
                else {
                    let success = parent.putChild(setting, source)
                    if (!success)
                        // adding can fail if child names are duplicate
                        return
                }
            }
        }
    }

    // no "readActualSettings()" in JavaScript like int kotlin, because the packaged settings are read from the server

    /**
     * Collect all items of this branch into a flat list rather than a tree
     */
    #collectItems(source, items, fieldNames, drillDown, level, tenantOnly) {
        for (let child of this.#children) {
            if (child !== this) { // avoid endless drill down loops. Misconfiguration can cause such situations
                if (child.source.indexOf(source) >= 0) {
                    let propertiesToUse = (tenantOnly) ? child.#nonDefaultProperties() : child.#properties;
                    if (propertiesToUse.length > 0) {
                        items.push(child)
                        for (let propertyName in propertiesToUse)
                            if ((fieldNames.indexOf(propertyName) < 0))
                                fieldNames.push(propertyName)
                    }
                }
                if (level < drillDown)
                    child.#collectItems(source, items, fieldNames, drillDown, level + 1, tenantOnly)
            }
        }
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
                        childrenPaths += child.fullPath() + ", "
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
    branchToCsv(drillDown, source, tenantOnly) {
        if (tenantOnly) {
            // load the default branch
            let topBranchPath = "." + source.substring(source.lastIndexOf("/") + 1)
            config.loadBranch(topBranchPath, true);
        }
        let items = (this.#source.endsWith("packaged")) ? [ this ] : []
        let fieldNames = [ this.#properties.keys() ]
        this.sortChildren(drillDown, false)
        this.#collectItems(source, items, fieldNames, drillDown, 0, tenantOnly)
        let fieldNamesSorted = Property.sortProperties(fieldNames)
        let header = ""
        for (let fieldName of fieldNamesSorted)
            header += ";" + fieldName
        let csv = header.substring(1) + "\n"
        for (let item of items) {
            let rowCsv = ""
            let nonDefaultProperties
            if (tenantOnly)
                nonDefaultProperties = item.#nonDefaultProperties();
            for (let fieldName of fieldNamesSorted) {
                let valueCsv =
                    (!tenantOnly || (typeof (nonDefaultProperties)[fieldName] !== 'undefined')) ?
                        (";" + Codec.encodeCsvEntry(item.propertyCsv(fieldName))) : ""
                rowCsv += ";" + valueCsv
            }
            csv += rowCsv.substring(1) + "\n"
        }
        return csv
    }

    /**
     * Compare item with its 'default' and return all properties which have a different value.
     */
    #nonDefaultProperties() {
        let defaultReference = config.getItem(this.fullPath(), true)
        if (defaultReference.isValid())
            return this.#properties;

        let differentProperties = {}
        for (let propertyName in this.#properties) {
            if (this.#properties[propertyName] !== defaultReference.#properties[propertyName])
                differentProperties[propertyName] = this.#properties[propertyName]
        }
        return differentProperties
    }

    /**
     * Get the level in the hierarchy, i.e. 0 fot thr root, 1 for the top-branch items, 2 for
     * their children asf.
     * @returns {number} the level.
     */
    getLevel() {
        if ((this === config.rootItem) || (this === config.invalidItem))
            return 0;
        return this.fullPath().split(".").length - 1
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
        let source = "Config/tenant/" + this.path().split(".")[1]
        return new Item(this, {
                name: "",
                value_type: (isTemplate) ? "template" : this.nodeAddableType(),
                value_reference: (isTemplate) ? this.nodeAddableType() : ""
            }, source)
    }

    /**
     * Return the record as an HTML table: key, value, type. The history field is providing a history link.
     */
    toHtmlTable(language) {
        if (typeof language == 'undefined')
            language = config.language()
        let html = "<h4>" + this.#_name + " <small>" + this.fullPath() + " [" + this.#source + "]</small></h4>"
        html += "<table><tr><th>" + i18n.t("sC5sYJ|property") + "</th><th colspan='2'>" +
            i18n.t("o474TC|value") + "</th></tr>";
        let nullValues = "";
        for (let propertyName in this.#properties) {
            let label = i18n.t(Property.descriptor[propertyName].label())
            let value = this.#properties[propertyName]
            let property = Property.descriptor[propertyName] ?? Property.invalid
            let parser = property.parser(this.#_type)
            if (ParserConstraints.isEmpty(value, parser))
                nullValues += ", " + propertyName.label()
            else if (this.#_type.name() === "micro_time") {
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
