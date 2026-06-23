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
 * A utility object to format type values from native memory representation into either
 * a UI readable String or a technical CSV String for API exchange.
 */
class Formatter {

    static language = Language.EN
    static timeZoneOffset = new Date().getTimezoneOffset();

    static setLocale(language, timeZoneOffset) {
        Formatter.language = language
        Formatter.timeZoneOffset = timeZoneOffset
    }

    /* ------------------------------------------------------------------------ */
    /* ----- DATA FORMATTING -------------------------------------------------- */
    /* ----- No errors are documented ----------------------------------------- */
    /* ------------------------------------------------------------------------ */

    /**
     * Format a boolean value for storage in files and the database.
     */
    static #formatBoolean(bool, language = Formatter.language)
    {
        if (language === Language.SQL)
            return (bool) ? "1" : "0"
        if (language === Language.CSV)
            return (bool) ? "true" : "false"
        return (bool) ? "on" : ""
    }

    static #formatInt(int) { return "" + int; }
    static #formatLong(long) { return "" + long; }

    /**
     * Format a floating point value for storage in files and the database.
     */
    static #formatDouble(double, language = Formatter.language) {
        let numberString = "" + double
        if (language.decimalPoint)
            return numberString
        return numberString.replace(".", ",")
    }

    /**
     * Format a date value for storage in files and the database.
     */
    static #formatDate(date, language = Formatter.language) {
        let formatted = ((language === Language.CSV) || (language === Language.SQL)) ?
            date.toISOString().substring(0, 10) :
            date.toLocaleDateString((language === "en") ? "en-GB" : language)
        return (language === Language.SQL) ? "'" + formatted + "'" : formatted
    }

    /**
     * Convert a time integer to HH:MM:SS format. Return the min/max, if beyond the borders.
     */
    static #formatTime(timeInt, language = Formatter.language)
    {
        // split sign and number of seconds
        let sign = (timeInt < 0) ? "-" : ""
        let ti = Math.abs(timeInt);
        // limit number of seconds
        if (ti >= ParserConstraints.TIME_MAX)
            ti = ParserConstraints.TIME_MAX
        if (ti <= ParserConstraints.TIME_MIN)
            ti = Math.abs(ParserConstraints.TIME_MIN)
        // return as integer for SQL. No quotes.
        if (language === Language.SQL)
            return sign + ti
        // return as string else.
        let s = (timeInt % 60).toString().padStart(2, '0')
        let m = ((timeInt / 60) % 60).toString().padStart(2, '0')
        let h = (timeInt / 3600).toString().padStart(2, '0')
        return sign + h + ":" + m + ":" + s;
    }

    /**
     * Format a datetime value for storage in files and the database.
     */
    static #formatDateTime(datetime, language = Formatter.language) {
        let formatted = ((language === Language.CSV) || (language === Language.SQL)) ?
            datetime.toISOString().substring(0, 19).replace("T", " ") :
            datetime.toLocaleDateString((language === "en") ? "en-GB" : language) +
                " " + datetime.toLocaleTimeString("de")
        return (language === Language.SQL) ? "'" + formatted + "'" : formatted

    }

    /**
     * Format a list for storage in files and the database.
     */
    static #formatList (list, language = Formatter.language, parser)
    {
        if (! Array.isArray(list) || (list.length === 0))
            return "[]";
        let formatted = ""
        for (let element of list)
             formatted += ", " + Codec.encodeCsvEntry(Formatter.format(element, parser, language), ",");
        return "[" + formatted.substring(2) + "]";
    }

    /**
     * Format a value for storage in files and the database. Arrays will be formatted as bracketed, comma-separated
     * list (like [a,b,", and c"]). For empty values (see TypeConstraints), an empty String is returned. Null values
     * return an empty String or NULL (Language::SQL) and boolean values "on" and "" for true and false on any but
     * Language::CSV (true or false). For Language::CSV and Language::SQL the appropriate double and single quotes are
     * included.
     */
    static format(value, parser, language = Formatter.language)
    {
        if ((value == null) || (typeof value == 'undefined'))
            return (language === Language.SQL) ? "NULL" : "";
        if (!Parser.isMatchingNative(value, parser)) {
            Findings.addFinding(1, value.toString(), parser.name);
            return Formatter.format(ParserConstraints.empty(parser), parser, language);
        }
        if (ParserConstraints.isEmpty(value, parser))
            return ""
        try {
            switch (parser) {
                case ParserName.BOOLEAN: return Formatter.#formatBoolean(value, language)
                case ParserName.INT: return Formatter.#formatInt(value)
                case ParserName.INT_LIST: return Formatter.#formatList(value, language, ParserName.INT)
                case ParserName.LONG: return Formatter.#formatLong(value)
                case ParserName.DOUBLE: return Formatter.#formatDouble(value, language)
                case ParserName.DATE: return Formatter.#formatDate(value, language)
                case ParserName.DATETIME: return Formatter.#formatDateTime(value, language)
                case ParserName.TIME: return Formatter.#formatTime(value, language)
                case ParserName.STRING: return value
                case ParserName.STRING_LIST: return Formatter.#formatList(value, language, ParserName.STRING)
                case ParserName.NONE:
                default: return ""
            }
        } catch (e) {
            Findings.addFinding(3, value.toString(), parser.name);
            return Formatter.format(ParserConstraints.empty(parser), parser, language);
        }
    }

    // convenience shorthand
    static formatCsv(value, parser) { return Formatter.format(value, parser, Language.CSV) }

    /* ------------------------------------------------------------------------ */
    /* ----- DATA SPECIAL FORMATS --------------------------------------------- */
    /* ------------------------------------------------------------------------ */

    /**
     * Convert a String into an Identifier by replacing forbidden characters by
     * an underscore and cutting the length to 64 characters maximum.
     */
    static toIdentifier (str)
    {
        if (str.length === 0)
            return "_"
        let identifier = ""
        let first = str[0]
        let firstAllowed = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_"
        let subsequentAllowed = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_0123456789"
        if (firstAllowed.indexOf(first) < 0)
            identifier += "_"
        for (let i = 0; i < str.length; i++) {
            if (i < 64) {
                let c = str.charAt(i)
                let d = (subsequentAllowed.indexOf(c) < 0) ?
                    ((c === ' ') ? "_" : "") : c
                identifier += d
            }
        }
        return identifier
    }

    /**
     * Convert a micro time (time as double) int a Date object
     */
    static microTimeToDateTime(microTime) {
        let microTimeLimited = (microTime <= 1.0e11) ? microTime : 1.0E11 - 1
        let ret = new Date()
        ret.setTime(Math.floor(microTimeLimited * 1000))
        return ret
    }

    /**
     * Convert micro time float to a datetime String
     */
    static microTimeToString (microTime, language=  Formatter.language) {
        return Formatter.format(Formatter.microTimeToDateTime(microTime), ParserName.DATETIME, language)
    }

    /**
     * Note: in Javascript the replace function only replaces the very first occurrence, unless called with a regex
     */
    static escapeHtml (text) {
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/'/g, "&quot;").replace(/"/g, "&#039;")
    }

    /**
     * Format a string by replacing ,* by &lt;b&gt;, ,/ by &lt;i&gt;, ,_ by &lt;u&gt;, .- by &lt;s&gt;,
     * ,^ by &lt;sup&gt;, ,, by &lt;sub&gt;, and ,# by &lt;code&gt;. The next following occurrence of ,. will
     * close the respective tag. The new line character \n is replaced by &lt;br&gt;.
     */
    static styleToHtml(styled) {
        let styledHtml = "";
        let tagMap = { "*": "b", "/": "i", "_": "u", "-": "s", "^": "sup", ",": "sub", "#": "code" }
        let c1 = styled.substring(0, 1)
        let tag = ""
        let i = 1
        while (i < styled.length) {
            let c2 = styled.substring(i, i + 1)
            if ((c1 === ",") && (typeof tagMap[c2] !== "undefined")) {
                // open tag
                let openTag = tagMap[c2]
                if (openTag) {
                    styledHtml += "<" + openTag + ">"
                    tag = openTag
                    c2 = styled.substring(i++, i + 1) // tags replace two characters
                }
                // close tag
                else if ((c2 === ".") && (tag.length > 0)) {
                    styledHtml += "</" + tag + ">"
                    tag = ""
                    c2 = styled.substring(i++, i + 1) // tags replace two characters
                }
            } else
                styledHtml += c1
            c1 = c2
            i++
        }
        styledHtml += c1
        return styledHtml
    }
}