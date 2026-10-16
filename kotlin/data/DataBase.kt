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

import org.tfyh.api.ApiHandler
import org.tfyh.api.ListConditionsIF
import org.tfyh.api.Transaction
import org.tfyh.control.EventHandlerIF
import org.tfyh.control.Logger
import org.tfyh.control.LoggerSeverity
import org.tfyh.data.Table.Selector
import kotlin.math.max

@Suppress("Unused")
class DataBase private constructor() {

    companion object {
        private val instance = DataBase()
        internal const val CACHE_PATH = "Data"
        fun getInstance() = instance
    }

    // the list of tables: name => table
    private val tables: MutableMap<String, Table> = mutableMapOf()
    private val logger : Logger = Logger("database")
    private var _tableOfFindBy: Table = Table(Config.getInstance().invalidItem)

    private var eventListener : EventHandlerIF? = null
    private var listConditionsIF : ListConditionsIF? = null
    private var localCache : LocalCacheIF? = null
    fun tableOfFindBy() = _tableOfFindBy.record.item.name()

    fun setEventListener(eventListener : EventHandlerIF) { this.eventListener = eventListener }
    fun setListConditionsIF(listConditionsIF : ListConditionsIF) { this.listConditionsIF = listConditionsIF }
    fun setLocalCache(localCache : LocalCacheIF) { this.localCache = localCache }

    /**
     * Reload all tables from the local cache. This will delete all existing data. If a table is
     * not available in the local cache, a transaction for download is appended to the API queue.
     */
    fun load(localCacheOnly : Boolean = false) {
        val recordItems = Config.getInstance().getItem(".tables").getChildren()
        Indices.getInstance().clearAll()
        for (recordItem in recordItems) {
            // read the data from local cache first
            val modified = localCache?.getItem("$CACHE_PATH/${recordItem.name()}.modified") ?: "0.0"
            val csv = localCache?.getItem("$CACHE_PATH/${recordItem.name()}.csv") ?: ""
            val table = getTableOrNull(recordItem.name())
            if (csv.isNotEmpty()) {
                if (table != null) {
                    val changed = mergeTable(recordItem.name(), csv)
                    if (changed) {
                        val event = EventHandlerIF.Event(EventHandlerIF.EventType.DATA_EVENT,
                            "merge", "", recordItem)
                        eventListener?.handleEvent(event)
                    }
                }
            }
            else if (!localCacheOnly) {
                // download any further changes. Calculate the period to download.
                val lastModified = try { modified.toDouble() } catch (_: Exception) { 0.0 }
                val microTime = NoZoneDateTime.now().microtime()
                val ageInSeconds = microTime - lastModified
                val modifiedAfter = max(microTime - (ageInSeconds * 1.5), 0.0)
                // create the update request if applicable
                val gracePeriod = 120.0
                if (ageInSeconds > gracePeriod)
                    updateTable(recordItem.name(), modifiedAfter)
            }
        }
    }

    /**
     * Create a "LIST" transaction for the tableName and records modified after modifiedAfter as
     * seconds after the epoch. Add a conditions map for further filtering.
     */
    fun updateTable(tableName: String, modifiedAfter: Double) {
        val apiHandler = ApiHandler.getInstance()
        val furtherConditions = listConditionsIF?.getFurtherConditions(tableName) ?: mapOf()
        apiHandler.addNewTxToPending(Transaction.TxType.LIST, tableName,
                mapOf("set" to "client", "modified_after" to modifiedAfter.toString())
                        + furtherConditions)
        logger.log(LoggerSeverity.INFO, "Database.updateTable()", "requested update for $tableName")
    }

    /**
     * Insert a row. row must have a field "uid" with a value that is not yet used.
     * The table row will have exactly the same values as row, no defaults are added or removed.
     */
    fun insert(tableName: String, row: MutableMap<String, String>) {
        val table = getTableOrNull(tableName) ?: return
        val changed = table.insert(row)
        if (changed)  {
            val uid = row["uid"] ?: ""
            val event = EventHandlerIF.Event(EventHandlerIF.EventType.DATA_EVENT,
                "insert", uid, table.record.item)
            eventListener?.handleEvent(event)
        }
    }

    /**
     * Update a row. row must have a field "uid" with a value that is used.
     * The row-values will overwrite the current values of the table row, extra fields
     * of the table row will be kept.
     */
    fun update(tableName: String, row: MutableMap<String, String>) {
        val table = getTableOrNull(tableName) ?: return
        val changed = table.update(row)
        if (changed)  {
            val uid = row["uid"] ?: ""
            val event = EventHandlerIF.Event(EventHandlerIF.EventType.DATA_EVENT,
                "update", uid, table.record.item)
            eventListener?.handleEvent(event)
        }
    }

    /**
     * Update a row. row must have a field "uid" with a value that is used.
     */
    fun delete(tableName: String, uid: String) {
        val table = getTableOrNull(tableName) ?: return
        val changed = table.delete(uid)
        if (changed)  {
            val event = EventHandlerIF.Event(EventHandlerIF.EventType.DATA_EVENT,
                "delete", uid, table.record.item)
            eventListener?.handleEvent(event)
        }
    }

