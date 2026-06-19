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
 * A class to provide all transcoding and validation for a record. Get the Record in question by
 * Record&#91;tableName&#93; Note that the premodification check is not performed here, but
 * on the server side.
 */
class Record {

    static copyCommonFields() {
        // read fields
        let tablesRoot = config.getItem(".tables");
        for (let recordItem of tablesRoot.getChildren()) {
            // collect what to copy and what to remove in this table
            let pseudoColumns = [];
            let toCopy = [];
            for (let fieldItem of recordItem.getChildren()) {
                if (fieldItem.name().startsWith("_")) {
                    if (!tablesRoot.hasChild(fieldItem.name()))
                        console.log(
                            "The common field set is missing: " + fieldItem.name());
                    else {
                        let commonFieldItem = tablesRoot.getChild(fieldItem.name())
                        if (commonFieldItem != null)
                            toCopy.push(commonFieldItem);
                        pseudoColumns.push(fieldItem);
                    }
                }
            }
            // both below copy and removal cannot be performed within the loop, because that
            // will raise a concurrent modification exception in the kotlin implementation.
            // copy common fields
            for (let commonFieldItem of toCopy)
                recordItem.copyChildren(commonFieldItem);
            // remove pseudo-fields
            for (let pseudoColumn of pseudoColumns) {
                // beware of the sequence. The child can be no more removed after being destroyed, because it loses its name.
                recordItem.removeChild(pseudoColumn)
                pseudoColumn.destroy()
            }
        }
        // remove pseudo-tables. Again, be aware of concurrent modification
        let pseudoTables = []
        for (let recordItem of tablesRoot.getChildren())
            if (recordItem.name().startsWith("_"))
                pseudoTables.push(recordItem);
        for (let pseudoTable of pseudoTables) {
            pseudoTable.parent().removeChild(pseudoTable)
            pseudoTable.destroy()
        }
    }

    /**
     * Parse a record as strings (a String map) into a record of native values.
     */
    static parseRow(strings, tableName, language = config.language()) {
        let item = config.getItem(".tables." + tableName)
        let record = new Record(item)
        record.parse(strings, language)
        return record.values()  // may be by reference, because $record is linked to nothing
    }

    #item
    #actualValues
    #writePermissions
    #writePermissionsOwn
    #readPermissions
    #readPermissionsOwn
    #userPermissionsAreSet

    constructor(item) {
        this.#item = item
        this.#actualValues = {}
    }

