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

import org.tfyh.util.I18n
import org.tfyh.util.User

/**
 * A utility class to provide all table indices.
 */
@Suppress("Unused")
class Indices private constructor() {

    companion object {
        private val instance = Indices()
        fun getInstance() = instance
    }

    private val short2longUuid: MutableMap<String, String> = mutableMapOf()
    private val uid2table: MutableMap<String, String> = mutableMapOf()

    private val uuid2table: MutableMap<String, String> = mutableMapOf()

    private val uuid2name: MutableMap<String, String> = mutableMapOf()
    private val userId2name: MutableMap<Int, String> = mutableMapOf()
    private val name2uuid: MutableMap<String, MutableMap<String, List<String>>> = mutableMapOf()

    var missingNotice = "not found"
    private var userIdFieldName = "userId"
    private val loaded: MutableMap<String, Boolean> = mutableMapOf()


    init {
        clearAll()
        missingNotice = "[" + I18n.getInstance().t("DcBmjj|not found") + "]"
        userIdFieldName = Config.getInstance().getItem(".framework.users.user_id_field_name").valueStr()
    }

    internal fun clearAll() {
        uid2table.clear()
        short2longUuid.clear()
        uuid2table.clear()
        uuid2name.clear()
        userId2name.clear()
        name2uuid.clear()
        loaded.clear()
    }

    /**
     * Add a single entry to the indices. Provides a warning on duplicates for uid and short UUID,
     * i.e. the first 11 characters of a UUID.
     */
    fun add(uid: String, uuid: String, userId: String,
                    tableName: String, name: String): String {
        var warnings = ""
        if (this.name2uuid[tableName] == null)
                this.name2uuid[tableName] = mutableMapOf()
        if (uid.isNotEmpty()) {
            if (uid2table[uid] == null)
                uid2table[uid] = tableName
            else {
                val warning = "Duplicate uid $uid in both " + uid2table[uid] + " and $tableName. "
                // Runner.getInstance().logger.log(LoggerSeverity::ERROR, "Indices.add()", $warning);
                warnings += warning
            }
        }
        if (uuid.isNotEmpty()) {
            // check whether this is the current version of the object. If not, ignore.
            val shortUuid = uuid.substring(0, 11)
            uuid2table[shortUuid] = tableName
            uuid2name[shortUuid] = name
            val name2uuidForTable = this.name2uuid[tableName]
            if (name2uuidForTable != null) {
                val uuidsForName = (name2uuidForTable[name] ?: mutableListOf()).toMutableList()
                if (uuidsForName.indexOf(uuid) < 0)
                    uuidsForName += uuid
                if (name2uuidForTable[name] == null)
                    name2uuidForTable[name] = uuidsForName
            }
            if (short2longUuid[shortUuid] == null)
                short2longUuid[shortUuid] = uuid
            else if (short2longUuid[shortUuid] != uuid) {
                val warning = "Duplicate short UUID $shortUuid (the 11 UUID start characters) in both " +
                    uid2table[uid] + " and $tableName. "
                // Runner::getInstance().logger.log(LoggerSeverity::ERROR, "Indices.add()", $warning);
                warnings += warning
            }
        }

        try {
            val userIdInt = userId.toInt()
            if (userIdInt > 0)
                userId2name[userIdInt] = name
        } catch (_: Exception) {}
        return warnings
    }

    /**
     * remove a single entry from the indices.
     */
    fun remove(uid: String, uuid: String, userId: String, tableName: String, name: String) {
        if (this.name2uuid[tableName] == null)
            this.name2uuid[tableName] = mutableMapOf()
        if (uid.isNotEmpty() && (uid2table[uid] != null))
                uid2table.remove(uid)
        if (uuid.isNotEmpty()) {
            val shortUuid = uuid.substring(0, 11)
            uuid2table.remove(shortUuid)
            uuid2name.remove(shortUuid)
            val name2uuidForTable = this.name2uuid[tableName]
            if (name2uuidForTable != null) {
                val nameEntry = name2uuidForTable[name]
                if (nameEntry != null) {
                    nameEntry.toMutableList().remove(uuid)
                    if (nameEntry.isEmpty())
                        name2uuidForTable.remove(uuid)
                }
            }
            // short2longUuid will keep the match, may be other versions of the uuid record exist still
        }

        try {
            val userIdInt = userId.toInt()
            if (userIdInt > 0)
                userId2name.remove(userIdInt)
        } catch (_: Exception) {}
    }

    /**
     * Create the definition for a dynamic list which retrieves the information needed for the name index using the
     * tables "name" template. NOT USED IN CLIENT IMPLEMENTATIONS.
     */
    private fun createListDefinition() = ""

    /**
     * Add all records of a table to the indices. Provides warnings on duplicates for uid and short UUID, i.e. the
     * first 11 characters of a UUID. This will be skipped, if the table was already loaded, but may then
     * be enforced by setting forced to true
     */
    internal fun addTable(recordItem: Item, forced: Boolean = false): String {
        val tableName = recordItem.name()
        if ((loaded[tableName] == true) && !forced)
            return ""
        val userRole = User.getInstance().role()
        if (userRole == Config.getInstance().getItem(".framework.users.anonymous_role").valueStr())
            return "No index built for anonymous user."
        val record = Record(recordItem)
        val db = DataBase.getInstance()
        val rows = db.select(tableName)
        var warnings = ""
        if (this.name2uuid[tableName] == null)
            this.name2uuid[tableName] = mutableMapOf()
        for (row in rows) {
            val name = record.rowToTemplate("name", row)
            val uid = row["uid"] ?: ""
            val uuid = row["uuid"] ?: ""
            val userId = row[userIdFieldName] ?: ""
            warnings += add(uid, uuid, userId, tableName, name)
        }
        loaded[tableName] = true
        return warnings
    }

