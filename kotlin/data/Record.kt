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

import org.tfyh.control.LoggerSeverity
import org.tfyh.util.I18n
import org.tfyh.util.Language
import org.tfyh.util.User

/**
 * A class to provide all transcoding and validation for a record. Get the Record in question by
 * Record&#91;tableName&#93;
 */
@Suppress("Unused")
class Record (internal val item: Item) {

    companion object {

        fun copyCommonFields() {
            val config = Config.getInstance()
            // read fields
            val tablesRoot = config.getItem(".tables")
            for (recordItem in tablesRoot.getChildren()) {
                // collect what to copy and what to remove in this table
                val pseudoColumns = mutableListOf<Item>()
                val toCopy = mutableListOf<Item>()
                for (fieldItem in recordItem.getChildren()) {
                    if (fieldItem.name().startsWith("_")) {
                        if (!tablesRoot.hasChild(fieldItem.name()))
                            Config.getInstance().logger.log(
                                LoggerSeverity.ERROR,
                                "Record->readTables()",
                                "The common field set is missing: " + fieldItem.name()
                            )
                        else {
                            val commonFieldItem = tablesRoot.getChild(fieldItem.name())
                            if (commonFieldItem != null)
                                toCopy.add(commonFieldItem)
                            pseudoColumns.add(fieldItem)
                        }
                    }
                }
                // both below copy and removal cannot be performed within the loop, because that
                // will raise a concurrent modification exception (only in the kotlin implementation).
                // copy common fields
                for (commonFieldItem in toCopy)
                    recordItem.copyChildren(commonFieldItem)
                // remove pseudo-fields
                for (pseudoColumn in pseudoColumns) {
                    pseudoColumn.parent().removeChild(pseudoColumn)
                    pseudoColumn.destroy()
                }
            }
            // remove pseudo-tables. Again beware of concurrent modification
            val pseudoTables = mutableListOf<Item>()
            for (recordItem in tablesRoot.getChildren())
                if (recordItem.name().startsWith("_"))
                    pseudoTables.add(recordItem)
            for (pseudoTable in pseudoTables) {
                // beware of the sequence. The child can no more removed after being destroyed,
                // because it loses its name.
                pseudoTable.parent().removeChild(pseudoTable)
                pseudoTable.destroy()
            }
        }

        /**
         * Parse a record as strings (a String map) into a record of native values.
         */
        fun parseRow(row: MutableMap<String, String>, tableName: String,
                     language: Language = Config.getInstance().language()): Map<String, Any> {
            val item = Config.getInstance().getItem(".tables.$tableName")
            val record = Record(item)  // temporary object
            record.parse(row, language)
            return record.values() // may be by reference, because $record is linked to nothing
        }

    }

    private val recordValues: MutableMap<String, Any> = mutableMapOf()
    private val writePermissions: MutableMap<String, Boolean> = mutableMapOf()
    private val writePermissionsOwn: MutableMap<String, Boolean> = mutableMapOf()
    private val readPermissions: MutableMap<String, Boolean> = mutableMapOf()
    private val readPermissionsOwn: MutableMap<String, Boolean> = mutableMapOf()
    private var userPermissionsAreSet = false

    /**
     * Return true, if the record is "owned", i.e. either the session user's user record or a record with the
     * session user's id in it (uuid or user_id).
     */
    private fun isOwn(): Boolean {
        val config = Config.getInstance()
        val userTableName = config.getItem(".framework.users.user_table_name").valueStr()
        val userIdFieldName = config.getItem(".framework.users.user_id_field_name").valueStr()
        val user = User.getInstance()
        val userUuid = user.uuid()
        val userShortUuid = userUuid.substring(0, 11)
        val userId = user.userId()
        // special case user table record: the field to use is always the user-id field
        if (item.name() == userTableName)
            return (value(userIdFieldName) == userId)
        // other records: check for userId and Uuid fields and their matching to the session user's values
        var isOwn = false
        for (child in item.children) {
            if (child.nodeHandling().contains("p")) {
                val fieldReference = child.valueReference().replace("userTableName.","")
                if (ParserName.isList(child.type().parser())) {
                    try {
                        val valueArray = value(child.name()) as Array<*>
                        // for uuids it is sufficient to match the short UUID, i.e. the first 11 characters
                        if ((fieldReference == "uuid") &&
                            ((valueArray.indexOf(userShortUuid) >= 0) || (valueArray.indexOf(userUuid) >= 0)))
                            isOwn = true
                        else if ((fieldReference == userIdFieldName) && (valueArray.indexOf(userId) >= 0))
                            isOwn = true
                    } catch (_: Exception) {}
                } else {
                    // for uuids it is sufficient to match the short UUID, i.e. the first 11 characters
                    if ((fieldReference == "uuid") && (userUuid.startsWith(valueCsv(child.name()))))
                            isOwn = true
                    else if ((fieldReference == userIdFieldName) && (userId == value(userIdFieldName)))
                        isOwn = true
                }
            }
        }
        // return result
        return isOwn
    }