    /**
     * Return true, if the record is "owned", i.e. either the session user's user record or a record with the
     * session user's id in it (uuid or user_id).
     */
    #isOwn() {
        let userTableName = config.getItem(".framework.users.user_table_name").valueStr()
        let userIdFieldName = config.getItem(".framework.users.user_id_field_name").valueStr()
        let user = User.getInstance()
        let userUuid = user.uuid()
        let userShortUuid = userUuid.substring(0, 11)
        let userId = user.userId()
        // special case user table record: the field to use is always the user id field
        if (this.#item.name() === userTableName)
            return (this.value(userIdFieldName) === userId)
        // other records: check for userId and Uuid fields and their matching to the session user's values
        let isOwn = false
        for (let child of this.#item.getChildren()) {
            if (child.nodeHandling().indexOf("p") >= 0) {
                let fieldReference = child.valueReference().replace("$userTableName.","")
                if (ParserName.isList(child.type().parser())) {
                    let valueArray = this.value(child.name())
                    if (Array.isArray(valueArray)) {
                        // for uuids it is enough to match the short UUID, i.e. the first 11 characters
                        if ((fieldReference === "uuid") &&
                            ((valueArray.indexOf(userShortUuid) >= 0) || (valueArray.indexOf(userUuid) >= 0)))
                            isOwn = true
                        else if ((fieldReference === userIdFieldName) && (valueArray.indexOf(userId) >= 0))
                            isOwn = true
                    }
                } else {
                    // for uuids it is enough to match the short UUID, i.e. the first 11 characters
                    if ((fieldReference === "uuid") && (userUuid.startsWith(this.#valueCsv(child.name()))))
                        isOwn = true
                    else if ((fieldReference === userIdFieldName) && (userId === this.value(userIdFieldName)))
                        isOwn = true
                }
            }
        }
        // return result
        return isOwn
    }

    /**
     * Set the per-field permissions for the session user. Do this before calling filter()
     */
    #setPermissions() {
        let writeForbiddenForUser = [ "role", "user_id", "workflows", "concessions" ]
        let readForbiddenForOwn = [ "uuid" ]
        let user = User.getInstance()
        let userTableName = config.getItem(".framework.users.user_table_name").valueStr()
        let isUserTable = (this.#item.name() === userTableName)
        for (let childItem of this.#item.getChildren()) {
            let writePermissions = childItem.nodeWritePermissions()
            this.#writePermissions[childItem.name()] = user.isAllowedItem(writePermissions)
            if (isUserTable)
                this.#writePermissionsOwn[childItem.name()] = (writePermissions.indexOf("system") < 0)
                    && (writeForbiddenForUser.indexOf(childItem.name()) < 0)
            else
                this.#writePermissionsOwn[childItem.name()] = false
            this.#readPermissions[childItem.name()] = user.isAllowedItem(childItem.nodeReadPermissions())
            this.#readPermissionsOwn[childItem.name()] = (readForbiddenForOwn.indexOf(childItem.name()) < 0)
        }
        this.#userPermissionsAreSet = true
    }

    /**
     * Apply the permissions to $record. That will remove all fields from the record provided for which the session user
     * has no permission. If the record is returned empty, that means there is no write permission at all
     * for $record. The $value type (String, parsed, validated asf.) does not matter. NB: This does not change the
     * actual values of this. Calls setPermissions() first if that was not done before.
     */
    filter(record, forWrite) {
        if (!this.#userPermissionsAreSet)
            this.#setPermissions()
        if (this.#isOwn()) {
            if (forWrite) {
                for (name in this.#writePermissionsOwn)
                    if (this.#writePermissionsOwn[name] !== true) record.remove(name)
            } else {
                for (name in this.#readPermissionsOwn)
                    if (name !== true) record.remove(name)
            }
        } else {
            if (forWrite) {
                for (name in this.#writePermissions)
                    if (this.#writePermissionsOwn[name] !== true) record.remove(name)
            } else {
                for (name in this.#readPermissions)
                    if (this.#writePermissionsOwn[name] !== true) record.remove(name)
            }
        }
    }

    /**
     * Get the actual value. Uses the default if the actual value is empty.
     */
    value(name) {
        let field = this.#item.getChild(name)
        if (! this.#actualValues[name]
            || ParserConstraints.isEmpty(this.#actualValues[name], field.type().parser()))
            return field.defaultValue()
        return this.#actualValues[name]
    }

    /**
     * Get the actual value. Uses the default if the actual value is empty.
     */
    #valueCsv(name) {
        if (!this.#item.hasChild(name))
            return ""
        let field = this.#item.getChild(name)
        return Formatter.format(this.value(name), field.type().parser(), Language.CSV)
    }

    /**
     * Parse a map as was produced by Csv decomposition, form entering, or database read into this record's actual
     * values. This applies no validation. See the Findings class to get the parsing process findings. Returns
     * a list of changes applied to the valuesActual array as text, per change a line.
     */
    parse(map, language, logChanges = false) {
        Findings.clearFindings()
        let changesLog = ""
        let currentValues = {}
        if (logChanges)
            Object.assign(currentValues, this.#actualValues)
        this.#actualValues = {}   // clear the actual values but keep the never changing uid for reference
        if (currentValues["uid"])
            this.#actualValues["uid"] = currentValues["uid"];
        for (let fieldName in map) {
            if (this.#item.hasChild(fieldName)) {
                let entryStr = map[fieldName]
                let field = this.#item.getChild(fieldName)
                if ((entryStr != null) && (field != null)) {
                    let currentValue = currentValues[fieldName] ?? ParserConstraints.empty(field.type().parser())
                    let newValue = Parser.parse(entryStr, field.type().parser(), language)
                    // add to the actual values always only if different from the default.
                    if (!Validator.isEqualValues(newValue, field.defaultValue()))
                        this.#actualValues[fieldName] = newValue
                    if (logChanges && !Validator.isEqualValues(newValue, currentValue)) {
                        let loggedCurrent = Formatter.format(currentValue, field.type().parser(), language)
                        if (loggedCurrent.length > 50)
                            loggedCurrent = loggedCurrent.substring(0, 50) + " ..."
                        let loggedNew = Formatter.format(newValue.value(), language)
                        if (loggedNew.length > 50)
                            loggedNew = loggedNew.substring(0, 50) + " ..."
                        changesLog += loggedCurrent + " => " + loggedNew + "\n"
                    }
                }
            }
        }
        return changesLog
    }

    /**
     * Get all record's values as a map of parsed values.
     */
    values() {
        let values = {}
        for (let child of this.#item.getChildren())
            values[child.name()] = this.value(child.name());
        return values;
    }

    /**
     * Validate the actual values of the record against its constraints and validation rules. Skips field without an
     * actual value. See the Findings class to get the validation process findings.
     */
    validate() {
        Findings.clearFindings()
        for (let child of this.#item.getChildren()) {
            let actual = this.#actualValues[child.name()]
            if (actual != null)
                this.#actualValues[child.name()] = child.validate(actual)
        }
    }

    /**
     * Format a record's value as String. If the input_type is "password", this will return 10 stars "**********"
     */
    #formatValue(column, language) {
        let actualValue = this.#actualValues[column.name()]
        return ((actualValue != null) && (typeof actualValue !== 'undefined'))
            ? Formatter.format(actualValue, column.type().parser(), language)
            :  ""
    }

    /**
     * Provide a String to display, i.e. resolve all referencing, convenience shortcut using the name.
     */
    valueToDisplayByName(columnName, historyFieldName, language) {
        let column = this.#item.getChild(columnName)
        return (! column || !column.isValid()) ? "?" + columnName + "?" : this.#valueToDisplay(column, historyFieldName, language)
    }

    /**
     * Provide a String to display, i.e. resolve all referencing.
     */
    #valueToDisplay(column, historyFieldName, language) {
        let columnName = column.name();
        let value = this.value(columnName);
        let type = column.type();
        let reference = column.valueReference();
        let valueToDisplay = "";
        if (type.parser() === ParserName.BOOLEAN)
            valueToDisplay = (value === true) ? i18n.t("pQmiSd|true") : i18n.t("wqLUx1|false");
        else if (type.name() === "micro_time") {
            if (parseFloat(value) >= ParserConstraints.FOREVER_SECONDS)
                valueToDisplay += i18n.t("2xog20|never");
            else
                valueToDisplay = Formatter.microTimeToString(value, language);
        } else if (columnName === historyFieldName) {
            let tableName = column.parent().name();
            let uid = this.#valueCsv("uid")
            valueToDisplay = "<a href='../_pages/viewRecordHistory.php?table=" + tableName + "&uid=" + uid + "'>" +
                i18n.t("UcNTLA|show versions") + "</a>";
        } else if (reference.length > 0) {
            let elements = (Array.isArray(value)) ? value : [ value ];
            let indices = Indices.getInstance();
            let userIdFieldName = config.getItem(".framework.users.user_id_field_name").valueStr();
            for (let element of elements) {
                valueToDisplay += ", ";
                if (reference.endsWith("uuid")) {
                    let elementToDisplay = indices.getNameForUuid(element, reference.split(".")[0]);
                    if (type.name().startsWith("uuid_or_name") && (elementToDisplay === indices.missingNotice))
                        valueToDisplay += element
                    else
                        valueToDisplay += elementToDisplay
                }
                else if (reference.endsWith(userIdFieldName))
                    valueToDisplay += indices.getUserName(element)
                else if (reference.startsWith(".")) {
                    let referencedList = config.getItem(reference);
                    valueToDisplay += (referencedList.hasChild(element))
                        ? referencedList.getChild(element).label() : element;
                }
            }
            if (valueToDisplay.length > 0)
                valueToDisplay = valueToDisplay.substring(2);
        } else
            valueToDisplay = this.#formatValue(column, language)
        return valueToDisplay;
    }

    /**
     * Format the record's values as a map of names and formatted Strings. See the Findings class
     * to get the formatting process errors and warnings. The $fields array selects the columns o be formatted,
     * if set and not empty. Set $includeDefaults == false to select only those values which are different from their
     * default.
     */
    format(language, includeDefaults, fields) {
        if (fields.length === 0)
            for (let child of this.#item.getChildren())
                fields.push = child.name()
        Findings.clearFindings()
        let formatted = {}
        for (let field of fields)
            if ((this.#item.hasChild(field)) &&
                (includeDefaults || ((this.#actualValues[field] != null)
                    && (typeof this.#actualValues[field] != 'undefined'))))
            formatted[field] = this.#formatValue(this.#item.getChild(field), language);
        return formatted
    }

    /**
     * Format the record's values as a map of names and referenced Strings. See the Findings class
     * to get the formatting process errors and warnings. The $fields array selects the columns o be formatted,
     * if set and not empty. Set $includeDefaults == false to select only those values which are different from their
     * default.
     */
    formatToDisplay(language, includeDefaults, fields) {
        if (fields.length === 0)
            for (let child of this.#item.getChildren())
                fields.push = child.name()
        Findings.clearFindings();
        let historyFieldName = config.getItem(".framework.database_connector.history").valueStr()
        let formatted = {};
        for (let field of fields) {
            if ((this.#item.hasChild(field)) &&
                (includeDefaults || ((this.#actualValues[field] != null)
                    && (typeof this.#actualValues[field] != 'undefined')))) {
                let child = this.#item.getChild(field);
                formatted[child.name()] = this.#valueToDisplay(child, historyFieldName, language);
            }
        }
        return formatted;
    }

    /**
     * Return the record as an HTML table: key, value, type. The history field is providing a history link.
     */
    toHtmlTable(language) {
        let historyFieldName = config.getItem(".framework.database_connector.history").valueStr()
        let html = "<table><tr><th>" + i18n.t("sC5sYJ|property") + "</th><th>" +
            i18n.t("o474TC|value") + "</th></tr>";
        let nullValues = "";
        for (let columnItem in this.#item.getChildren()) {
            let column = columnItem.name()
            let value = this.value(column)
            let type = columnItem.type()
            if (ParserConstraints.isEmpty(value, type.parser()))
                nullValues += ", " + columnItem.label()
            else if ((this.#actualValues[column] == null)
                || (typeof this.#actualValues[column] === 'undefined'))
                nullValues += "; " + columnItem.label()
            else {
                let technicallyDisplay = "(" + type + ")";
                if (type === "micro_time")
                    technicallyDisplay = "(" + value + ")";
                else if (column === historyFieldName)
                    technicallyDisplay = "";
                else if (columnItem.valueReference().length > 0) {
                    let formatted = Formatter.format(value, type.parser(), config.language());
                    technicallyDisplay = "(" + ((formatted.length > 12)
                        ? formatted.substring(0, 11) + "..." : formatted) + ")";
                }
                let valueToDisplay =  this.#valueToDisplay(columnItem, historyFieldName, language);
                html += "<tr><td>" + columnItem.label() + "</td><td>" + valueToDisplay + " " + technicallyDisplay + "</td></tr>\n";
            }
        }

        if (nullValues.length > 2)
            html += "<tr><td>" + i18n.t("eiCoTk|empty data fields") + "</td><td>" +
                nullValues.substring(2) + "</td><td></td></tr>\n"
        return html + "</table>"
    }

    /**
     * Helper to create an edit form for the record.
     */
    #addEditFormField(columnItem, i) {
        let modifier = columnItem.inputModifier();
        let cName = columnItem.name();
        if ((i % 2) === 0)
            return "r;" + modifier + cName;
        else
            return "," + modifier + cName + ";\n";
    }

    /**
     * Create a form definition based on the Records columns.
     */
    defaultEditForm() {
        let defaultForm = "rowTag;names;labels\n";
        // system fields
        defaultForm += "r;§systemFields;" + i18n.t("KKbFTN|System fields") + "\n";
        let i = 0;
        let columnItem;
        for (columnItem of this.#item.getChildren())
            if (columnItem.nodeHandling.toString().indexOf("s") >= 0)
                defaultForm += this.#addEditFormField(columnItem, i++);
        // close the form line if the last field was left-hand side
        if ((i % 2) !== 0) defaultForm += ",;\n";

        // period fields (only versioned records)
        if (this.#item.hasChild("valid_from")) {
            defaultForm += "R;§validityFields;" + i18n.t("hfCAVH|Period validity") + "\n";
            defaultForm += "r;valid_from,invalid_from;\n";
        }

        // content fields
        defaultForm += "R;§contentFields;" + i18n.t("nHAnn0|Record content") + "\n";
        i = 0;
        let handling;
        for (columnItem of this.#item.getChildren()) {
            handling = columnItem.nodeHandling();
            if ((handling.toString().indexOf("s") < 0) // system fields marker
                && (handling.toString().indexOf("v") < 0) // period validity fields marker
                && (handling.toString().indexOf("x") < 0)) // extended fields marker
                defaultForm += this.#addEditFormField(columnItem, i++);
        }
        if ((i % 2) !== 0) defaultForm += ",;\n";

        // extra fields
        defaultForm += "R;§extraFields;" + i18n.t("d0z4Oi|Expert fields") + "\n";
        i = 0;
        for (columnItem of this.#item.getChildren()) {
            handling = columnItem.nodeHandling();
            if (handling.toString().indexOf("x") >= 0)  // extended fields marker
                defaultForm += this.#addEditFormField(columnItem, i++);
        }
        if ((i % 2) !== 0) defaultForm += ",;\n";

        return defaultForm + "R;submit;" + i18n.t("Er1g83|Save changes") + "\n";
    }

    /**
     * Get a String representing the row by using its template
     */
    rowToTemplate(templateName, row) {
        return this.#toTemplateOrFields(templateName, false, row)
    }
    /**
     * Get a String representing the record's values by using its template
     */
    recordToTemplate(templateName) {
        return this.#toTemplateOrFields(templateName, false)
    }

    /**
     * Get an array (field name => count of usages) of all fields used by this template
     */
    templateFields(templateName) {
        return this.#toTemplateOrFields(templateName, true)
    }

    #toTemplateOrFields(templateName, getFields, row = null) {
        let recordTemplates = this.#item.valueStr().split("\n");
        let recordTemplate = ""
        let usedFields = {}

        let currentTemplate = ""
        for (let templateDefinition in recordTemplates) {
            let pair = templateDefinition.split(":")
            let nextTemplate = templateDefinition.substring(templateDefinition.indexOf(":") + 1).trim()
            currentTemplate = (nextTemplate.startsWith("~"))
                ? currentTemplate + nextTemplate.substring(1) : nextTemplate
            if ((pair.size > 1) && (pair[0] === templateName))
                recordTemplate = currentTemplate
        }
        recordTemplate = recordTemplate.replace(" // ", "\n")

        let historyFieldName = config.getItem(".framework.database_connector.history").valueStr()
        let language = config.language()
        for (let child in this.#item.getChildren()) {
            let token = "{#" + child.name() + "#}"
            if (recordTemplate.indexOf(token) >= 0) {
                if (getFields) {
                    let currentCount = usedFields[child.name()] ?? 0
                    usedFields[child.name()] = currentCount + 1
                } else {
                    let text = (row == null)
                        ? this.#valueToDisplay(child, historyFieldName, language) : row[child.name()]
                    if (text.length > 0)
                        recordTemplate = recordTemplate.replace(token, text)
                    else {
                        if (recordTemplate.indexOf("($token)") >= 0)
                            recordTemplate.replace("($token)", "")
                        else if (recordTemplate.indexOf("[$token]") >= 0)
                            recordTemplate.replace("[$token]", "")
                        else if (recordTemplate.indexOf("<$token>") >= 0)
                            recordTemplate.replace("<$token>", "")
                        else
                            recordTemplate.replace(token, "")
                        recordTemplate = recordTemplate.trim()
                    }
                }
            }
        }
        return (getFields) ? usedFields : recordTemplate
    }

    // No JavaScript implementation
    isOk() {}
    // No JavaScript implementation
    store() {}
    // No JavaScript implementation
    modify() {}
}
