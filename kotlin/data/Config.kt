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

import org.tfyh.control.Logger
import org.tfyh.control.LoggerSeverity
import org.tfyh.util.I18n
import org.tfyh.util.Language
import org.tfyh.util.ResProviderIF
import org.tfyh.util.User

/**
 * A utility class to load all application configuration.
 */
class Config private constructor() {

    companion object {

        // the path in the local cache must be identical to the settings files' path at the server side
        val allSettingsDirs = arrayOf(
            // the sequence is relevant for the loading process, do not change
            "packaged", // contains below settings files, comes packaged with the application
            "plugins", // contains per plugin a directory with the plugin name and a subset of the below settings files
            "flavours", // contains per flavour a directory with the flavour name and a subset of the below settings files
            "tenant" // contains a subset of below settings files with tenant modified and added structure
        )
        val allSettingsFiles = arrayOf(  // the sequence is relevant for the loading process, do not change
            // the sequence is relevant for the loading process, do not change
            // settings descriptor. These can change with a new framework release, but should not.
            "descriptor", // the file to define the properties which describe a value
            "types", // the file to define the available value types
            // Immutable settings. These can change with a new application release, or by a plugin.
            "access", // the settings which configure roles, menus, workflows, concessions and subscriptions
            "templates", // configuration branches which are available for multiple use in this app
            "framework", // the setting which configures the framework classes for this app
            "tables", // the database table layout
            // tenant-mutable structure. THe tenant may add or change elements to configure its implementation.
            "lists", // the settings which configure list retrievals off the database
            "app", // all settings of the application needed to run at the tenant.
            "catalogues", // the catalogues of types, like the valid boat variants asf.
            "ui", // user interface layout and other settings
            "theme" // user interface font and colour settings
        )

        private val rootItemDefinition = mapOf("_path" to "#none", "_name" to "root", "default_label" to "root", "value_type" to "none" )
        private val defaultsRootItemDefinition = mapOf("_path" to "#none", "_name" to "defaultsRoot", "default_label" to "root", "value_type" to "none" )
        /**
         * The definition of the invalid item. This is a floating item, which is not part of the configuration tree. It
         * is used to represent the "null"-type item to avoid setting any item to null.
         */
        private val invalidItemDefinition = mapOf("_path" to "#none", "default_label" to "invalid item", "value_type" to "none" )
        private val instance = Config()

        fun getInstance() = instance
        // no Kotlin implementation
        @Suppress("Unused")
        fun getModified(): String = ""

    }

    // temporary initialise the root and invalid item to ensure they are never null.
    // Will be replaced during load.
    val rootItem: Item = Item.getFloating(rootItemDefinition)
    val invalidItem: Item = Item.getFloating(invalidItemDefinition)
    var defaultsRootItem: Item = Item.getFloating(defaultsRootItemDefinition)

    private val loaded: MutableMap<String, Boolean> = mutableMapOf()
    private var ready = false
    var localFilePath = ""

    private var language: Language = Language.EN
    internal val logger = Logger("config")
    private var localCache : LocalCacheIF? = null

    var appVersion: String = "0.0"
    private var appName: String = ""
    var appUrl: String = ""

    fun setLocalCache(localCache: LocalCacheIF) { this.localCache = localCache }

    /**
     * Get the local cache via the Config singleton, because this is the first one to use it
     * and needed by all other classes
     */
    fun getLocalCache() = this.localCache
    /**
     * Get an Item by its path. Returns the invalid Handle on errors. Returns the item of the
     * default items branch, if getDefault is true.
     */
    fun getItem(path: String, getDefault: Boolean = false): Item {
        if (path.isEmpty())
            return rootItem
        if (!path.startsWith("."))
            return invalidItem
        val names = path.substring(1).split(".")
        var i = 0
        var parent: Item? = getInstance().rootItem
        while ((i < names.size) && (parent != null) && parent.hasChild(names[i]))
            parent = parent.getChild(names[i++])
        return if (parent == null) invalidItem
        else if (i == names.size) parent // path fully resolved
        else if (parent == parent.parent()) {
            // hit top level
            if (loaded[names[0]] != true) {
                loadBranch(names[0], getDefault)
                getItem(path, getDefault)
            } else
                parent
        }
        else invalidItem // path not resolved
    }

