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

import org.tfyh.api.ApiHandler.Companion.API_VERSION
import org.tfyh.api.ApiHandler.Companion.MESSAGE_SEPARATOR_STRING
import org.tfyh.api.ApiHandler.Companion.MS_REPLACEMENT_STRING
import org.tfyh.api.ApiHandler.Companion.apiHandler
import org.tfyh.control.EventHandlerIF
import org.tfyh.control.Logger
import org.tfyh.control.LoggerSeverity
import org.tfyh.data.Codec

/**
 * The API container class providing container transmission check an error handling,
 * container parsing.
 */
open class Container private constructor() {

    /**
     * Then different types of results. Except for 300 and 900 these are all errors
     */
    enum class ResultType (val result: Result) {
        // undefined
        UNDEFINED(Result(0, "Undefined",
            isPermanentError = false,
            isServerGenerated = false)),
        // success
        REQUEST_AUTHENTICATED(Result(20, "Request container authenticated",
            isPermanentError = false,
            isServerGenerated = true
        )), // issued by SERVER only
        RESPONSE_SUCCESSFULLY_PARSED(Result(22, "Response container successfully parsed",
            isPermanentError = false,
            isServerGenerated = false
        )), // issued by client only
        // connection failure (assumed to be temporary)
        INTERNET_CONNECTION_FAILED(Result(40, "Internet connection failed",
            isPermanentError = false,
            isServerGenerated = false
        )), // issued by client only
        INTERNET_CONNECTION_TIMEOUT(Result(42, "Internet connection timeout",
            isPermanentError = false,
            isServerGenerated = false
        )), // issued by client only
        HTTP_COMMUNICATION_ERROR(Result(44, "Http communication error",
            isPermanentError = false,
            isServerGenerated = false
        )), // issued by client only
        SERVER_ERROR(Result(46, "Internal server error",
            isPermanentError = false,
            isServerGenerated = false
        )), // issued by client only
        SERVER_OVERLOAD(Result(48, "Server overload",
            isPermanentError = false,
            isServerGenerated = true
        )), // issued by SERVER only
        // content error (assumed to be permanent)
        // content error (all container transactions are moved from the busy into the failed queue)
        SYNTAX_ERROR(Result(60, "Syntax error",
            isPermanentError = true,
            isServerGenerated = false
        )),
        API_VERSION_NOT_SUPPORTED(Result(62, "API version not supported",
            isPermanentError = true,
            isServerGenerated = true
        )),
        MISMATCHING_ID(Result(64, "Mismatching Container ID in server response",
            isPermanentError = true,
            isServerGenerated = false
        )), // issued by client only
        UNKNOWN_CLIENT(Result(66, "Unknown client",
            isPermanentError = true,
            isServerGenerated = true
        )), // issued by SERVER only
        AUTHENTICATION_FAILED(Result(68, "Authentication failed",
            isPermanentError = true,
            isServerGenerated = true
        )), // issued by SERVER only
        EMPTY_RESPONSE_CONTAINER(Result(70, "Empty response container",
            isPermanentError = true,
            isServerGenerated = false
        )), // issued by client only
        TX_ID_NOT_MATCHED(Result(72, "transactionId in response container not matched",
            isPermanentError = true,
            isServerGenerated = false
        )), // issued by client only
        MISSING_TRANSACTION(Result(74, "Missing transaction in response container",
            isPermanentError = true,
            isServerGenerated = false
        )), // issued by client only
        INVALID_RESULT_CODE(Result(76, "Invalid result code",
            isPermanentError = true,
            isServerGenerated = false
        )), // issued by client only
    }

    /**
     * a container to hold the parsed result as code and message
     */
    data class Result(
        val code: Int,
        val message: String,
        val isPermanentError: Boolean,
        val isServerGenerated: Boolean
    )

    companion object {
        protected val container = Container()
        fun getInstance() = container
    }

    private var logger: Logger? = null

