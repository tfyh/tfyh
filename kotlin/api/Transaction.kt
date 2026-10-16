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

package org.tfyh.api

import org.tfyh.api.ApiHandler.Companion.MESSAGE_SEPARATOR_STRING
import org.tfyh.api.ApiHandler.Companion.MS_REPLACEMENT_STRING
import org.tfyh.control.LoggerSeverity
import org.tfyh.data.Codec
import org.tfyh.data.DataBase
import org.tfyh.data.Ids
import org.tfyh.data.SettingsLoader
import kotlin.time.Clock

/**
 * The API transaction class, providing the transaction structure, its parsing and error handling.
 */
data class Transaction(
    val apiHandler: ApiHandler,
    // transaction values as set by the request
    val transactionId : Int = -1,
    val type : TxType = TxType.NOP,
    val tableName : String = "",
    val uid : String = Ids.generateUid(9), // only for local storage, not used at API
    var record : Map<String, String> = emptyMap(),
    // transaction result as provided by the server
    var resultCode : Int = ResultType.UNDEFINED.result.code,
    var resultMessage : String = "",
    // status control
    var sentAt : Long = 0L,
    var resultAt : Long = 0L,
    var closedAt : Long = 0L,
    var callBack : (Transaction) -> Unit = { }
) {

    enum class TxType {
        UNDEFINED, NOP, SESSION, INSERT, UPDATE, DELETE, LIST, SELECT
    }

    /**
     * Then different types of results.
     */
    enum class ResultType (val result: Result) {
        // undefined
        UNDEFINED(Result(0, "Undefined",
            isPermanentError = false,
            isServerGenerated = false)),
        // success
        REQUEST_SUCCESSFULLY_PROCESSED(Result(21, "Transaction request successfully processed at server",
            isPermanentError = false,
            isServerGenerated = true
        )), // issued by SERVER only
        RESPONSE_SUCCESSFULLY_PROCESSED(Result(23, "Transaction response successfully processed at client",
            isPermanentError = false,
            isServerGenerated = false
        )), // issued by client only
        // container failure (the container will then care for retry, if necessary)
        CONTAINER_ERROR(Result(41, "Container error",
            isPermanentError = false,
            isServerGenerated = false
        )),
        MISSING_IN_RESPONSE_CONTAINER(Result(43, "Missing in response container",
            isPermanentError = true,
            isServerGenerated = false
        )), // issued by client only
        // content failure (the transaction is moved from the busy into the failed queue)
        SYNTAX_ERROR(Result(61, "Syntax error in server response",
            isPermanentError = true,
            isServerGenerated = false
        )),
        MISMATCHING_ID(Result(63, "Mismatching transactionId in server response",
            isPermanentError = true,
            isServerGenerated = false
        )), // issued by client only
        TRANSACTION_INVALID(Result(65, "Transaction invalid",
            isPermanentError = true,
            isServerGenerated = true)), // issued by SERVER only
        TRANSACTION_FAILED(Result(67, "Transaction failed",
            isPermanentError = true,
            isServerGenerated = true)), // issued by SERVER only
        TRANSACTION_FORBIDDEN(Result(69, "Transaction forbidden",
            isPermanentError = true,
            isServerGenerated = true
        )), // issued by SERVER only
        INVALID_RESULT_CODE(Result(71, "invalid result code",
            isPermanentError = true,
            isServerGenerated = true
        )), // issued by client only
    }

    companion object {

        /**
         * a container to hold the parsed result as code and message
         */
        data class Result(
            val code: Int,
            val message: String,
            val isPermanentError: Boolean,
            val isServerGenerated: Boolean
        )

        // this function is used to initialise the ResultType enum
        fun resultOf(code: Int): Result {
            for (type in ResultType.entries)
                if (type.result.code == code)
                    return type.result
            return ResultType.UNDEFINED.result
        }

        /**
         * read a transaction from the local storage. Return null on errors. Returns a new Transaction
         * instead of updating the existing one.
         */
        fun readStored(id: Int, apiHandler: ApiHandler): Transaction? {
            // transaction values as set by the request
            val parts = Codec.splitCsvRow(apiHandler.localCache.getItem("${ApiHandler.CACHE_PATH_PENDING}/tx-$id"))
            if (parts.size < 6)
                return null
            var transactionId = id
            var txType = TxType.NOP
            var tableName = ""
            var resultCode = ResultType.UNDEFINED.result.code
            var sentAt = 0L
            var resultAt = 0L
            var closedAt = 0L
            var resultMessage = ""
            try {
                transactionId = parts[0].toInt()
                txType = TxType.valueOf(parts[1].uppercase())
                tableName = parts[2]
                sentAt = parts[3].toLong()
                resultAt = parts[4].toLong()
                closedAt = parts[5].toLong()
                resultCode = parts[6].toInt()
                resultMessage = parts[9]
            } catch (_: Exception) {}

            // only return a Transaction, itf at least a table name was recognised
            return if (tableName.isNotEmpty())
                Transaction(
                    apiHandler = apiHandler,
                    transactionId = transactionId,
                    type = txType,
                    tableName = tableName,
                    sentAt = sentAt,
                    resultAt = resultAt,
                    closedAt = closedAt,
                    resultCode = resultCode,
                    resultMessage = resultMessage,
                ) else
                null
        }

    }

    override fun toString() : String {
        var plain = "${transactionId};$type;$tableName;$sentAt;$resultAt;$closedAt;$resultCode;$resultMessage;"
        for (key in record.keys) {
            val valueToStore = Codec.encodeCsvEntry(record[key]?.replace(MESSAGE_SEPARATOR_STRING, MS_REPLACEMENT_STRING))
            plain += "$key;$valueToStore;"
        }
        return plain.substring(0, plain.length - 1)
    }

    /**
     * Store a transaction in the local storage. This will overwrite any previously stored version
     * of this transaction.
     */
    fun writeStored(cachePath: String) {
        // encode to CSV
        val plain = this.toString()
        // write it to the cache
        apiHandler.localCache.setItem("$cachePath/tx-${transactionId}", plain)
        // update the maximum transaction ID
        apiHandler.localCache.setItem("$cachePath/tx-max", transactionId.toString())

        // cleanse done and failed queues. Only keep the last 100 done transactions
        if ((cachePath == ApiHandler.CACHE_PATH_DONE)
            || (cachePath == ApiHandler.CACHE_PATH_FAILED)) {
            for (cached in apiHandler.localCache.keys())
                if (cached.startsWith(cachePath)) {
                    val idStr = cached.replace("$cachePath/tx-", "")
                    try {
                        val id = idStr.toInt()
                        if ((transactionId - id) > 100)
                            apiHandler.localCache.removeItem(cached)
                    } catch (_:Exception) {}
                }
        }
    }

    /**
     * remove a transaction in the local storage.
     */
    internal fun deleteStored(cachePath: String) = apiHandler.localCache
        .removeItem("$cachePath/tx-$transactionId")

    /**
     * parse a transaction response
     */
    fun parseResponse(response: String) {
        val responseParts = response.split(Regex(";"), 3)
        val parsedCode = try { responseParts[1].toInt() } catch (_: Exception) {
            ResultType.SYNTAX_ERROR.result.code }
        val parsedTId = try { responseParts[0].toInt() } catch (_: Exception) { 0 }
        if (parsedTId != transactionId) {
            resultCode = ResultType.MISMATCHING_ID.result.code
            resultMessage = ResultType.MISMATCHING_ID.result.message
        } else {
            resultCode = if (resultOf(parsedCode) == ResultType.UNDEFINED.result)
                ResultType.TRANSACTION_INVALID.result.code
            else
                parsedCode
            resultMessage = responseParts[2]
        }
    }

    /**
     * handling dispatcher for a single transaction
     */
    fun processTransaction() : ResultType {
        val resultType = when (type) {
            TxType.NOP, TxType.UNDEFINED -> {
                ResultType.RESPONSE_SUCCESSFULLY_PROCESSED
            }
            TxType.SESSION -> {
                onSessionResponse()
            }
            TxType.LIST, TxType.SELECT -> {
                onReadResponse()
            }
            TxType.INSERT, TxType.UPDATE, TxType.DELETE -> {
                onDataWriteResponse()
            }
        }
        this.closedAt = Clock.System.now().toEpochMilliseconds()
        apiHandler.logger.log(
            LoggerSeverity.INFO, "Transaction.processTransaction()",
            "#$transactionId $type $tableName [${apiHandler.pendingQueue.size}]: " +
                    resultType.result.message
        )
        // delete the transaction from the pending queue
        apiHandler.removeTxFromPending(this)
        // add the transaction to the done queue
        this.writeStored(ApiHandler.CACHE_PATH_DONE)
        return resultType
    }

    fun handleError() {
        ApiHandler.apiHandler.logger.log(
            LoggerSeverity.ERROR, "Transaction.handleError()",
            "tx-$transactionId $type $tableName failed: $resultCode $resultMessage.")
        callBack(this)
    }

    /**
     * Response on a session. Parse and refresh the session parameters.
     */
    private fun onSessionResponse () : ResultType {
        if (tableName != "close") {
            apiHandler.onSessionResponse(resultMessage)
            apiHandler.logger.log(
                LoggerSeverity.INFO, "ApiHandler.onSessionResponse",
                apiHandler.welcomeMessage.replace("<br>", "\n")
            )
        }
        return ResultType.RESPONSE_SUCCESSFULLY_PROCESSED
    }

    /**
     * Response on a read request. Trigger actions
     */
    private fun onReadResponse () : ResultType {
        val nowSeconds = Clock.System.now().epochSeconds
        apiHandler.lastSessionActivity = nowSeconds

        // the table name starting with a dor indicates a configuration read
        if (tableName.startsWith(".")) {
            val settingsLoader = SettingsLoader()
            when (tableName) {
                ".modified" -> settingsLoader.onModifiedResponse(resultMessage)
                else -> settingsLoader.onSettingsResponse(this)
            }
        } else {
            val db = DataBase.getInstance()
            val changed = db.mergeTable(tableName, resultMessage)
            if (changed) db.store()
        }
        return ResultType.RESPONSE_SUCCESSFULLY_PROCESSED
    }

    /**
     * Response on config request. Nothing to do but log the response.
     */
    private fun onDataWriteResponse () : ResultType {
        callBack(this)
        // TODO: notify change
        return ResultType.RESPONSE_SUCCESSFULLY_PROCESSED
    }

}
