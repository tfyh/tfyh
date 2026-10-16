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

class ListHandlerKernel {
    /**
     * the list set chosen (lists config file name)
     */
    #set
    #nameOrDefinition = ""
    #args

    /**
     * Definition of all lists in the configuration file. Will be read once upon construction from $file_path.
     */
    #listDefinitions
    /**
     * One list definition is the current. The index points to it, and the private variables are shorthands to it
     */
    #currentListIndex= -1
    #name
    #tableName
    #columns = []
    #recordItem
    #record

    #label = ""
    #description = ""
    /**
     * the list set chosen (lists file name)
     */
    #listSetPermissions = []

    /**
     * the list of sort options using the format [-]column[.[-]column]
     */
    #oSortsList = ""
    /**
     * the column of the filter option for this list
     */
    #oFilter = ""
    /**
     * the value of the filter option for this list
     */
    #oFValue = ""

    /**
     * the maximum number of rows in the list
     */
    #maxRows = 100
    /**
     * filter for duplicates, only return the first of multiple. Table must be sorted for that column
     */
    #firstOfBlock = ""

    /**
     * Build a list set based on the definition provided in the csv file at "../Config/lists/$set". Use the list with
     * name $nameOrDefinition as the current list name or none, if $name = "", or put your full-set definition to
     * $nameOrDefinition and "@dynamic" to $set to generate a list programmatically. Use the count() function to see
     * whether list definitions could be parsed.
     */
    constructor(set, nameOrDefinition = "", args) {
        if (set === "@dynamic") {
            this.#listDefinitions = Codec.csvToMap(nameOrDefinition)
            this.#name = (this.#listDefinitions[0]) ? this.#listDefinitions[0]["name"] ?? "" : ""
        } else {
            this.#listDefinitions = this.#readSet(set)
            this.#name = nameOrDefinition
        }
        // if definitions could be found, parse all and get their own.
        for (let i in this.#listDefinitions.indices) {
            // join permissions for the entire set
            if (!this.#listSetPermissions.contains(this.#listDefinitions[i]["permission"]))
                this.#listSetPermissions.push(this.#listDefinitions[i]["permission"] + ",")
            // replace arguments only for the current list
            if (this.#listDefinitions[i]["name"] === name) {
                this.#currentListIndex = i
                this.#label = this.#listDefinitions[i]["label"] ?? this.#name
                this.#description = this.#listDefinitions[i]["description"] ?? ""
                for (let key in this.#listDefinitions[i].keys)
                    for (let template in args.keys) {
                        let used = args[key] ?? ""
                        // list arguments are values which may be user-defined to avoid SQL infection ";"
                        // is not allowed in these
                        let usedSecure = (used.contains(";")) ? i18n.t("KtXJLq|{invalid parameter with ...") : used
                        // replace the template String by the value to use
                        this.#listDefinitions[i][key] = this.#listDefinitions[i][key]?.replace(template, usedSecure) ?? ""
                    }
            }
        }

        // Parse the current list's definition
        let currentListDefinition = this.listDefinition()
        if (currentListDefinition.length > 0) {
            this.#tableName = currentListDefinition["table"] ?? ""
            this.#recordItem = config.getItem(".tables.$tableName")
            if (!this.#recordItem.isValid())
                console.log("List of '" + this.#set + "' asks for undefined table: " + this.#tableName)
            this.#record = new Record(this.#recordItem)
            this.#parseOptions(currentListDefinition["options"] ?? "")
            let columnsParsingErrors = ""
            this.#columns.clear()
            let definition = this.#listDefinitions[this.#currentListIndex]
            let selection = (definition["select"] ?? "").split(",")
            for (let column in selection)
                if (this.#recordItem.hasChild(column))
                    this.#columns.add(column)
                else
                    columnsParsingErrors += "Invalid column name $column in list definition, "
            if (columnsParsingErrors.length > 0)
                console.log("List of '" + this.#set + "' with definition errors: " + columnsParsingErrors)
        } else {
            this.#tableName = ""
            this.#recordItem = config.invalidItem
            this.#record = new Record(this.#recordItem)
            console.log("Undefined list of set '" + this.#set + "' called: " + nameOrDefinition)
        }
    }

    /**
     * Parse the list set configuration
     */
    #readSet(set) {
        let setItem = config.getItem(".lists." + set)
        let listDefinitions = []
        for (let listItem in setItem.getChildren()) {
            let listDefinition = {}
            listDefinition["name"] = listItem.name()
            listDefinition["permission"] = listItem.nodeReadPermissions()
            listDefinition["label"] = listItem.label()
            listDefinition["select"] = listItem.getChild("select")?.valueStr() ?? ""
            listDefinition["table"] = listItem.getChild("table")?.valueStr() ?? ""
            listDefinition["where"] = listItem.getChild("where")?.valueStr() ?? ""
            listDefinition["options"] = listItem.getChild("options")?.valueStr() ?? ""
            listDefinitions.push(listDefinition)
        }
        return listDefinitions
    }

    /**
     * Parse the option String containing the sort and filter options, e.g. "sort=-name&filter=doe" or
     * "sort=ID&link=id=../forms/changePlace.php?id=". Sets: oSortsList, oFilter, oFValue, firstOfBlock,
     * maxRows, recordLink, recordLinkCol
     */
    #parseOptions(optionsList) {
        let options = optionsList.split("&")
        this.#oSortsList = ""
        this.#oFilter = ""
        this.#oFValue = ""
        this.#firstOfBlock = ""
        this.#maxRows = 0 // 0 = no limit.
        for (let option in options) {
            let optionPair = option.split("=")
            switch (optionPair[0]) {
                case "sort": this.#oSortsList = optionPair[1]; break
                case "filter": this.#oFilter = optionPair[1]; break
                case "fvalue": this.#oFValue = optionPair[1]; break
                case "firstofblock": this.#firstOfBlock = optionPair[1]; break
                case "maxrows": this.#maxRows = parseInt(optionPair[1]); break
            }
        }
    }

    /**
     * Get the entire list definition array of the current list, arguments are replaced. If there is no current list,
     * return an empty array
     */
    listDefinition() {
        return this.#listDefinitions[this.#currentListIndex] ?? []
    }

    /**
     * Get the count of list definitions
     */
    count() { return this.#listDefinitions.size }

    noValidCurrentList() { return ((this.#currentListIndex < 0) || (this.#listDefinitions[this.#currentListIndex].size <= 1)) }
    getName() { return this.#name }
    getLabel() { return this.#label }
    getDescription() { return this.#description }
    getSetPermission() { return this.#listSetPermissions }
    getPermission() { return this.listDefinition()["permission"] }
    getAllListDefinitions() { return this.#listDefinitions }

    /**
     * Build the database request, i.e. an SQL-statement for the implementation and a filter and sorting for the
     * JavaScript and kotlin implementations.
     */
    #buildDatabaseRequest(oSortsList, oFilter, oFValue, maxRows)
    {
        let osl = oSortsList ?? this.#oSortsList
        let of = oSortsList ?? this.#oFilter
        let ofv = oFValue ?? this.#oFValue
        let mxr = (maxRows === -1) ? this.#maxRows : maxRows
        let limit = (mxr > 0) ? "LIMIT 0, $mxr" : ""
        // the remainder is TODO
        return ""
    }

    /**
     * Provide a list with all data retrieved. The list contains rows of name-to-value pairs, all Strings, as
     * provided by the database
     */
    #getRowsSql(oSortsList = "", oFilter = "", oFValue = "", maxRows = -1) {
        let rowsSql = []
        if (this.noValidCurrentList())
            return rowsSql // Since kotlin doesn't allow for more
                       // than one return type, no error indication will be returned at all.

        // normal operation
        let osl = oSortsList ?? this.#oSortsList
        let of = oSortsList ?? this.#oFilter
        let ofv = oFValue ?? this.#oFValue
        let mxr = (maxRows === -1) ? this.#maxRows : maxRows

        // TODO remainder of the implementation
        // TODO permissions check. Use $this->record

        return rowsSql
    }

    /**
     * get an array of rows as native values.
     */
    getRowsNative(oSortsList = "", oFilter = "", oFValue = "", maxRows = -1) {
        let rowsSql = this.#getRowsSql(oSortsList, oFilter, oFValue, maxRows)
        if (rowsSql.isEmpty())
            return []
        let processedRows = []
        for (let rowSql in rowsSql) {
            this.#record.parse(rowSql, Language.SQL)
            processedRows.add(this.#record.values())
        }
        return processedRows
}

    /**
     * get an array of rows according to the format: "csv" = csv-formatted, e.g. for the api, "localised" = local
     * language formatted values, "referenced" = local language formatted values with references resolved.
     */
    getRows(format, oSortsList = "", oFilter = "", oFValue = "", maxRows = -1) {
        let rowsSql = this.#getRowsSql(oSortsList, oFilter, oFValue, maxRows)
        if (rowsSql.length === 0)
            return []
        let processedRows = []
        for (let rowSql in rowsSql) {
            this.#record.parse(rowSql, Language.SQL)
            switch (format) {
                case "csv": processedRows.add(this.#record.format(Language.CSV, true, this.#columns)); break
                case "localized": processedRows.add(this.#record.format(config.language(), true, this.#columns)); break
                case "referenced": processedRows.add(this.#record.formatToDisplay(config.language(), true, this.#columns))
            }
        }
        return processedRows
    }

}
