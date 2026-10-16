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
 * A utility class to load all application configuration.
 */

class Config {

    static CACHE_PATH = "Config"

    static allSettingsDirs = [
        // the sequence is relevant for the loading process, do not change
        "packaged", // contains below settings files, comes packaged with the application
        "plugins", // contains per plugin a directory with the plugin name and a subset of the below settings files
        "flavours", // contains per flavour a directory with the flavour name and a subset of the below settings files
        "tenant" // contains a subset of below settings files with tenant modified and added structure
    ]
    static allSettingsFiles = [
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
    ]
    static #rootItemDefinition = { _name: "root", item_label: "root", value_type: "none" }
    static #invalidItemDefinition = { _name: "invalid", item_label: "invalid", value_type: "none" }
    static #defaultsRootItemDefinition = { _name: "defaultsRoot", item_label: "root", value_type: "none" }
    static #instance

    static getInstance() {
        if (!Config.#instance)
            Config.#instance = new Config()
        return Config.#instance
    }

    // no JavaScript implementation
    static getModified() { return "" }

    // Initialise the root and invalid item. They have no configuration file entry
    invalidItem = Item.getFloating(Config.#invalidItemDefinition)
    rootItem = Item.getFloating(Config.#rootItemDefinition)
    defaultsRootItem = Item.getFloating(Config.#defaultsRootItemDefinition)

    #loaded = [];
    #language = Language.EN

    appName = ""
    appVersion = "0.0"
    appUrl = ""

    constructor() {
    }

    /**
     * Get an Item by its path. Returns the invalid Handle on errors. Returns the item of the
     * default items branch, if getDefault is true.
     */
    getItem(path, getDefault = false) {
        if ((path.length === 0) || (path === "."))
            return config.rootItem
        if (!path.startsWith("."))
            return config.invalidItem
        let names = path.substring(1).split(".")
        let i = 0
        let parent = config.rootItem
        while ((i < names.length) && (parent != null) && parent.hasChild(names[i]))
            parent = parent.getChild(names[i++])
        if (parent !== null) {
            if (i === names.length)
                return parent // path fully resolved
            if (parent === parent.parent()){
                // hit top level
                if (!(this.#loaded[names[0]] ?? false)) {
                    this.loadBranch(names[0], getDefault)
                    return this.getItem(path, getDefault)
                } else
                    return parent;
            }
        }
        return config.invalidItem // the path is not resolved
    }

    // access language and timezone offset directly, no getter (duplicate name problem)
    language() { return this.#language }

    /**
     * load a top-level branch. This is not part of the main loading procedure but performed on demand based on
     * the getItem() requests.
     */
    loadBranch(branchName, loadDefault = false) {
        if ((this.#loaded[branchName] ?? false) && !loadDefault)
            return;

        let rootToUse
        if (loadDefault) {
            // create a new comparison root
            this.defaultsRootItem = Item.getFloating(Config.#defaultsRootItemDefinition)
            rootToUse = this.defaultsRootItem
        } else
            rootToUse = this.rootItem
        // load the configuration structure including defaults
        for (let settingsDir of Config.allSettingsDirs) {
            // the packaged and tenant have no subdirectories
            if ((settingsDir === "packaged") || (settingsDir === "tenant")) {
                // exclude the tenant, if the default is to be loaded
                if (!loadDefault || (settingsDir !== "tenant")) {
                    let source = "Config/" + settingsDir + "/" + branchName
                    rootToUse.readSourceFile(source)
                }
            } else {
                // plugins and flavours have a subdirectory per plugin and flavour
                for (let key of LocalCache.getInstance().keys()) {
                    if (key.startsWith("Config/" + settingsDir) && key.endsWith(branchName))
                        rootToUse.readSourceFile(key)
                }
            }
        }
        if (!loadDefault)
            this.#loaded[branchName] = true
    }

    /**
     * Load the minimum configuration. This will only parse CSV files; therefore, no language setting is needed
     * beforehand.
     */
    load(callback) {

        // initialise the Type object
        let descriptorCsv = LocalCache.getInstance().getItem(Config.CACHE_PATH + "/packaged/descriptor")
        let typesCsv = LocalCache.getInstance().getItem(Config.CACHE_PATH + "/packaged/types")
        Type.init(descriptorCsv, typesCsv)
        this.#loaded = []

        // initialise the root and invalid item. The descriptor is not yet initialised at this
        // point in the bootstrap process, so only static properties can be used.
        this.invalidItem = Item.getFloating(Config.#invalidItemDefinition)
        this.rootItem = Item.getFloating(Config.#rootItemDefinition)

        // No web session management in JavaScript client therefore, only User management
        User.getInstance().setIncludedRoles()

        // set tables and language
        Record.copyCommonFields()

        // specific for the client implementation: load the ui layouts by calling their root items
        config.getItem(".templates")
        config.getItem(".app")
        config.getItem(".ui")

        let languageString = this.getItem(".app.user_preferences.language").valueCsv()
        this.#language = Language.valueOfOrDefault(languageString)
        this.appName = this.getItem(".framework.app.name").valueCsv();
        this.appUrl = this.getItem(".framework.app.url").valueCsv();
        this.appVersion = this.getItem(".framework.app.version").valueStr()

        // ===== initialise the internationalisation support and then call back
        let i18n = I18n.getInstance()
        i18n.loadResource(this.language(), callback)
    }
}