    /**
     * Add all records of all tables to the indices. Provides warnings on duplicates for uid and short UUID, i.e. the
     * first 11 characters of a UUID.
     */
    private fun addAll(): String {
        if (loaded["@all"] == true)
            return ""
        var warnings = ""
        val tablesItem = Config.getInstance().getItem(".tables")
        for (recordItem in tablesItem.children) {
            if (loaded[recordItem.name()] != true)
                warnings += addTable(recordItem)
        }
        loaded["@all"] = true
        return warnings
    }

    /**
     * Set the index of names to uuids for a specific table. Access the result in this.name2uuid[tableName].
     * The Form uses this public access to the #addTable private function
     */
    fun buildIndexOfNames(tableName: String) {
        if (this.name2uuid[tableName] == null)
            this.addTable(Config.getInstance().getItem(".tables.$tableName"))
    }

    /**
     * get the records name (i.e. the filled "name" template) for an uuid. returns a missing notice, if not resolved.
     * Restrict the search to a single table by setting the $tableName.
     */
    fun getNameForUuid(uuidOrShortUuid: String, tableName: String = "@all"): String {
        if (uuidOrShortUuid.length < 11)
            return uuidOrShortUuid
        val shortUuid = uuidOrShortUuid.substring(0, 11)
        if (tableName != "@all") {
            val matchedTableName = getTableForUuid(shortUuid, tableName)
            return if (matchedTableName != tableName)
                missingNotice
            else
                uuid2name[shortUuid] ?: missingNotice
        } else
            return uuid2name[shortUuid] ?: missingNotice
    }

    /**
     * Get the name of the user with the respective user id
     */
    fun getUserName(userId: Int): String {
        val userTableName = Config.getInstance().getItem(".framework.users.user_table_name").valueStr()
        if (loaded[userTableName] != true)
            addTable(Config.getInstance().getItem(".tables.$userTableName"))
        return userId2name[userId] ?: missingNotice
    }

    /**
     * Get the name of the table in which the uuid occurs. Returns a missing notice, if not resolved.
     * Restrict the search to a single table by setting the $tableName.
     */
    fun getTableForUid(uid: String, tableName: String = "@all"): String {
        if (uid.isEmpty())
            return ""
        if (tableName == "@all")
            addAll()
        else {
            val recordItem = Config.getInstance().getItem(".tables.$tableName")
            if (!recordItem.isValid())
                return ""
            addTable(recordItem)
        }
        return uid2table[uid] ?: ""
    }

    /**
     * Get the name of the table in which the uid occurs. Returns a missing notice, if not resolved.
     * Restrict the search to a single table by setting the $tableName.
     */
    fun getTableForUuid(uuidOrShortUuid: String, tableName: String = "@all"): String {
        if (uuidOrShortUuid.isEmpty())
            return ""
        val shortUuid = uuidOrShortUuid.substring(0, 11)
        if (tableName != "@all") {
            val recordItem = Config.getInstance().getItem(".tables.$tableName")
            if (!recordItem.isValid())
                return ""
            addTable(recordItem)
            return if (uuid2table[shortUuid] != tableName)
                ""
            else
                uuid2table[shortUuid] ?: ""
        }
        return uuid2table[shortUuid] ?: ""
    }

    /**
     * Get the uuid for a name within a table. If the name cannot be resolved, "" is returned. If for this name
     * exist multiple uuids, the uuid with the most recent invalidFrom parameter is used.
     */
    fun getUuid(tableName: String, nameToResolve: String): String {

        // error case
        if (nameToResolve.isEmpty())
            return ""

        // the index may not have been loaded
        if (this.name2uuid[tableName] == null)
            this.addTable(Config.getInstance().getItem(".tables.$tableName"))

        val uuids = this.name2uuid[tableName]?.get(nameToResolve) ?: emptyList()
        if (uuids.isEmpty()) return ""
        if (uuids.size == 1) return uuids.first()

        // multiple uuids for this name. get the most recent uuid used.
        // If more than one are valid, the result is a random choice between those.
        val mostRecent = mutableMapOf<String, String>()
        val db = DataBase.getInstance()
        for (uuid in uuids) {
            val rowForUuid = db.findByUuid(uuid)
            mostRecent[rowForUuid["invalid_from"] ?: ""] = uuid
        }
        val mostRecentKeys = mostRecent.keys.sorted()
        return mostRecent[""] ?: (mostRecent[mostRecentKeys.last()] ?: "")
    }

    /**
     * Get all names for a specific table as name => uuid-list
     */
    fun getNames(tableName: String) = this.name2uuid[tableName] ?: mutableMapOf()

    /**
     * Get a new uid which is checked against all existing to be really new. The randomiser ha a very
     * probability of 1 in 2.5e14 to generate two identical uids, but who knows. This is secure, but requires
     * the full indices loading.
     */
    fun getNewUid(): String {
        this.addAll()
        var uid = Ids.generateUid(6)
        while (this.uid2table[uid] != null)
            uid = Ids.generateUid(6)
        return uid
    }
}
