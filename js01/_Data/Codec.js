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

class Codec {

    /*
     * API encoding
     */
    static apiEncode(plain) {
        return atob(plain)
            .replace("+", "-")
            .replace("/", "_")
            .replace("=", ".")
    }

    /*
     * API decoding
     */
    static apiDecode(encoded) {
        return this.base64apiToUtf8(encoded)
    }

    /**
     * base64 String to byte decoding. See Daniel Guerrero:
     * http://blog.danguer.com/2011/10/24/base64-binary-decoding-in-javascript/
     * Adapted to use *-_ instead of +/=, removed superfluous parts. Corrected
     * dangling end byte error.
     *
     * Only implemented in JavaScript, not in PHP, or kotlin/Java.
     */
    static #base64apiToUint8Array (input) {
        // remove all irrelevant characters ('\n', ' ') asf.
        // Note: this is the API-type base 64 using -_. instead of +/=
        input = input.replace(/[^A-Za-z0-9\-_.]/g, "");
        // calculate output size
        let bytes = input.length / 4 * 3;
        // remove padding ('.' instead of '=')
        while (input.substring(input.length - 1).localeCompare(".") === 0) {
            input = input.substring(0, input.length - 1);
            bytes --;
        }
        // prepare decoding
        let uarray = new Uint8Array(bytes);
        let chr1, chr2, chr3;
        let enc1, enc2, enc3, enc4;
        let i;
        let j = 0;
        // Note: this is the API-type base 64 using -_. instead of +/= , see at the end.
        let keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_.";
        // decode
        for (i=0; i<bytes; i+=3) {
            // get the 3 octets in 4 ascii chars
            enc1 = keyStr.indexOf(input.charAt(j++));
            enc2 = keyStr.indexOf(input.charAt(j++));
            enc3 = keyStr.indexOf(input.charAt(j++));
            enc4 = keyStr.indexOf(input.charAt(j++));
            if ((enc1 < 0) || (enc2 < 0) || (enc3 < 0) || (enc3 < 0))
                enc1 = 0;
            chr1 = (enc1 << 2) | (enc2 >> 4);
            chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
            chr3 = ((enc3 & 3) << 6) | enc4;
            uarray[i] = chr1;
            if (enc3 !== 64) uarray[i+1] = chr2;
            if (enc4 !== 64) uarray[i+2] = chr3;
        }
        return uarray;
    }
    /**
     * Source (adapted): utf.js - UTF-8 <=> UTF-16 conversion.
     * http://www.onicos.com/staff/iz/amuse/javascript/expert/utf.txt Copyright
     * (C) 1999 Masanao Izumo <iz@onicos.co.jp> Version: 1.0 LastModified: Dec
     * 25 1999 This library is free. You can redistribute it and/or modify it.
     * window.atob will exit with an error when using UTF-8 encoding.
     *
     * Only implemented in JavaScript, not in PHP, or kotlin/Java.
     */
    static base64apiToUtf8 (base64String) {
        let uint8Array = Codec.#base64apiToUint8Array(base64String);
        let c, char2, char3;
        let out = "";
        let len = uint8Array.length;
        let i = 0;
        while (i < len) {
            c = uint8Array[i++];
            switch (c >> 4)
            {
                case 0: case 1: case 2: case 3: case 4: case 5: case 6: case 7:
                // 0xxxxxxx
                out += String.fromCharCode(c);
                break;
                case 12: case 13:
                // 110x xxxx 10xx xxxx
                char2 = uint8Array[i++];
                out += String.fromCharCode(((c & 0x1F) << 6) | (char2 & 0x3F));
                break;
                case 14:
                    // 1110 xxxx 10xx xxxx 10xx xxxx
                    char2 = uint8Array[i++];
                    char3 = uint8Array[i++];
                    out += String.fromCharCode(((c & 0x0F) << 12) | ((char2 & 0x3F) << 6) | ((char3 & 0x3F) << 0));
                    break;
            }
        }
        return out;
    }

    /*
     * TRANSCODING FROM AND TO CSV
     */
    /**
     * return the character position of the next line end. Skip line breaks in
     * between double quotes. Return the length of csvString if there is no more
     * line break.
     */
    static #nextCsvLineEnd (csvString, cLineStart) {
        let nextLinebreak = csvString.indexOf('\n', cLineStart);
        let nextDoubleQuote = csvString.indexOf('"', cLineStart);
        let doubleQuotesPassed = 0;
        while (((nextDoubleQuote >= 0) && (nextDoubleQuote < nextLinebreak))
        || (doubleQuotesPassed % 2 === 1)) {
            doubleQuotesPassed++;
            nextLinebreak = csvString.indexOf('\n', nextDoubleQuote);
            nextDoubleQuote = csvString.indexOf('"', nextDoubleQuote + 1);
        }
        return (nextLinebreak === -1) ? csvString.length : nextLinebreak;
    }

    /**
     * Split a csv-formatted line into an array.
     */
    static splitCsvRow (line, separator = ";") {
        // split entries by parsing the String, it may contain quoted elements.
        let entries = [];
        if (! line)
            return entries;
        let entryStartPos = 0;

        while (entryStartPos < line.length) {
            // trim start if blank chars precede a '"' character
            while ((entryStartPos < line.length) && (line.charAt(entryStartPos) === ' '))
                entryStartPos++;
            // Check for quotation
            let entryEndPos = entryStartPos;
            let quoted = false;
            // while loop to jump over twin double quotes
            while ((entryEndPos < line.length) && (line.charAt(entryEndPos) === '"')) {
                quoted = true;
                // Put pointer to first character after next double quote.
                entryEndPos = line.indexOf('"', entryEndPos + 1) + 1;
            }
            entryEndPos = line.indexOf(separator, entryEndPos);
            if (entryEndPos < 0)
                entryEndPos = line.length;
            let entry = line.substring(entryStartPos, entryEndPos);
            if (quoted) {
                // remove opening and closing double quotes.
                let entryToParse = entry.substring(1, entry.length - 1);
                // replace all inner twin double quotes by single double quotes
                let nextSnippetStart = 0;
                let nextDoubleQuote = entryToParse.indexOf('""',
                    nextSnippetStart);
                entry = "";
                while (nextDoubleQuote >= 0) {
                    // add the segment to the next twin double quote and the
                    // first double quote in it
                    entry += entryToParse.substring(nextSnippetStart,
                        nextDoubleQuote + 1);
                    nextSnippetStart = nextDoubleQuote + 2;
                    // continue search after the second of the twin double quotes
                    nextDoubleQuote = entryToParse.indexOf('""',
                        nextSnippetStart);
                }
                // add last segment (or full entry if there are no twin
                // double quotes
                entry += entryToParse.substring(nextSnippetStart);
            }
            entries.push(entry)
            entryStartPos = entryEndPos + 1;
            // if the line ends with a separator char, add an empty entry.
            if (entryStartPos === line.length)
                entries.push("")
        }
        return entries;
    }
    /**
     * Join an array of Strings into a row.
     */
    static joinCsvRow (row, separator = ";") {
        let joined = ""
        for (let entry of row)
            joined += separator + Codec.encodeCsvEntry(entry, separator)
        return (joined) ? joined.substring(1) : ""
    }

    /**
     * Read a csv String (; and " formatted) into an array[rows][columns]. It is
     * not checked whether all rows have the same column width. This is plain
     * text parsing.
     */
    static csvToArray (csvString) {
        if (!csvString)
            return [];
        let table = [];
        let cLineStart = 0;
        let cLineEnd = Codec.#nextCsvLineEnd(csvString,
            cLineStart);
        while (cLineEnd > cLineStart) {
            let line = csvString.substring(cLineStart, cLineEnd);
            let entries = Codec.splitCsvRow(line);
            table.push(entries);
            cLineStart = cLineEnd + 1;
            cLineEnd = Codec.#nextCsvLineEnd(csvString,
                cLineStart);
        }
        return table;
    }

    // no method csv-file to map in JavaScript, only PHP.

    /**
     * Read a csv String (; and " formatted) into an associative array, where
     * the keys are the entries of the first line. All rows must have the same
     * column width. However, this is not checked.
     */
    static csvToMap (csvString) {
        if (!csvString)
            return [];
        let table = Codec.csvToArray(csvString);
        let list = [];
        let r = 0;
        let w = 0;
        let header = [];
        for (let rowCsv of table) {
            if (r === 0) {
                w = rowCsv.length;
                header = rowCsv;
            } else {
                let listRow = {};
                let c = 0;
                for(let entry of rowCsv) {
                    if (header[c])
                        // never set more fields than in the header
                        listRow[header[c]] = entry;
                    c++;
                }
                list.push(listRow);
            }
            r++;
        }
        return list;
    }

    /**
     * encode a single entry to be written to the csv file.
     */
    static encodeCsvEntry (entry, separator = ";") {
        // return numbers unchanged
        if ((entry == null) || (entry.length === 0))
            return "";
        // return entry unchanged if there is no need for quotation.
        if ((entry.indexOf(separator) >= 0) || (entry.indexOf("\n") >= 0)
            || (entry.indexOf("\"") >= 0))
            // add inner quotes and outer quotes for all other entries.
            return "\"" + entry.replace(/"/g, "\"\"") + "\"";
        return entry;
    }

    /**
     * Write an array to a csv String. tableMaps must be an array of rows of
     * which each row holds a map of key/value pairs, the values being formatted.
     */
    static encodeCsvTable (tableRows) {
        if (tableRows.length === 0)
            return ""
        let headline = "";
        let keys = []
        for (let key in tableRows[0]) {
            headline += ";" + Codec.encodeCsvEntry(key)
            keys.push(key);
        }
        let csvString = headline.substring(1);
        for(let row of tableRows) {
            let rowString = ""
            for(let key of keys)
                rowString += ";" + Codec.encodeCsvEntry(row[key])
            csvString += "\n" + rowString.substring(1);
        }
        return csvString;
    }

    /**
     * Transform an array of rows into an HTML table. The first row contains the headline.
     */
    static tableToHtml(table, headlineOn)
    {
        // create the layout
        let html = "<table>";
        if (headlineOn) {
            html += "<thead><tr>"
            for (let c = 0; c < table[0].length; c++)
            html += "<th>" + table[0][c] + "</th>"
            html += "</tr></thead>"
        }
        html += "<tbody>"
        for (let r = 1; r < table.length; r++) {
            html += "<tr>"
            for (let c = 0; c < table[r].length; c++)
                html += "<td>" + table[r][c] + "</td>"
            html += "</tr>"
        }
        html += "</tbody></table>"
        return html
    }

    /**
     * Transform an array of rows into a csv table. The first row contains the headline,
     * if $withHeadline == true
     */
    static tableToCsv(table, headlineOn)
    {
        // create the layout
        let csv = "";
        if (headlineOn) {
            for (let c = 0; c < table[0].length; c++)
            csv += ";" + Codec.encodeCsvEntry(table[0][c])
            csv = "\n" + csv.substring(1)
        }
        for (let r = 1; r < table.length; r++) {
            for (let c = 0; c < table[r].length; c++)
                csv += ";" + Codec.encodeCsvEntry(table[r][c])
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
    static htmlSpecialCharsDecode(encoded) {
        let entities = [ ['amp', '&'], ['quot', '"'], ['apos', '\''],
            ['#039', '\''], ['lt', '<'], ['gt', '>'], ['nbsp', ' '] ];
        let plain = encoded
        for (let i = 0; i < entities.length; ++i)
            plain = plain.replace(new RegExp('&' + entities[i][0] + ';', 'g'), entities[i][1]);
        return plain;
    }
    /**
     * Apply the PHP htmlspecialchars() encoding
     */
    static htmlSpecialChars(plain) {
        let entities = [ ['amp', '&'], ['quot', '"'], ['apos', '\''],
            ['#039', '\''], ['lt', '<'], ['gt', '>'], ['nbsp', ' '] ];
        let encoded = plain
        for (let i = 0; i < entities.length; ++i)
            encoded = encoded.replace(new RegExp(entities[i][1], 'g'), '&' + entities[i][0] + ';');
        return encoded;
    }

}