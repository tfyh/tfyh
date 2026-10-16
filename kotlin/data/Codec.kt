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

import kotlin.io.encoding.Base64

@Suppress("Unused")
object Codec {

    const val BASE62 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789"

    /*
     * API encoding
     */
    fun apiEncode(plain: String): String {
        return Base64.encode(plain.encodeToByteArray())
            .replace("+", "-")
            .replace("/", "_")
            .replace("=", ".")
    }

    /*
     * API decoding
     */
    fun apiDecode(encoded: String): String {
        val replaced = encoded.replace("-", "+")
            .replace("_", "/")
            .replace(".", "=")
        return Base64.decode(replaced).decodeToString()
    }

    /*
     * TRANSCODING FROM AND TO CSV
     */
    /**
     * return the character position of the next line end. Skip line breaks in
     * between double quotes. Return length of csvString, if there is no more
     * line break.
     */
    private fun nextCsvLineEnd (csvString: String, cLineStart: Int): Int {
        var nextLinebreak = csvString.indexOf("\n", cLineStart)
        var nextDoubleQuote = csvString.indexOf("\"", cLineStart)
        var doubleQuotesPassed = 0
        while (((nextDoubleQuote >= 0) && (nextDoubleQuote < nextLinebreak))
            || (doubleQuotesPassed % 2 == 1)) {
            doubleQuotesPassed++
            nextLinebreak = csvString.indexOf("\n", nextDoubleQuote)
            nextDoubleQuote = csvString.indexOf("\"", nextDoubleQuote + 1)
        }
        return if (nextLinebreak == -1) csvString.length else nextLinebreak
    }

    /**
     * Split a csv formatted line into an array.
     */
    fun splitCsvRow (line: String?, separator: String = ";"): List<String> {
        // split entries by parsing the String, it may contain quoted elements.
        var entries = emptyList<String>()
        if (line == null)
            return entries
        var entryStartPos = 0

        while (entryStartPos < line.length) {
            // trim start if blank chars precede a """ character
            while ((entryStartPos < line.length) && (line[entryStartPos] == ' '))
                entryStartPos++
            // Check for quotation
            var entryEndPos = entryStartPos
            var quoted = false
            // while loop to jump over twin double quotes
            while ((entryEndPos < line.length) && (line[entryEndPos] == '"')) {
                quoted = true
                // Put pointer to first character after next double quote.
                entryEndPos = line.indexOf("\"", entryEndPos + 1) + 1
            }
            entryEndPos = line.indexOf(separator, entryEndPos)
            if (entryEndPos < 0)
                entryEndPos = line.length

            var entry = line.substring(entryStartPos, entryEndPos)
            if (quoted) {
                // remove opening and closing double quotes.
                val entryToParse = entry.substring(1, entry.length - 1)
                // replace all inner twin double quotes by single double quotes
                var nextSnippetStart = 0
                var nextDoubleQuote = entryToParse.indexOf("\"\"", nextSnippetStart)
                entry = ""
                while (nextDoubleQuote >= 0) {
                    // add the segment to the next twin double quote and the
                    // first double quote in it
                    entry += entryToParse.substring(nextSnippetStart, nextDoubleQuote + 1)
                    nextSnippetStart = nextDoubleQuote + 2
                    // continue search after the second of the twin double quotes
                    nextDoubleQuote = entryToParse.indexOf("\"\"", nextSnippetStart)
                }
                // add last segment (or full entry, if there are no twin
                // double quotes
                entry += entryToParse.substring(nextSnippetStart)
            }
            entries = entries.plus(entry)
            entryStartPos = entryEndPos + 1
            // if the line ends with a separator char, add an empty entry.
            if (entryStartPos == line.length)
                entries = entries.plus("")
        }
        return entries
    }
    /**
     * Join an array of Strings into a row.
     */
    fun joinCsvRow (row: List<String>, separator: String = ";"): String {
        var joined = ""
        for (entry in row)
            joined += separator + encodeCsvEntry(entry, separator)
        return if (joined.isEmpty()) "" else joined.substring(1)
    }

    /**
     * Read a csv String (; and " formatted) into an array<rows><columns>. It is
     * not checked, whether all rows have the same column width. This is plain
     * text parsing.
     */
    private fun csvToArray (csvString: String?): MutableList<MutableList<String>> {
        val table = mutableListOf<MutableList<String>>()
        if (csvString == null)
            return table
        var cLineStart = 0
        var cLineEnd = nextCsvLineEnd(csvString, cLineStart)
        while (cLineEnd > cLineStart) {
            val line = csvString.substring(cLineStart, cLineEnd)
            val entries = splitCsvRow(line)
            table.add(entries.toMutableList())
            cLineStart = cLineEnd + 1
            cLineEnd = nextCsvLineEnd(csvString, cLineStart)
        }
        return table
    }

