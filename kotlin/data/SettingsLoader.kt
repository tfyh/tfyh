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
import org.tfyh.api.Transaction
import org.tfyh.control.LoggerSeverity

class SettingsLoader {

    /**
     * The companion object holds the configuration modification times. It provides a function to
     * validate the configuration files in order to be able to distinguish them from server error
     * responses
     */
    companion object {
        private val serverTimes = mutableMapOf<String, Double>()

        /**
         * Return true if the configuration file is invalid. This may happen in case of server
         * error responses. Remove the invalid file from the local cache if the cache is provided.
         */
        fun invalidConfigurationFile(fileName: String, contents: String, localCache: LocalCacheIF? = null): Boolean {
            // check for correctness of read file. Internet retrieval has chances for hazard.
            // file must have at least a headline
            var fileIsInvalid = (contents.length < 5) // file must have at least a headline
            // ... a matching first word
            // _path for all "normal" settings files, _name fot the types file, name; for the descriptor file
            val firstWord = contents.trim().substring(0, 5).lowercase()
            fileIsInvalid = fileIsInvalid || ((firstWord != "_path") && (firstWord != "_name")
                    && (firstWord != "name;"))
            // ... and a headline with at least 2 entries
            val firstLine = contents.split("\n")[0]
            fileIsInvalid = fileIsInvalid || (firstLine.split(";").size < 2)
            // if it is invalid, remove it from the cache
            if (fileIsInvalid) {
                localCache?.removeItem(fileName)
                localCache?.removeItem("$fileName.modified")
                Config.getInstance().logger.log(LoggerSeverity.ERROR,
                    "SettingsLoader.invalidConfigurationFile()",
                    "Invalid configuration file " + fileName + ". Starts with '" +
                            contents.substring(0, 100) + " ...'." + "The file was removed.")
                return true
            }
            return false
        }
    }

    /**
     * Read the server modified timestamps using the transaction response
     */
    private fun readServerModified(response: String) {
        val times = response.split("\n")
        for (timeStr in times) {
            val name = timeStr.split("=")[0]
            val timeDbl = try {
                timeStr.split("=")[1].toDouble()
            } catch (_: Exception) {
                0.0
            }
            serverTimes[name] = timeDbl
        }
    }

    /**
     * Read the server response with the configuration files' last modified timestamp and trigger a
     * configuration read request, if a server modification is more recent than the local time stamp.
     */
    fun onModifiedResponse(response: String) {
        readServerModified(response)
        val apiHandler = ApiHandler.getInstance()
        for (settingsFile in serverTimes.keys) {
            // only non-packaged configuration files are loaded from the server. The program versions
            // of client and server may differ and packaged settings are linked to the version
            if (!settingsFile.startsWith("Config/packaged")) {
                val localCache = Config.getInstance().getLocalCache()
                val localModified =
                    try {
                        localCache?.getItem("$settingsFile.modified")?.toDouble() ?: 0.0
                    } catch (_: Exception) {
                        0.0
                    }
                val serverModified = serverTimes[settingsFile] ?: 0.0
                if (localModified < serverModified) {
                    apiHandler.addNewTxToPending(Transaction.TxType.LIST,
                        ".$settingsFile", emptyMap())
                    apiHandler.logger.log(LoggerSeverity.INFO, "SettingsLoader.onModifiedResponse()",
                        "updating $settingsFile")
                } else
                    apiHandler.logger.log(LoggerSeverity.INFO, "SettingsLoader.onModifiedResponse()",
                        "Skipped $settingsFile, already up-to-date")
            }
        }
        // as a last step get all actual values, labels, descriptions
        apiHandler.addNewTxToPending(Transaction.TxType.LIST,".actuals", emptyMap())
    }

    /**
     * update the local cache with the new settings received.
     */
    fun onSettingsResponse(tx: Transaction) {

        val settingsFile = tx.tableName.substring(1)
        if (invalidConfigurationFile(settingsFile, tx.resultMessage.trim()))
            return // logging was already done

        val localCache = Config.getInstance().getLocalCache() ?: return
        localCache.setItem(settingsFile, tx.resultMessage.trim())
        localCache.setItem("$settingsFile.modified", serverTimes[settingsFile].toString())

        val topBranchName = settingsFile.substring(settingsFile.lastIndexOf("/") + 1)
        val topBranch = Config.getInstance().rootItem.getChild(topBranchName)
        topBranch?.readSourceFile(settingsFile)

    }

}

