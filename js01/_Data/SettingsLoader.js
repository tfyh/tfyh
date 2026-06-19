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

class SettingsLoader {

    static #serverModified = {}
    static #loadingCompleted = false
    static callback = {}

    static invalidConfigurationFile(fileName, contents) {
        // check for the correctness of the read file. Internet retrieval has chances for hazard.
        // The file must have at least a headline
        let fileIsInvalid = (contents.length < 5)
        // ... a matching first word
        // _path for all "normal" settings files, _name for the type file, name; for the descriptor file
        let firstWord = (fileIsInvalid) ? "" : contents.trim().substring(0, 5).toLowerCase()
        fileIsInvalid = fileIsInvalid || ((firstWord !== "_path") && (firstWord !== "_name") && (firstWord !== "name;"));
        // ... and a headline with at least 2 entries
        let firstLine = contents.split("\n")[0]
        fileIsInvalid = fileIsInvalid || (firstLine.split(";").length < 2)
        // if it is invalid, remove it from the cache
        if (fileIsInvalid) {
            LocalCache.getInstance().removeItem(fileName)
            LocalCache.getInstance().removeItem(fileName + ".modified")
            console.log("Invalid configuration file " + fileName + ". Starts with '" + contents.substring(0, 100) + " ...'."
                + "The file was removed, please reload the page.")
            return true
        }
        return false
    }

    constructor(callback) {
        SettingsLoader.callback = callback
        SettingsLoader.#serverModified = {}
        this.requestModified()
    }

    requestModified() {
        SettingsLoader.#loadingCompleted = false
        let that = this
        $.get('../_pages/jsGet.php?info=modified', function(data) {
            //  first step download of modification times
            let mtimePairs = data.split("\n")
            for (let pair of mtimePairs)
                SettingsLoader.#serverModified[pair.split("=")[0]] = parseInt(pair.split("=")[1])
            console.log("Loaded " + mtimePairs.length + " settings modification times.")
            that.loadRepeat(0)
        })
    }

    loadRepeat(fileIndexToLoad) {
        let that = this
        let fileName = Object.keys(SettingsLoader.#serverModified)[fileIndexToLoad]
        let lcModified = parseInt(LocalCache.getInstance().getItem(fileName + ".modified"))
        fileIndexToLoad++

        if (// read a file, if it was not yet read at all, but exists on the server side
            isNaN(lcModified) ||
            // read a file only if it is more recent at the server side
            (lcModified < SettingsLoader.#serverModified[fileName])) {
            $.get('../_pages/jsGet.php?info=' + fileName, function(data) {
                // copy data to local cache and set the modification time stamp
                LocalCache.getInstance().setItem(fileName, data.trim())
                LocalCache.getInstance().setItem(fileName + ".modified",
                    SettingsLoader.#serverModified[fileName])
                console.log("Configuration loading of " + data.length + " bytes of " + fileName)
                // remove, if invalid
                SettingsLoader.invalidConfigurationFile(fileName, data)
                if (fileIndexToLoad < Object.keys(SettingsLoader.#serverModified).length)
                    that.loadRepeat(fileIndexToLoad)
                else
                    SettingsLoader.callback()
            })
        } else {
            SettingsLoader.invalidConfigurationFile(fileName, LocalCache.getInstance().getItem(fileName))
            console.log("Skipped (up to date locally): " + fileName
                + ". local timestamp: " + Formatter.microTimeToString(lcModified, config.language)
                + ", server timestamp: " +  Formatter.microTimeToString(SettingsLoader.#serverModified[fileName], config.language))
            if (fileIndexToLoad < Object.keys(SettingsLoader.#serverModified).length)
                that.loadRepeat(fileIndexToLoad)
            else
                SettingsLoader.callback()
        }
        if (SettingsLoader.#loadingCompleted && (fileIndexToLoad === 1))
            SettingsLoader.callback()
    }
}