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

import org.tfyh.util.Language
import kotlin.math.floor


/**
 * A table holds all rows and a name index. Each row is a Map of <fieldName, fieldValue>,
 * values are csv-encoded Strings.
 */
class Table internal constructor(private val recordItem: Item) {

    enum class Comparison {
        EQUAL, NOT_EQUAL, LOWER_THAN, GREATER_THAN, LOWER_OR_EQUAL, GREATER_OR_EQUAL, CONTAINS, IS_EMPTY, NONE
    }

    // Selector usage example
    val example: Selector = Selector(
        childrenAnd = true, children = mutableListOf(
            Selector(
                // A == 1 and
                fieldName = "A", compare = Comparison.EQUAL, filter = "1"
            ),
            Selector(
                // B < 100 or C >= 70
                childrenAnd = false, children = mutableListOf(
                    Selector(
                        fieldName = "B", compare = Comparison.LOWER_THAN, filter = "100"
                    ),
                    Selector(
                        fieldName = "C", compare = Comparison.GREATER_OR_EQUAL, filter = "70"
                    )
                )
            )
        )
    )

    /**
     * a hierarchical selection filter, see val example fur usage.
     */
    data class Selector(
        var fieldName: String = "",
        var compare: Comparison = Comparison.NONE,
        var filter: Any = "",
        val children: MutableList<Selector> = mutableListOf(),
        var childrenAnd: Boolean = true
    ) {
        fun isSelected(record: Record): Boolean {
            val value = record.value(fieldName)
            val parser = record.item.getChild(fieldName)?.type()?.parser() ?: ParserName.NONE
            val filterValue = Parser.parse(filter, parser, Language.CSV)
            var selected: Boolean
            var i = 0
            if (children.isNotEmpty()) {
                selected = children[i].isSelected(record)
                while (i < children.size - 1) {
                    i++
                    selected =
                        if (childrenAnd)
                            selected && children[i].isSelected(record)
                        else
                            selected || children[i].isSelected(record)
                }
            }
            else selected = when (compare) {
                Comparison.EQUAL -> Validator.equals(value, filterValue, parser)
                Comparison.NOT_EQUAL -> !Validator.equals(value, filterValue, parser)
                Comparison.LOWER_THAN -> Validator.lt(value, filterValue, parser)
                Comparison.GREATER_OR_EQUAL -> ! Validator.lt(value, filterValue, parser)
                Comparison.GREATER_THAN -> ! Validator.equals(value, filterValue, parser) && ! Validator.lt(value, filterValue, parser)
                Comparison.LOWER_OR_EQUAL -> Validator.equals(value, filterValue, parser) || Validator.lt(value, filterValue, parser)
                Comparison.CONTAINS -> Validator.contains(value, filterValue, parser)
                Comparison.IS_EMPTY -> ParserConstraints.isEmpty(value, parser)
                Comparison.NONE -> true
            }
            return selected
        }
    }

    /**
     * Build a selector to filter rows with matching filterRow values. If filterRow contains fields,
     * that do not belong to this table, they are ignored.
     */
    fun buildSelector(filterRow: MutableMap<String, String>): Selector {
        val conditions: MutableList<Selector> = mutableListOf()
        for (fieldName in filterRow.keys) {
            if (recordItem.hasChild(fieldName)) {
                val field = recordItem.getChild(fieldName)
                if (field != null) {
                    val filterValue = Parser.parse(filterRow[fieldName] ?: "", field.type().parser(), Language.CSV)
                    conditions.add(Selector(fieldName, Comparison.EQUAL, filterValue))
                }
            }
        }
        return Selector(children = conditions, childrenAnd = true)
    }