    /**
     * Set the per field permissions for the session user. Do this before calling filter()
     */
    private fun setPermissions() {
        val writeForbiddenForUser = listOf("role", "user_id", "workflows", "concessions")
        val readForbiddenForOwn = listOf("uuid")
        val user = User.getInstance()
        val config = Config.getInstance()
        val userTableName = config.getItem(".framework.users.user_table_name").valueStr()
        val isUserTable = item.name() == userTableName
        for (childItem in item.children) {
            val writePermissions = childItem.nodeWritePermissions()
            this.writePermissions[childItem.name()] = user.isAllowedItem(writePermissions)
            this.writePermissionsOwn[childItem.name()] = isUserTable && ((writePermissions.indexOf("system") < 0)
                    && (writeForbiddenForUser.indexOf(childItem.name()) < 0))
            this.readPermissions[childItem.name()] = user.isAllowedItem(childItem.nodeReadPermissions())
            this.readPermissionsOwn[childItem.name()] = (readForbiddenForOwn.indexOf(childItem.name()) < 0)
        }
        userPermissionsAreSet = true
    }

    /**
     * Apply the permissions to record. That will remove all fields from the record provided for which the session user
     * has no permission. If the record is returned empty that means, there is no write permission at all
     * for record. The value type (String, parsed, validated asf.) does not matter. NB: This does not change the
     * actual values of this. Calls setPermissions() first, if that was not done before.
     */
    fun filter(record: MutableMap<String, Any>, forWrite: Boolean) {
        if (!userPermissionsAreSet)
            setPermissions()
        if (isOwn()) {
            if (forWrite) {
                for (name in this.writePermissionsOwn.keys)
                    if (this.writePermissionsOwn[name] != true) record.remove(name)
            } else {
                for (name in this.readPermissionsOwn.keys)
                    if (this.writePermissionsOwn[name] != true) record.remove(name)
            }
        } else {
            if (forWrite) {
                for (name in this.writePermissions.keys)
                    if (this.writePermissionsOwn[name] != true) record.remove(name)
            } else {
                for (name in this.readPermissions.keys)
                    if (this.writePermissionsOwn[name] != true) record.remove(name)
            }
        }
    }

    /**
     * Get the actual value. Uses defaults, if the actual value is empty.
     */
    fun value(name: String): Any {
        val field = this.item.getChild(name)
        val recordValue = this.recordValues[name]
        val parser = field?.type()?.parser() ?: ParserName.NONE
        return if ((recordValue == null) || ParserConstraints.isEmpty(recordValue, parser))
            field?.value() ?: Exception()
        else
            recordValue
    }

    /**
     * Get the actual value. Uses defaults, if the actual value is empty.
     */
    private fun valueCsv(name: String): String {
        val field = item.getChild(name) ?: return ""
        return Formatter.format(value(name), field.type().parser(), Language.CSV)
    }

    /**
     * Parse a map as was produced by Csv decomposition, form entering or database read into this record's actual
     * values. This applies no validation. See the Findings class to get the parsing process findings. Returns
     * a list of changes applied to the valuesActual array as text, per change a line.
     */
    fun parse(map: Map<String, String>, language: Language, logChanges: Boolean = false): String {
        Findings.clearFindings()
        var changesLog = ""
        val currentValues = if (logChanges) this.recordValues.toMap() else emptyMap()
        this.recordValues.clear() // clear the actual values, but keep the never changing uid for reference
        if (currentValues["uid"] != null)
            this.recordValues["uid"] = currentValues["uid"] ?: ""
        for (fieldName in map.keys) {
            if (item.hasChild(fieldName)) {
                val entryStr = map[fieldName]
                val field = item.getChild(fieldName)
                if ((entryStr != null) && (field != null)) {
                    val newValue = Parser.parse(entryStr, field.type().parser(), language)
                    val currentValue = currentValues[fieldName] ?: ParserConstraints.empty(field.type().parser())
                    // add to the actual values always only if different from the default.
                    if (!Validator.isEqualValues(newValue, field.value()))
                        recordValues[fieldName] = newValue
                    if (logChanges && !Validator.isEqualValues(newValue, currentValue)) {
                        var loggedCurrent = Formatter.format(currentValue, field.type().parser(), language)
                        if (loggedCurrent.length > 50)
                            loggedCurrent = loggedCurrent.substring(0, 50) + " ..."
                        var loggedNew = formatValue(field, language)
                        if (loggedNew.length > 50)
                            loggedNew = loggedNew.substring(0, 50) + " ..."
                        changesLog += "$loggedCurrent => $loggedNew\n"
                    }
                }
            }
        }
        return changesLog
    }