    /**
     * Update a row. row must have a field "uid" with a value that is used.
     */
    fun merge(tableName: String, row: MutableMap<String, String>) {
        val table = getTableOrNull(tableName) ?: return
        val changed = table.merge(row)
        if (changed)  {
            val uid = row["uid"] ?: ""
            val event = EventHandlerIF.Event(EventHandlerIF.EventType.DATA_EVENT,
                "merge", uid, table.record.item)
            eventListener?.handleEvent(event)
        }
    }

    /**
     * The value stored in the "modified" field indicates that the record was read from
     * the server.
     */
    private fun isServerStored(row: MutableMap<String,String>): Boolean {
        return (row["modified"]?.isNotEmpty() ?: false) // there is an entry
                && ((row["modified"]?.toDouble() ?: 0.0) > 946684800) // and it is after the 1.1.2000
    }

    /**
     * The value stored in the "modified" field indicates that the record was read from
     * the server.
     */
    fun isServerStored(tableName: String, uid: String): Boolean {
        val row = tables[tableName]?.selectByUid(uid)
        return ((row != null) && isServerStored(row))
    }

    /**
     * Return the existing table with the tableName. If it does not yet exist,
     * but a recordItem is within the configuration, create it. If no child with the tableName
     * exists for the ".tables" configuration item, return null.
     */
    fun getTableOrNull(tableName: String): Table? {
        val recordItem = Config.getInstance().getItem(".tables.$tableName")
        if (!recordItem.isValid()) {
            return null
        }
        val table = tables[tableName] ?: Table(recordItem)
        if (tables[tableName] == null)
            tables[tableName] = table
        return table
    }

    fun notifyChange(uid: String, modification: String, table: Table) {
        logger.log(LoggerSeverity.INFO, "DataBase.notifyChange()",
            modification + " in " + table.record.item.name())
        val event = EventHandlerIF.Event(EventHandlerIF.EventType.DATA_EVENT,
            modification, uid, table.record.item)
        eventListener?.handleEvent(event)
    }

    /**
     * Read the csv-table and update or insert ist rows in the table depending on whether
     * a row with the respective uid exists or not. If the table of name tableName does not
     * yet exist and tableName is a valid table name, this will import the table.
     */
    fun mergeTable(tableName: String, csv: String) : Boolean {
        val table = getTableOrNull(tableName) ?: return false
        val changed = table.mergeTable(csv)
        if (changed)  {
            val event = EventHandlerIF.Event(
                EventHandlerIF.EventType.DATA_EVENT, "merge", "", table.record.item)
            eventListener?.handleEvent(event)
        }
        return changed
    }

    /**
     * Return a clone of the tableRow. Will be an empty map, if there is no table row
     * with the given uid or the uid is null.
     */
    internal fun findByUuid(uuid: String?): MutableMap<String, String> {
        _tableOfFindBy = tables[Indices.getInstance().getTableForUuid(uuid ?: "?uuid?")] ?: return mutableMapOf()
        return _tableOfFindBy.selectByUuid(uuid)
    }

    /**
     * Return a clone of the tableRow. Will be an empty map, if there is no table row
     * with the given uid or the uid is null.
     */
    fun findByUid(uid: String?): MutableMap<String, String> {
        _tableOfFindBy = tables[Indices.getInstance().getTableForUid(uid ?: "?uid?")] ?: return mutableMapOf()
        return _tableOfFindBy.selectByUid(uid)
    }

    /**
     * Return a clone of the tableRow. Will be an empty map, if there is no table row
     * with the given name.
     */
    fun selectByName(tableName: String, name: String): MutableMap<String, String> {
        val table = tables[tableName] ?: return mutableMapOf()
        return table.selectByName(name)
    }

    /**
     * Return a clone of the tableRow. Will be an empty map, if there is no table row
     * with the given name.
     */
    fun selectByMatch(tableName: String, row: MutableMap<String, String>): List<MutableMap<String, String>> {
        val table = tables[tableName] ?: return mutableListOf()
        return table.select(table.buildSelector(row))
    }

    /**
     * Select a set of tableRows applying the selector and sorting
     */
    fun select(tableName: String, where: Selector? = null, maxRows: Int = 0, sorting: String = ""):
            List<MutableMap<String, String>> {
        val table = tables[tableName] ?: return mutableListOf()
        val rowsList = table.select(where, maxRows, sorting)
        return if (sorting.isEmpty())
                    rowsList
                else
                    Table.sortRows(sorting, rowsList, table.record.item)
    }

    /**
     * store all changed tables as csv
     */
    fun store() {
        for (tableName in tables.keys)
            tables[tableName]?.store(localCache)
    }

    /**
     * Create a backup which is the sequence of all tables as csv headed each by "Table::<tableName>".
     */
    fun createBackup(): String {
        var backup = ""
        for (tableName in tables.keys)
            backup += "Table::" + tableName + "\n" + (tables[tableName]?.toCsv() ?: "") + "\n\n"
        return backup
    }
}