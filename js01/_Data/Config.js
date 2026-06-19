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
    static allSettingsFiles = [   // the sequence is relevant for the loading process, do not change
        // settings descriptor
        "descriptor", // the file to define the properties which describe a value
        "types", // the file to define the available value types
        // immutable settings. These will never change in structure nor get actual values
        "access", // the settings which configure roles, menus, workflows, concessions and subscriptions
        "templates", // configuration branches which are available for multiple use in this app
        "framework", // the setting which configures the framework classes for this app
        "tables", // the database table layout
        // tenant-mutable structure. These may get added or deleted items and actual values
        "lists", // the settings which configure list retrievals off the database
        "app", // all settings of the application needed to run at the tenant.
        "catalogs", // the catalogues of types, like the valid boat variants asf.
        "ui" // user interface layout and other settings
    ]
    static allSettingsDirs = [
        "packaged", "added"
    ]
    static #rootItemDefinition = { name: "root", default_label: "root", value_type: "none" }
    static #invalidItemDefinition = { name: "invalid", default_label: "invalid", value_type: "none" }
    static #instance

    static getInstance() {
        if (!Config.#instance)
            Config.#instance = new Config()
        return Config.#instance
    }

    // no JavaScript implementation
    static getModified() { return "" }

    // Initialise the root and invalid item. They have no configuration file entry
    rootItem = Item.getFloating(Config.#rootItemDefinition)
    invalidItem = Item.getFloating(Config.#invalidItemDefinition)
    // no "actualSettingsMap" in JavaScript like int kotlin, because the packaged settings are read from the server

    #loaded = [];

    #language = Language.EN
    #timeZoneOffset = new Date().getTimezoneOffset()

    appName = ""
    appVersion = "0.0"
    appUrl = ""

    constructor() {
    }

    // get a summary of the current dilbo installation for display in the dialogue
    dilboAbout() {
        return "the digital logbook for rowing and canoeing~\\" +
            "\u00a9 " + this.appUrl + "<br>" +
            "version: " + this.appVersion + "<br>" +
            "language: " + this.#language
    }

    /**
     * Get an Item by its path. Returns the invalid Handle on errors
     */
    getItem(path) {
        if (path.length === 0)
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
                    this.loadBranch(names[0])
                    return this.getItem(path)
                } else
                    return parent;
            }
        }
        return config.invalidItem // the path is not resolved
    }

    // access language and timezone offset directly, no getter (duplicate name problem)
    language() { return this.#language }
    timeZoneOffset() { return this.#timeZoneOffset }

    /**
     * load a top-level branch. This is not part of the main loading procedure but performed on demand based on
     * the getItem() requests.
     */
    loadBranch(branchName) {
        if (this.#loaded[branchName] ?? false)
            return;
        for (let settingsDir of Config.allSettingsDirs) {
            let settingsCsv = LocalCache.getInstance().getItem(Config.CACHE_PATH + "/" + settingsDir + "/" + branchName)
            let settingsMap = Codec.csvToMap(settingsCsv)
            let loadingResult = this.rootItem.readBranch(settingsMap)
            if (loadingResult.length > 0)
                console.log("ERROR: Config->load() - " + loadingResult)
        }
        // no "actualSettingsMap" in JavaScript like int kotlin, because the packaged settings are read from the server
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

        // specific for the client implementation: read ui layouts
        config.getItem(".templates")
        config.getItem(".app")
        config.getItem(".ui")

        let languageString = this.getItem(".app.user_preferences.language").valueCsv()
        this.#language = Language.valueOfOrDefault(languageString)
        this.appName = this.getItem(".framework.app.name").valueCsv();
        this.appUrl = this.getItem(".framework.app.url").valueCsv();
        this.appVersion = this.getItem(".framework.app.version").valueStr()

        // initialise the locale settings for parser and formatter not yet,
        // see the main loader (dilboMain e.g.)
        callback()
    }
}