    /**
     * Get all record's values as a map of parsed values.
     */
    fun values(): Map<String, Any> {
        val values = mutableMapOf<String, Any>()
        for (child in item.children)
            values[child.name()] = this.value(child.name())
        return values.toMap()
    }

    /**
     * Validate the actual values of the record against its constraints and validation rules. Skips field without an
     * actual value. See the Findings class to get the validation process findings.
     */
    fun validate() {
        Findings.clearFindings()
        for (child in item.children) {
            val actual = recordValues[child.name()]
            if (actual != null)
                recordValues[child.name()] = child.validate(actual)
        }
    }

    /**
     * Format a record's value as String. If the input_type is "password", this will return 10 stars "**********"
     */
    fun formatValue(column: Item, language: Language, includeDefaults: Boolean = false): String {
        val actualValue = recordValues[column.name()]
        return if (actualValue != null) 
            Formatter.format(actualValue, column.type().parser(), language)
        else {
            if (includeDefaults)
                Formatter.format(column.value(), column.type().parser(), language)
            else ""
        }
    }

    /**
     * Provide a String to display, i.e. resolve all referencing, convenience shortcut using the name.
     */
    fun valueToDisplayByName(columnName: String, language: Language): String {
        val column = item.getChild(columnName)
        return if (column?.isValid() != true) "?$columnName?" else valueToDisplay(column, language)
    }

    /**
     * Provide a String to display, i.e. resolve all referencing.
     */
    fun valueToDisplay(column: Item, language: Language): String {
        if (!column.isValid()) return "?invalidColumn?"
        val i18n = I18n.getInstance()
        val config = Config.getInstance()
        val columnName = column.name()
        val value = this.value(columnName)
        val type = column.type()
        val reference = column.valueReference()
        var valueToDisplay = ""
        if (type.parser() === ParserName.BOOLEAN)
            valueToDisplay = if (value == true) i18n.t("lhMPZA|true") else i18n.t("ed9xCV|false")
        else if (type.name() == "micro_time") {
            if ((value as Double) >= ParserConstraints.FOREVER_SECONDS)
                valueToDisplay += i18n.t("2xog20|never")
            else
                valueToDisplay = Formatter.microTimeToString(value, language)
        } else if (reference.isNotEmpty()) {
            val elements = value as? MutableList<*> ?: mutableListOf(value)
            val indices = Indices.getInstance()
            val userIdFieldName = config.getItem(".framework.users.user_id_field_name").valueStr()
            for (element in elements) {
                valueToDisplay += ", "
                if (reference.endsWith("uuid") && (element is String)) {
                    val elementToDisplay = indices.getNameForUuid(element, reference.split(".")[0])
                    valueToDisplay += if (type.name().startsWith("uuid_or_name") && (elementToDisplay == indices.missingNotice))
                        element
                    else
                        elementToDisplay
                }
                else if (reference.endsWith(userIdFieldName) && (element is Int))
                    valueToDisplay += indices.getUserName(element)
                else if (reference.startsWith(".") && (element is String)) {
                    val referencedList = config.getItem(reference)
                    valueToDisplay += if (referencedList.hasChild(element))
                        (referencedList.getChild(element)?.label() ?: element) else element
                }
            }
            if (valueToDisplay.isNotEmpty())
                valueToDisplay = valueToDisplay.substring(2)
        } else
            valueToDisplay = this.formatValue(column, language)
        return valueToDisplay
    }