    companion object {

        /**
         * Replacement for the kotlinx-IO function, only working within the JavaScript- and kotlin-implementation
         */
        private fun getTimeMillis() : Long = try {
            (NoZoneDateTime.now().microtime() * 1000.0).toLong()
        } catch (_: Exception) {
            ParserConstraints.empty(ParserName.LONG) as Long
        }

        /**
         * Return a sorting rule. Note that descending sort is not possible for Strings
         */
        private fun comparable(sortName: String, row: MutableMap<String, String>,
                               item: Item): Comparable<*> {
            val upDown = if (sortName.startsWith("-")) -1 else 1
            val fieldName = if (sortName.startsWith("-")) sortName.substring(1) else sortName
            val field = item.getChild(fieldName) ?: return 0
            val parser = field.type().parser()
            val value = Parser.parse(row[fieldName] ?: "", parser, Language.CSV)
            return when (val comparable = Validator.comparable(value, parser)) {
                is Int -> comparable * upDown
                is Long -> comparable * upDown
                is Double -> comparable * upDown
                else -> comparable
            }
        }

        /**
         * sort the list by a sort string like id.-invalid_from or last_name.first_name. Note that
         * descending sort does not work with strings and sorting of lists will be according to
         * the list size only.
         */
        fun sortRows(sorting: String, rowsList: List<MutableMap<String, String>>,
                     item: Item): List<MutableMap<String, String>> {
            val sorts = sorting.split(".")
            return when (sorts.size) {
                1 -> rowsList.sortedWith(compareBy { comparable(sorts[0], it, item) })
                2 -> rowsList.sortedWith(compareBy({ comparable(sorts[0], it, item) },
                    { comparable(sorts[1], it, item) }))
                3 -> rowsList.sortedWith(compareBy({ comparable(sorts[0], it, item) },
                    { comparable(sorts[1], it, item) }, { comparable(sorts[2], it, item) }))
                else -> rowsList.toList()
            }
        }
    }

    private val rows: MutableMap<String, MutableMap<String, String>> = mutableMapOf()
    internal val record: Record = Record(recordItem)
    private val nameIndex: MutableMap<String, String> = mutableMapOf()
    private var lastChanged: Double = 0.0
    private var lastStored: Double = 0.0

    /**
     * Update the uuid index. it always points to the most recent version of an object for
     * versioned tables, that's why its complex.
     */
    private fun updateUuidIndex(row: MutableMap<String, String>,
                                uuidIndex: MutableMap<String, String>, isDelete: Boolean = false) {
        val uid = row["uid"] ?: return
        val uuid = row["uuid"] ?: return
        val invalidFrom = row["invalid_from"] ?: ""

        // this is a new uuid
        if (uuidIndex[uuid] == null) {
            if (!isDelete) uuidIndex[uuid] = uid
            // trying to delete a not existing row will not require any action at all
            return
        }

        // this is the valid version
        var currentVersionWasDeleted = false
        if (invalidFrom.isEmpty()) {
            if (!isDelete) {
                uuidIndex[uuid] = uid
                return
            }
            else {
                uuidIndex.remove(uuid)
                currentVersionWasDeleted = true
            }
        }

        // This is not a valid version, and a valid version exists
        val previousInvalidFrom = rows[uid]?.get("invalid_from") ?: ""
        if ((rows[uid] != null) && previousInvalidFrom.isEmpty()) {
            if (isDelete) uuidIndex.remove(uuid)
            // no index update required on removal of a non-current version
            // no index update required on adding a non-current version
            return
        }

        // Compare the previously index version with this one
        val invalidFromDate = Parser.parse(invalidFrom, ParserName. DATE, Language.CSV) as NoZoneDateTime
        val previousInvalidFromDate = Parser.parse(previousInvalidFrom, ParserName. DATE, Language.CSV) as NoZoneDateTime
        if (invalidFromDate > previousInvalidFromDate)
            // The previously registered version is less recent than the new one: replace it
            if (!isDelete) uuidIndex[uuid] = uid
            // for delete that shall not happen, because the indexed version is the most recent one
        else if (invalidFromDate == previousInvalidFromDate)
            // The previously registered version is the current version: ignore the new
            if (isDelete) {
                uuidIndex.remove(uuid)
                currentVersionWasDeleted = true
            }
        else
            // The new version is not the current version: ignore the new for add and delete
            if (isDelete) uuidIndex.remove(uuid)

        // a current version is removed. check for remaining versions of this uuid.
        if (currentVersionWasDeleted)
            for (remainingRowUid in rows.keys) {
                val remainingRow = rows[remainingRowUid]
                if (remainingRow != null)  {
                    val remaining = remainingRow["uuid"]
                    if (remaining == uuid)
                        updateUuidIndex(remainingRow, uuidIndex)
                }
            }
    }

