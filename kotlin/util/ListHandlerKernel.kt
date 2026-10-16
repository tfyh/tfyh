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

package org.tfyh.util

import org.tfyh.control.LoggerSeverity
import org.tfyh.data.Codec
import org.tfyh.data.Config
import org.tfyh.data.DataBase
import org.tfyh.data.Item
import org.tfyh.data.Record
import org.tfyh.data.Table
import org.tfyh.data.Table.Comparison

@Suppress("Unused")
class ListHandlerKernel(
    /**
     * the list set chosen (lists config file name)
     */
    private val set: String, nameOrDefinition: String = "", args: MutableMap<String, String> = mutableMapOf()) {
    /**
     * Definition of all lists in configuration file. Will be read once upon construction from $file_path.
     */
    private val listDefinitions: List<MutableMap<String, String>>
    /**
     * One list definition is the current. The index points to it and the private variables are shorthands to it
     */
    private var currentListIndex: Int = -1
    private val name: String
    private val tableName: String
    private val columns: MutableList<String> = mutableListOf()
    private val recordItem: Item
    private val record: Record

    private var label = ""
    private var description = ""
    /**
     * the list set chosen (lists file name)
     */
    private val listSetPermissions: MutableList<String> = mutableListOf()

    /**
     * the list of sort options using the format [-]column[.[-]column]
     */
    private var oSortsList = ""
    /**
     * the column of the filter option for this list
     */
    private var oFilter = ""
    /**
     * the value of the filter option for this list
     */
    private var oFValue = ""

    /**
     * the maximum number of rows in the list
     */
    private var maxRows = 100
    /**
     * filter for duplicates, only return the first of multiple, table must be sorted for that column
     */
    private var firstOfBlock = ""
    private val i18n = I18n.getInstance()

    /**
     * Build a list set based on the definition provided in the csv file at "../Config/lists/$set". Use the list with
     * name $nameOrDefinition as current list name or none, if $name = "", or put your full set definition to
     * $nameOrDefinition and "@dynamic" to $set to generate a list programmatically. Use the count() function to see
     * whether list definitions could be parsed.
     */
    init {
        if (set == "@dynamic") {
            listDefinitions = Codec.csvToMap(nameOrDefinition)
            name = listDefinitions.getOrNull(0)?.get("name") ?: ""
        } else {
            listDefinitions = this.readSet(set)
            name = nameOrDefinition
        }
        // if definitions could be found, parse all and get own.
        for (i in listDefinitions.indices) {
            val listDefinition = listDefinitions.getOrNull(i) ?: mutableMapOf()
            // join permissions for the entire set
            if (!listSetPermissions.contains(listDefinition["permission"]))
                listSetPermissions += listDefinition["permission"] + ","
            // replace arguments only for the current list
            if (listDefinition["name"] == name) {
                currentListIndex = i
                label = listDefinition["label"] ?: name
                description = listDefinition["description"] ?: ""
                for (key in listDefinition.keys)
                    for (template in args.keys) {
                        val used = args[key] ?: ""
                        // list arguments are values which may be user defined to avoid SQL infection ";"
                        // is not allowed in these
                        val usedSecure = if (used.contains(";")) i18n.t("KtXJLq|{invalid parameter with ...") else used
                        // replace the template String by the value to use
                        listDefinition[key] = listDefinition[key]?.replace(template, usedSecure) ?: ""
                    }
            }
        }

        // Parse the current list's definition
        val logger = Config.getInstance().logger
        val currentListDefinition = listDefinitions.getOrNull(currentListIndex) ?: mutableMapOf()
        val config = Config.getInstance()
        if (currentListDefinition.isNotEmpty()) {
            tableName = currentListDefinition["table"] ?: ""
            recordItem = config.getItem(".tables.$tableName")
            if (! recordItem.isValid())
                logger.log(LoggerSeverity.ERROR, "ListHandlerKernel __construct()",
                    "List of '" + this.set + "' asks for undefined table: " + this.tableName)
            record = Record(recordItem)
            parseOptions(currentListDefinition["options"] ?: "")
            var columnsParsingErrors = ""
            columns.clear()
            val selection = (currentListDefinition["select"] ?: "").split(",")
            for (column in selection)
                if (recordItem.hasChild(column))
                    columns.add(column)
                else
                    columnsParsingErrors += "Invalid column name $column in list definition, "
            if (columnsParsingErrors.isNotEmpty())
                logger.log(LoggerSeverity.ERROR, "ListHandlerKernel __construct()",
            "List of '" + this.set + "' with definition errors: " + columnsParsingErrors)
        } else {
            tableName = ""
            recordItem = config.invalidItem
            record = Record(recordItem)
            logger.log(LoggerSeverity.ERROR, "ListHandlerKernel __construct()",
                "Undefined list of set '" + this.set + "' called: " + nameOrDefinition)
        }
    }

    /**
     * Parse the list set configuration
     */
    private fun readSet(set: String): List<MutableMap<String, String>> {
        val setItem = Config.getInstance().getItem(".lists.$set")
        val listDefinitions = listOf<MutableMap<String, String>>()
        for (listItem in setItem.getChildren()) {
            val listDefinition = mutableMapOf<String, String>()
            listDefinition["name"] = listItem.name()
            listDefinition["permission"] = listItem.nodeReadPermissions()
            listDefinition["label"] = listItem.label()
            listDefinition["select"] = listItem.getChild("select")?.valueStr() ?: ""
            listDefinition["table"] = listItem.getChild("table")?.valueStr() ?: ""
            listDefinition["where"] = listItem.getChild("where")?.valueStr() ?: ""
            listDefinition["options"] = listItem.getChild("options")?.valueStr() ?: ""
            listDefinitions.plus(listDefinition)
        }
        return listDefinitions
    }

    /**
     * Parse the options String containing the sort and filter options, e.g. "sort=-name&filter=doe" or
     * "sort=ID&link=id=../forms/changePlace.php?id=". Sets: oSortsList, oFilter, oFValue, firstOfBlock,
     * maxRows, recordLink, recordLinkCol
     */
    private fun parseOptions(optionsList: String) {
        val options = optionsList.split("&")
        oSortsList = ""
        oFilter = ""
        oFValue = ""
        firstOfBlock = ""
        maxRows = 0 // 0 = no limit.
        for (option in options) {
            val optionPair = option.split("=")
            when (optionPair[0]) {
                "sort" -> oSortsList = optionPair[1]
                "filter" -> oFilter = optionPair[1]
                "fvalue" -> oFValue = optionPair[1]
                "firstofblock" -> firstOfBlock = optionPair[1]
                "maxrows" -> maxRows = optionPair[1].toInt()
            }
        }
    }

    /**
     * Get the entire list definition array of the current list, arguments are replaced. If there is no current list,
     * return an empty array
     */
    private fun listDefinition(): MutableMap<String, String> {
        return listDefinitions.getOrNull(currentListIndex) ?: mutableMapOf()
    }

    /**
     * Get the count of list definitions
     */
    fun count() = listDefinitions.size

    private fun noValidCurrentList() = ((currentListIndex < 0) || ((listDefinitions.getOrNull(currentListIndex)?.size ?: 0) <= 1))
    fun getName() = name
    fun getLabel() = label
    fun getDescription() = description
    fun getSetPermission() = listSetPermissions
    fun getPermission() = listDefinition()["permission"]
    fun getAllListDefinitions() = listDefinitions

    /**
     * Build the database request, i.e. an SQL-statement for the implementation and a filter and sorting for the
     * JavaScript and kotlin implementations.
     */
    private fun buildDatabaseRequest(oFilter: String, oFValue: String): Table.Selector
    {
        return Table.Selector(
            fieldName = oFilter, compare = Comparison.EQUAL, filter = oFValue
        )
    }

    /**
     * Provide a list with all data retrieved. The list contains rows of name to value pairs, all Strings, as
     * provided by the database
     */
    private fun getRowsSql(oSortsList: String = "", oFilter: String = "", oFValue: String = "",
            maxRows: Int = -1): MutableList<MutableMap<String, String>>
    {
        val rowsSql: MutableList<MutableMap<String, String>> = mutableListOf()
        if (noValidCurrentList())
            return rowsSql // Due to the fact, that kotlin doesn't allow for more
                           // than one return type, no error indication will be returned at all.

        // normal operation
        val osl = oSortsList.ifEmpty { this.oSortsList }
        val of = oFilter.ifEmpty { this.oFilter }
        val ofv = oFValue.ifEmpty { this.oFValue }
        val mxr = if (maxRows == -1) this.maxRows else maxRows

        // assemble the selector-statement and read data
        val selector = buildDatabaseRequest(of, ofv)
        val dbc = DataBase.getInstance()
        val rows = dbc.select(tableName, selector, mxr, osl)

        // check the firstOfBlock pivoting filter
        var firstOfBlockFilter = false
        for (i in columns.indices)
            if (firstOfBlock.equals(columns[i], ignoreCase = true))
                firstOfBlockFilter = true
        // filter pivoted rows
        var lastFirstValue: String? = null
        for (row in rows) {
            val filtered = (firstOfBlockFilter && (lastFirstValue != null) &&
                    ((row[firstOfBlock] ?: "") == lastFirstValue))
            if (!filtered) {
                rowsSql.add(row)
                if (firstOfBlockFilter)
                    lastFirstValue = row[firstOfBlock]
            }
        }

        // TODO permissions check. Use $this->record

        return rowsSql
    }

    /**
     * get an array of rows as native values.
     */
    fun getRowsNative(oSortsList: String = "", oFilter: String = "", oFValue: String = "",
        maxRows: Int = -1): MutableList<Map<String, Any>>
    {
        val rowsSql = getRowsSql(oSortsList, oFilter, oFValue, maxRows)
        if (rowsSql.isEmpty())
            return mutableListOf()
        val processedRows = mutableListOf<Map<String, Any>>()
        for (rowSql in rowsSql) {
            record.parse(rowSql, Language.SQL)
            processedRows.add(record.values())
        }
        return processedRows
    }

    /**
     * get an array of rows according to the format: "csv" = csv-formatted, e.g. for the api, "localised" = local
     * language formatted values, "referenced" = local language formatted values with references resolved.
     */
    fun getRows(format: String, oSortsList: String = "", oFilter: String = "", oFValue: String = "",
                maxRows: Int = -1): MutableList<Map<String, String>> {
        val config = Config.getInstance()
        val rowsSql = getRowsSql(oSortsList, oFilter, oFValue, maxRows)
        if (rowsSql.isEmpty())
            return mutableListOf()
        val processedRows = mutableListOf<Map<String, String>>()
        for (rowSql in rowsSql) {
            record.parse(rowSql, Language.SQL)
            when (format) {
                "csv" -> processedRows.add(record.format(Language.CSV, true, this.columns))
                "localized" -> processedRows.add(record.format(config.language(), true, this.columns))
                "referenced" -> processedRows.add(record.formatToDisplay(config.language(), true, this.columns))
            }
        }
        return processedRows
    }

}