    /**
     * Format the record's values as a map of names and formatted Strings. See the Findings class
     * to get the formatting process errors and warnings. The $fields array selects the columns o be formatted,
     * if set and not empty. Set $includeDefaults == false to select only those values which are different from their
     * default.
     */
    fun format(language: Language, includeDefaults: Boolean, fieldNames: List<String> = emptyList()): Map<String, String> {
        if (fieldNames.isEmpty())
            for (child in this.item.getChildren())
                fieldNames.plus(child.name())
        Findings.clearFindings()
        val formatted = mutableMapOf<String, String>()
        for (fieldName in fieldNames)
            if (item.hasChild(fieldName) &&
                (includeDefaults || this.recordValues[fieldName] != null)) {
                val child = this.item.getChild(fieldName)
                if (child != null)
                    formatted[fieldName] = this.formatValue(child, language)
            }
        return formatted
    }

    /**
     * Format the record's values as a map of names and referenced Strings. See the Findings class
     * to get the formatting process errors and warnings. The $fields array selects the columns o be formatted,
     * if set and not empty. Set $includeDefaults == false to select only those values which are different from their
     * default.
     */
    fun formatToDisplay(language: Language, includeDefaults: Boolean, fieldNames: List<String> = emptyList()): MutableMap<String, String> {
        val fieldNamesToUse = fieldNames.toMutableList()
        if (fieldNamesToUse.isEmpty())
            for (child in this.item.getChildren())
                fieldNamesToUse.add(child.name())
        Findings.clearFindings()
        val formatted = mutableMapOf<String, String>()
        for (fieldName in fieldNamesToUse)
            if (item.hasChild(fieldName) &&
                (includeDefaults || this.recordValues[fieldName] != null)) {
                val child = this.item.getChild(fieldName)
                if (child != null)
                    formatted[fieldName] = this.valueToDisplay(child, language)
            }
        return formatted
    }

    /**
     * Return the record as HTML table; not implemented in kotlin, only for JavaScript and PHP.
     */
    fun toHtmlTable(language: Language): String {
        return "not implemented"
    }

    /**
     * STILL TO BE IMPLEMENTED. Create a form definition based on the Records columns.
     */
    fun defaultEditForm(): String {
        return "R;§uid;"
        // TODO: to be implemented. Not yet used in JavaScript.
    }

    /**
     * Get a String representing the row by using its template
     */
    fun rowToTemplate(templateName: String, row: Map<String, String>): String {
        return toTemplateOrFields(templateName, false, row).elementAt(0)
    }
    /**
     * Get a String representing the record's values by using its template
     */
    fun recordToTemplate(templateName: String): String {
        return toTemplateOrFields(templateName, false).elementAt(0)
    }

    /**
     * Get an array (field name => count of usages) of all fields used by this template
     */
    fun templateFields(templateName: String): List<String> {
        return toTemplateOrFields(templateName, true)
    }

    /**
     * get the fields of a template
     */
    private fun toTemplateOrFields(templateName: String, getFields: Boolean,
                                   row: Map<String, String>? = null): List<String> {
        val recordTemplates = item.value() as List<*>
        var recordTemplate = ""
        val usedFields = mutableListOf<String>()
        var currentTemplate = ""
        for (templateDefinition in recordTemplates) {
            val pair = (templateDefinition as String).split(":")
            val nextTemplate = templateDefinition.substring(templateDefinition.indexOf(":") + 1).trim()
            currentTemplate =
                if (nextTemplate.startsWith("~")) currentTemplate + nextTemplate.substring(1)
                else nextTemplate
            if ((pair.size > 1) && (pair[0] == templateName))
                recordTemplate = currentTemplate
        }
        recordTemplate = recordTemplate.replace(" // ", "\n")
        val language = Config.getInstance().language()
        for (child in item.getChildren()) {
            val token = "{#" + child.name() + "#}"
            if (recordTemplate.contains(token)) {
                if (getFields) {
                    if (usedFields.indexOf(child.name()) < 0)
                        usedFields.add(child.name())
                } else {
                    val text = if (row == null) valueToDisplay(child, language) else row[child.name()]
                    recordTemplate = if (text?.isNotEmpty() == true)
                        recordTemplate.replace(token, text)
                    else {
                        if (recordTemplate.contains("($token)"))
                            recordTemplate.replace("($token)", "")
                        else if (recordTemplate.contains("[$token]"))
                            recordTemplate.replace("[$token]", "")
                        else if (recordTemplate.contains("<$token>"))
                            recordTemplate.replace("<$token>", "")
                        else
                            recordTemplate.replace(token, "")
                    }
                }
            }
        }
        return if (getFields) usedFields.toList() else listOf(recordTemplate.trim())
    }

    // no kotlin implementation yet
    fun isOk() {}
    // no kotlin implementation yet
    fun store() {}
    // no kotlin implementation yet
    fun modify() {}
}