    /**
     * merge a single row into the table, i.e. update if existing or insert if missing.
     */
    internal fun merge(row: MutableMap<String, String>) : Boolean {
        // check for the uid. It must be present
        val uid = row["uid"] ?: return false
        // get the tableRow with the respective uid
        val tableRow = rows[uid]
        return if (tableRow == null) insert(row) else update(row)
    }

    /**
     * Insert a row. row must have a field "uid" with a value that is not yet used.
     * The table row will have exactly the same values as row, no defaults are added or removed.
     * The name index is updated.
     */
    internal fun insert(row: MutableMap<String, String>) : Boolean {
        val uid = row["uid"]
        // the uid must be given and be unique
        if ((uid == null) || (rows[uid] != null)) return false
        // create the new tableRow
        rows[uid] = mutableMapOf()
        // put the values
        return update(row)
    }

    /**
     * Update a row. row must have a field "uid" with a value that is used.
     * The row-values will overwrite the current values of the table row, extra fields
     * of the table row will be kept. The name index is updated.
     */
    internal fun update(row: MutableMap<String, String>) : Boolean {
        val uid = row["uid"] ?: return false
        // get the tableRow with the respective uid
        val tableRow = rows[uid] ?: return false
        record.parse(tableRow, Language.CSV)
        // get the record's name prior to change
        val currentName = record.recordToTemplate("name")

        // update the tableRow
        var changed = false
        for (fieldName in row.keys) {
            if (record.item.hasChild(fieldName)) {
                val value = row[fieldName]
                if ((value != null) && (value.compareTo(tableRow[fieldName] ?: "") != 0)) {
                    tableRow[fieldName] = value
                    changed = true
                }
            }
        }
        if (!changed) return false

        // change post-processing
        lastChanged = floor(getTimeMillis() / 1000.0)
        // get the name after the change
        record.parse(row, Language.CSV)
        val newName = record.recordToTemplate("name")
        // update the name index
        if (newName != currentName) {
            if (nameIndex[currentName] != null)
                nameIndex.remove(currentName)
            nameIndex[newName] = uid
        }
        return true
    }

    /**
     * Update a row. row must have a field "uid" with a value that is used.
     * The name index is updated.
     */
    internal fun delete(uid: String) : Boolean {
        // get the tableRow with the respective uid
        val tableRow = rows[uid] ?: return false
        // delete the tableRow
        rows.remove(uid)

        // change post-processing
        // get the current name
        record.parse(tableRow, Language.CSV)
        val deletedName = record.recordToTemplate("name")
        // update the name index
        if (nameIndex[deletedName] != null)
            nameIndex.remove(deletedName)
        // update the other indices
        Indices.getInstance().remove(uid, "", "", recordItem.name(), deletedName)
        lastChanged = floor(getTimeMillis() / 1000.0)
        return true
    }

     /**
     * Read the csv-table and update or insert ist rows in the table depending on whether
     * a row with the respective uid exists or not.
     * The name index is updated. Listeners are notified once.
     */
    internal fun mergeTable(csv: String) : Boolean {
        // read the rows
        val tableRows = Codec.csvToMap(csv)
        var changed = false
        // insert or update the rows one by one
        for (row in tableRows) {
            val uid = row["uid"]
            if (uid != null) {
                val tableRow = rows[uid]
                changed = when (tableRow) {
                    null -> insert(row) || changed
                    else -> update(row) || changed
                }
            }
        }
        // change post-processing
        if (changed)
            Indices.getInstance().addTable(record.item, true)
        return changed
    }