    /**
     * Read a csv String (; and " formatted) into an associative array, where
     * the keys are the entries of the first line. All rows must have the same
     * column width. However, this is not checked.
     */
    fun csvToMap (csvString: String?): MutableList<MutableMap<String, String>> {
        if (csvString == null)
            return mutableListOf()
        val table = csvToArray(csvString)
        val list = mutableListOf<MutableMap<String, String>>()
        var header: MutableList<String> = mutableListOf()
        for ((r, rowCsv) in table.withIndex()) {
            if (r == 0) {
                header = rowCsv
            } else {
                val listRow = mutableMapOf<String, String>()
                for((c, entry) in rowCsv.withIndex()) {
                    if (header.elementAtOrNull(c) != null)
                        // never set more fields than in the header
                        listRow[header[c]] = entry
                }
                list.add(listRow)
            }
        }
        return list
    }

    /**
     * encode a single entry to be written to the csv file.
     */
    fun encodeCsvEntry (entry: String?, separator: String = ";"): String {
        if (entry.isNullOrEmpty()) return ""
        // return entry unchanged, if there is no need for quotation.
        if (entry.contains(separator) || entry.contains("\n") || entry.contains("\""))
            // add inner quotes and outer quotes for all others.
            return "\"" + entry.replace("\"", "\"\"") + "\""
        return entry
    }

    /**
     * Write an array to a csv String. table must have an object table.rows[] of
     * which each row holds an array. If (associative) the keys are the first
     * row become the first csv-line column headers, else the first csv-line is
     * written as provided in the first of rows.
     */
    fun encodeCsvTable (tableRows: MutableList<MutableMap<String, String>>): String {
        if (tableRows.isEmpty())
            return ""
        var headline = ""
        var keys = emptyList<String>()
        for (key in tableRows.getOrNull(0)?.keys ?: emptyList()) {
            headline += ";" + encodeCsvEntry(key)
            keys = keys.plus(key)
        }
        var csvString = headline.substring(1)
        for(row in tableRows) {
            var rowString = ""
            for(key in keys)
                rowString += ";" + encodeCsvEntry(row[key])
            csvString += "\n" + rowString.substring(1)
        }
        return csvString
    }

    /**
     * Transform an array of rows into an HTML table. The first row contains the headline.
     */
    fun tableToHtml(table: List<List<String>>, headlineOn: Boolean): String
    {
        // create the layout
        var html = "<table>"
        if (headlineOn) {
            html += "<thead><tr>"
            for (c in 0 ..< (table.getOrNull(0)?.size ?: 0))
                html += "<th>" + table[0][c] + "</th>"
            html += "</tr></thead>"
        }
        html += "<tbody>"
        for (r in 1 ..< table.size) {
            html += "<tr>"
            for (c in 0 ..< (table.getOrNull(r)?.size ?: 0))
                html += "<td>" + table[r].getOrNull(c) + "</td>"  // table[r] is always valid null at this point
            html += "</tr>"
        }
        html += "</tbody></table>"
        return html
    }

    /**
     * Transform an array of rows into a csv table. The first row contains the headline,
     * if $withHeadline == true
     */
    fun tableToCsv(table: List<List<String>>, headlineOn: Boolean): String
    {
        // create the layout
        var csv = ""
        if (headlineOn) {
            for (c in 0 ..< (table.getOrNull(0)?.size ?: 0))
                csv += ";" + encodeCsvEntry(table[0][c])
            csv = "\n" + csv.substring(1)
    }
        for (r in 1 ..< table.size) {
            for (c in 0 ..< (table.getOrNull(r)?.size ?: 0))
                csv += ";" + encodeCsvEntry(table[r].getOrNull(c)) // table[r] is always valid null at this point
            csv = "\n" + csv.substring(1)
        }
        return csv.substring(1)
    }

    /*
    * TRANSCODING the html special characters like in PHP native
    */

    /**
     * Revert the PHP htmlspecialchars() encoding
     */
    fun htmlSpecialCharsDecode(encoded: String): String {
        val entities = mapOf( "amp" to "&", "quot" to "\"", "apos" to "'",
            "#039" to "'", "lt" to "<", "gt" to ">", "nbsp" to " ")
        var plain = encoded
        for (encodedElement in entities.keys) {
            val decodedElement = entities[encodedElement]
            if (decodedElement != null)
                plain = plain.replace("&$encodedElement;", decodedElement)
        }
        return plain
    }
    /**
     * Apply the PHP htmlspecialchars() encoding
     */
    fun htmlSpecialChars(plain: String): String {
        val entities = mapOf( "amp" to "&", "quot" to "\"", "apos" to "'",
            "#039" to "'", "lt" to "<", "gt" to ">", "nbsp" to " ")
        var encoded = plain
        for (encodedElement in entities.keys) {
            val decodedElement = entities[encodedElement]
            if (decodedElement != null)
                encoded = encoded.replace(decodedElement, "&$encodedElement;")
        }
        return encoded
    }
}