    // transaction values as set iun the request
    val version : Int = API_VERSION
    private var cID : Int = 42
    var user : Int = -1
    private var sessionId : String = ""
    var transactions : MutableList<Transaction> = mutableListOf()
    // result for container: server response fields
    internal var cResultCode : Int =  -1
    internal var cResultMessage : String = ""
    // error message in case of container parsing errors
    internal var cParsingError : String = ""

    // this function is used to get the ResultType by the result code
    private fun getType(code: Int): ResultType {
        for (type in ResultType.entries)
            if (type.result.code == code)
                return type
        return ResultType.UNDEFINED
    }

    fun setLogger(logger: Logger) { this.logger = logger }

    /**
     * Get a new empty container
     */
    fun getEmpty() {
        cID++
        user = -1
        sessionId = ""
        transactions.clear()
        cResultCode =  -1
        cResultMessage = ""
        cParsingError = ""
    }


    /**
     * Build the API base64 String for this container including the container ID and the credentials.
     */
    fun build (apiUserId: Int, apiSessionId: String): String {
        // use the globally provided user and session id, from page load.
        var plain = "$version;$cID;$apiUserId;$apiSessionId;"
        for (i in transactions.indices) {
            val tx = transactions[i]
            plain += buildString {
                append(tx.transactionId.toString())
                append(";")
                append(tx.type.toString().lowercase())
                append(";")
                append(tx.tableName)
                append(";")
            }
            for (key in tx.record.keys) {
                val value =
                    tx.record[key]?.replace(MESSAGE_SEPARATOR_STRING, MS_REPLACEMENT_STRING)
                plain += key + ";" + Codec.encodeCsvEntry(value) + ";"
            }
            plain = plain.substring(0, plain.length - 1) + MESSAGE_SEPARATOR_STRING
        }
        plain = plain.substring(0, plain.length - MESSAGE_SEPARATOR_STRING.length)
        val base64api = Codec.apiEncode(plain)
        logger?.log(
            LoggerSeverity.INFO, "ApiHandler.buildContainer",
            "Container built: " + transactions.size + " transactions in " + base64api.length + " base64 characters")
        return base64api
    }

    /**
     * Set the container failed error for all container transactions, if the container itself
     * failed and remove the transactions from the container to unblock the queue
     */
    fun setFailedAndRemoveAllTransactions() {
        val logger = ApiHandler.getInstance().logger
        logger.log(LoggerSeverity.ERROR,
            "Container.setFailedAndRemoveAllTransactions()",
            "Container #$cID failed: $cResultMessage. Invalidating all transactions.")
        transactions.forEach {
            it.resultCode = Transaction.ResultType.CONTAINER_ERROR.result.code
            it.resultMessage = if (cParsingError.isEmpty())
                Transaction.ResultType.CONTAINER_ERROR.result.message
            else
                cResultMessage
            it.callBack(it)
        }
        transactions.clear()
    }