    /**
     * returns true, if the uid corresponds to the uid of the most recent record with the given uuid
     */
    @Suppress("Unused")
    fun isCurrentVersion(uid: String, uuid: String): Boolean {
        if (rows.isNotEmpty() && (rows[rows.keys.first()]?.get("invalid_from") == null))
            return true    // non versioned table. The record is always the current one
        val row = selectByUuid(uuid)  // this returns the most recent one
        return (row["uid"] ?: "?uid?") == uid
    }

    /**
     * Return a clone of the tableRow. Will be an empty map, if there is no table row
     * with the given uid or the uid is null.
     */
    fun selectByUid(uid: String?): MutableMap<String, String> {
        val clone = mutableMapOf<String, String>()
        val row = rows[uid] ?: return clone
        for (fieldName in row.keys) {
            val value = row[fieldName]
            if (value != null)
                clone[fieldName] = value
        }
        return clone
    }

    /**
     * Return a clone of the tableRow. Will be an empty map, if there is no table row
     * with the given uid or the uid is null.
     */
    fun selectByUuid(uuid: String?): MutableMap<String, String> {
        val empty = mutableMapOf<String, String>()
        if (uuid == null)
            return empty
        val selector = Selector("uuid", Comparison.EQUAL, uuid)
        val selected = select(selector)
        if (selected.isEmpty())
            return empty
        val sorted = sortRows("invalid_from", selected, record.item)
        return if (sorted.first()["invalid_from"] == "") sorted.first()
               else sorted.last()
    }

    /**
     * Return a clone of the tableRow. Will be an empty map, if there is no table row
     * with the given name.
     */
    fun selectByName(name: String): MutableMap<String, String> {
        return selectByUid(nameIndex[name])
    }

    /**
     * Select a set of tableRows based on the selector and sorting.
     */
    fun select(where: Selector?, maxRows: Int = 0, sorting: String = ""): List<MutableMap<String, String>> {
        val selected: MutableList<MutableMap<String, String>> = mutableListOf()
        val uids = rows.keys.toList() // avoid a co-modification exception when loading the very first time
        for (uid in uids) {
            val row = rows[uid]
            if (row != null) {
                record.parse(row.toMap(), Language.CSV)
                if (where?.isSelected(record) != false) {
                    if ((maxRows <= 0) || (selected.size < maxRows) || sorting.isNotEmpty())
                        selected.add(row)
                }
            }
        }
        if (sorting.isNotEmpty()) {
            val sorted = sortRows(sorting, selected, recordItem)
            val limited: MutableList<MutableMap<String, String>> = mutableListOf()
            for (i in 0..< intArrayOf(maxRows, sorted.size).min())
                limited.add(sorted[i])
            return limited.toList()
        } else
            return selected.toList()
    }

    /**
     * Get a csv-representation of the table, providing all available columns.
     */
    fun toCsv(): String {
        val rowsList = mutableListOf<MutableMap<String, String>>()
        val allKeys: MutableList<String> = mutableListOf()
        // Make sure all columns are filled
        for (uid in rows.keys) {
            val row = rows[uid]
            if (row != null) {
                for (key in row.keys)
                    if (allKeys.indexOf(key) < 0)
                        allKeys.add(key)
                rowsList.add(row)
            }
        }
        // set all available columns for the first row
        // because multiple lists can provide data for a table, the row size may differ
        if (rowsList.isNotEmpty()) {
            val firstRow = rowsList.getOrNull(0)
            if (firstRow != null)
                for (key in allKeys)
                    if (firstRow[key] == null)
                        firstRow[key] = ""
        }
        return Codec.encodeCsvTable(rowsList)
    }

    /**
     * store the table as csv, if changed
     */
    fun store(localCache: LocalCacheIF?) {
        if (localCache == null) return
        if ((lastChanged > lastStored) || (lastStored == 0.0)) {
            localCache.setItem("${DataBase.CACHE_PATH}/${record.item.name()}.csv", toCsv())
            lastStored = floor(getTimeMillis() / 1000.0)
        }
    }

    override fun toString(): String {
        return record.item.name() +  "[" + rows.size + "]"
    }
}