    fun language() = language

    fun ready() = ready

    /**
     * load a top level branch. This is not part of the main loading procedure, but performed on demand based on
     * the getItem() requests.
     */
    fun loadBranch(branchName: String, loadDefault: Boolean = false) {

        if ((this.loaded[branchName] ?: false) && !loadDefault) return

        val rootToUse =
                if (loadDefault) {
                    // create a new comparison root
                    this.defaultsRootItem = Item.getFloating(defaultsRootItemDefinition)
                    this.defaultsRootItem
                } else
                    this.rootItem
        // load the configuration structure including defaults
        for (settingsDir in allSettingsDirs) {
            // the packaged and tenant have no subdirectories
            if ((settingsDir == "packaged") || (settingsDir == "tenant")) {
                // exclude the tenant, if the default is to be loaded
                if (!loadDefault || (settingsDir != "tenant")) {
                    val source = "Config/$settingsDir/$branchName"
                    rootToUse.readSourceFile(source)
                }
            } else {
                // plugins and flavours have a subdirectory per plugin and flavour
                for (key in localCache?.keys() ?: listOf()) {
                    if (key.startsWith("Config/$settingsDir") && key.endsWith(branchName))
                        rootToUse.readSourceFile(key)
                }
            }
        }
        if (!loadDefault)
            this.loaded[branchName] = true
    }

    /**
     * Load the configuration. Descriptor and types are read from resource files
     */
    suspend fun load(resProvider: ResProviderIF) {

        // Read the packaged settings from the program package into the local storage.
        // Never use any download, because there may be a version difference between server and client.
        val lc = localCache ?: return
        for (settingsFile in allSettingsFiles) {
            val lcPath = "Config/packaged/$settingsFile"
            val cachedFile = lc.getItem(lcPath)
            if (cachedFile.isEmpty() ||
                SettingsLoader.invalidConfigurationFile(lcPath, cachedFile, lc))
                try {
                    lc.setItem(lcPath, resProvider.readBytes("files/Config/packaged/$settingsFile").decodeToString())
                } catch (_: Exception) {
                    logger.log(LoggerSeverity.ERROR, "Config.load()", "Missing packaged settings file: $settingsFile")
                    lc.setItem(lcPath, "")
                }
        }

        // initialise the Type object
        val descriptorCsv = lc.getItem("Config/packaged/descriptor")
        val typesCsv = lc.getItem("Config/packaged/types")
        Type.init(descriptorCsv, typesCsv)
        this.loaded.clear()

        // No web session management in Kotlin client, therefore only User management
        User.setIncludedRoles()  // this loads the access configuration branch

        // set tables and language
        Record.copyCommonFields()  // this loads the tables configuration branch

        // specific for the client implementation: read ui layouts and theme settings
        getItem(".framework") // in PHP and JavaScript this is nott needed.
        getItem(".templates")
        getItem(".app")
        getItem(".ui")
        getItem(".theme")

        val languageString = getItem(".app.user_preferences.language").valueCsv()
        language = Language.valueOfOrDefault(languageString.uppercase())
        appName = getItem(".framework.app.name").valueCsv()
        appUrl = getItem(".framework.app.url").valueCsv()
        appVersion = getItem(".framework.app.version").valueStr()

        // trigger the loading of the language resource file
        I18n.getInstance().loadResource(language, resProvider)

        // loading completed, trigger recomposition
        ready = true
    }

    /**
     * Create a backup String which contains the settings tree in the settings-directory slices.
     */
    fun createBackup(): String {
        var backupString = ""
        for (lcKey in (localCache?.keys() ?: mutableSetOf())) {
            if (lcKey.startsWith("Config")) {
                backupString += "Config::$lcKey\n${rootItem.branchToCsv(99, lcKey)}\n\n"
            }
        }
        return backupString
    }

}