    /**
     * parse a server response into the transactions of this container and handle all errors and
     * results - including the container's transactions
     *
     * @params String response to be parsed.
     * @return true, if all transactions of the container received a response.
     */
    fun processResponse (response: String) {

        // Handle API compatibility, container Id and too few header parts errors
        val plain = Codec.apiDecode(response)
        val plainParts = plain.split(";")
        val plainShort = if (plain.length < 100) plain else plain.substring(0, 100) + "..."
        val responseApiVersion = try { plainParts[0].toInt() } catch (_: NumberFormatException) { 0 }
        val responseContainerId = try { plainParts[1].toInt() } catch (_: Exception) { -1 }
        if ((plainParts.size < 4) || (responseApiVersion > version) || (responseContainerId != cID)) {
            val cParsingResult =
                if (responseApiVersion > version) {
                    ResultType.API_VERSION_NOT_SUPPORTED
                }
                else if (responseContainerId != cID)
                    ResultType.MISMATCHING_ID
                else
                    ResultType.SYNTAX_ERROR
            apiHandler.lastContainerResult = cParsingResult.result
            cParsingError += cParsingResult.result.message + ". Container response: " + plainShort
            setFailedAndRemoveAllTransactions()
            return
        }

        // handle server provided result code error
        cResultMessage = plainParts[3]
        try {
            val containerResult = getType(plainParts[2].toInt())
            apiHandler.lastContainerResult = containerResult.result
            cResultCode = containerResult.result.code
            if (cResultCode >= 40) {
                if (cResultMessage.isEmpty()) cResultMessage = plainShort
                setFailedAndRemoveAllTransactions()
                if ((cResultCode == ResultType.UNKNOWN_CLIENT.result.code) ||
                    (cResultCode == ResultType.AUTHENTICATION_FAILED.result.code)) {
                    // remove all SESSION transactions from the queue
                    val transactionsList = transactions.toList()
                    for (tx in transactionsList)
                        if (tx.type == Transaction.TxType.SESSION)
                            apiHandler.removeTxFromPending(tx)
                    // invalidate the credentials. That will suppress container generation
                    apiHandler.setCredentials(-1, "")
                    val apiEvent =
                        EventHandlerIF.Event(EventHandlerIF.EventType.API_EVENT,
                            if (cResultCode == ResultType.UNKNOWN_CLIENT.result.code) "unknownClient"
                            else "authenticationFailed")
                    apiHandler.eventHandler.handleEvent(apiEvent)
                }
                return
            }
        } catch (_: NumberFormatException) {
            apiHandler.lastContainerResult = ResultType.SYNTAX_ERROR.result
            cResultCode = ResultType.SYNTAX_ERROR.result.code
            cParsingError += ResultType.SYNTAX_ERROR.result.message + plainShort
            setFailedAndRemoveAllTransactions()
            val apiEvent =
                EventHandlerIF.Event(EventHandlerIF.EventType.API_EVENT, "containerFailed")
            apiHandler.eventHandler.handleEvent(apiEvent)
            return
        }

        // split transaction responses within container.
        val txcHeaderLength = plainParts[0].length + plainParts[1].length + plainParts[2].length + plainParts[3].length + 4
        val txcBody = if (plain.length < txcHeaderLength) "" else plain.substring(txcHeaderLength)

        // handle no transactions in container error
        if (txcBody.isEmpty()) {
            apiHandler.lastContainerResult = ResultType.EMPTY_RESPONSE_CONTAINER.result
            cParsingError += ResultType.EMPTY_RESPONSE_CONTAINER.result.message
            setFailedAndRemoveAllTransactions()
            return
        }

        // parse and handle transactions
        apiHandler.lastContainerResult = ResultType.RESPONSE_SUCCESSFULLY_PARSED.result
        val txResponsesAsList = txcBody.split(MESSAGE_SEPARATOR_STRING)
        for (i in txResponsesAsList.indices) {
            if (txResponsesAsList[i].isNotEmpty()) {
                val tx = transactions[i]
                // the result code may already have been set by a container error
                if (tx.resultCode > 40)
                    tx.handleError()
                else {
                    tx.parseResponse(txResponsesAsList[i])
                    if (tx.resultCode > 40)
                        tx.handleError()
                    else {
                        tx.processTransaction()
                        if (tx.resultCode >= 40)
                            tx.handleError()
                    }
                }
                apiHandler.removeTxFromPending(tx)
            }
        }
        // remove all transactions from the container. If a transaction was not contained in the response
        // and therefore not processed, it stays in the queue and will be retransmitted. Duplicate
        // sending will do no harm: updates and deletes anyway and inserts will fail due to SQL uniqueness of
        // the ID.
        transactions.clear()
    }

    /**
     * Log a warning (retry scheduled) and an error (transactions failed), if it affects the entire container.
     */
    fun logResponseError(caller: String, result: Result) {
        if (result.isPermanentError)
            logger?.log(
                LoggerSeverity.ERROR, caller,
                "Container failed permanently. ${transactions.size} transactions affected. Reason: " + result.message)
        else
            logger?.log(
                LoggerSeverity.WARNING, "ApiHandler.handleHttpsError",
                "Container failed temporarily. Will retry. Reason: " + result.message)
    }

}