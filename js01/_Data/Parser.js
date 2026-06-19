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
 * Provide the utility to transcode a CSV-encoded String as received from the API or a UI input
 * into a typed value.
 */
class Parser {

    static #language = Language.EN
    static #timeZoneOffset = new Date().getTimezoneOffset();

    static setLocale(language, timeZoneOffset) {
        Parser.#language = language
        Parser.#timeZoneOffset = timeZoneOffset
    }

    /* ------------------------------------------------------------------------ */
    /* ----- DATE AND DATETIME CLEANSING -------------------------------------- */
    /* ----- Using some heuristic to add missing bits ------------------------- */
    /* ------------------------------------------------------------------------ */

    /**
     * Add leading zeros before the number to get the "len" expected. If
     * n.toString.length >= len, the number is converted to a String and not
     * changed.
     */
    static #padZeros (n, len) {
        let padded = "" + n;
        while (padded.length < len) padded = "0" + padded;
        return padded;
    }

    /**
     * Cleanse a date string into the YYYY-MM-DD format. If a single integer is passed, it is taken as a year
     * and the 1st of January added. If two numbers are detected, they are taken as month and day
     * and the current year added.
     */
    static #cleanseDate(dateString, language){
        // an empty String shall be an empty date
        if (dateString.length === 0)
            return ""
        // parse the string to filter day, month and year
        let dateTemplate = Language.settingsOf(language).dateTemplate
        let parts = dateString.split(dateTemplate[1])

        // the result may not match if the format is not according to the language expected.
        if ((parts.length === 1) && !isNaN(parseFloat(parts[0]))) {
            if (dateString.split("-").length === 3)
                // assume ISO formatting, typically a result of a form entry
                dateTemplate = Language.settingsOf(Language.CSV).dateTemplate;
            else if (dateString.split(".").length === 3)
                // assume DE formatting
                dateTemplate = Language.settingsOf(Language.DE).dateTemplate;
            else if (dateString.split("/").length === 3)
                // assume EN formatting
                dateTemplate = Language.settingsOf(Language.EN).dateTemplate;
            parts = dateString.split(dateTemplate[1])
        }
        // If a DateTime ist provided instead of a date, cut the time off of the last element
        if ((parts.length === 3) && (parts[2].indexOf(" ") > 0))
            parts[2] = parts[2].split(" ")[0]
        // convert to Integer
        let partsInt = []
        for (let part of parts) {
            let i = parseInt(part);
            if (isNaN(i))
                partsInt.push(0);
            else
                partsInt.push(i);
        }

        // if there is just one value, assume it to be the year, if > 31
        // else to be the day of the month and add month and year
        let now = new Date()
        let lastDayOfMonth;
        let month = now.getMonth() + 1
        switch (month) {
            case 4:
            case 6:
            case 9:
            case 11: lastDayOfMonth = 30; break;
            case 2: lastDayOfMonth = 28; break;  // no leap year support in date autocompletion.
            default: lastDayOfMonth = 31;
        }
        if (parts.length === 1) {
            if ((partsInt[0] > 1000) && (partsInt[0] < 2999))
                // a four-digit integer in the date range is taken to be a year. Add the first of Jánuary
                return dateString + "-01-01";
            else if ((partsInt[0] >= 1) && (partsInt[0] <= lastDayOfMonth)) {
                // an integer in the day of month range is taken ro be the actual month's day
                return now.year + "-" + Parser.#padZeros(month, 2) + "-" + Parser.#padZeros(partsInt[0], 2)
            } else {
                // any other value is regarded as an error
                Findings.addFinding(1, dateString)
                return null
            }
        }

        let yearIsFirst = (dateTemplate.toLowerCase().startsWith("y"))
        // if just two integers were detected, assume that the year is missing and add the
        // current year
        let y, m = 1, d = 1
        if ((parts.length === 2) || ((parts.length === 3) && (parts[2].length === 0))) {
            y = now.year;
            m = (yearIsFirst) ? partsInt[0] : partsInt[1];
            d = (yearIsFirst) ? partsInt[1] : partsInt[0];
            if ((m >= 1) && (m <= 12) && (d >= 1) && (d <= lastDayOfMonth))
            // verify the date
            if (isNaN(new Date(y, m - 1, d).valueOf())) {
                Findings.addFinding(1, dateString)
                return null
            }
            return Parser.#padZeros(y, 4) + "-" + Parser.#padZeros(m,2) + "-" + Parser.#padZeros(d, 2)
        }

        // three numbers are given
        // if all are lower than 100, extend the year by a heuristic guess
        y = (yearIsFirst) ? partsInt[0] : partsInt[2];
        if (y < 100) {
            // extend two digits. Get the century
            let yearNow2Digit = now.year % 100
            let centuryNow = now.year - yearNow2Digit
            let centuryNext = centuryNow + 100
            let centuryPrevious = centuryNow - 100
            // apply heuristics: go 90 years back to 10 years forward
            y = (yearNow2Digit < 90) ?
                    ((y > (yearNow2Digit + 10)) ? (centuryPrevious + y) : (centuryNow + y)) :
                    ((y > (yearNow2Digit + 10) % 100) ? (centuryNow + y) : (centuryNext + y))
        }
        // try to build a date, causes an exception if invalid.
        // verify the date
        if (isNaN(new Date(y, m - 1, d).valueOf())) {
            Findings.addFinding(1, dateString)
            return null
        }
        return Parser.#padZeros(y, 4) + "-" + Parser.#padZeros(m,2) + "-" + Parser.#padZeros(d, 2)
    }

    /**
     * Cleanse a time string to HH:MM:SS format. Milliseconds are dropped.
     */
    static #cleanseTime(timeString, noHours) {
        if (timeString.length < 2)
            return null
        // split off the "minus", if existing.
        let sign = ""
        let times = timeString
        if (times[0] === '-') {
            times = times.substring(1).trim()
            sign = "-"
        }
        // cleanse the remainder
        let hms = times.split(":")
        if ((hms.size < 2) || (hms.size > 3))
            return null
        let hmsInt = []
        for (let part in hms)
            hmsInt.push(isNaN(parseInt(part)) ? 0 : parseInt(part));
        let hms0 = Parser.#padZeros(hmsInt[0], 2)
        let hms1 = Parser.#padZeros(hmsInt[1], 2)
        if (hms.length === 2)
            return (noHours) ? sign + "00:" + hms0 +":" + hms1 : sign + hms0 + ":" + hms1 + ":00";
        let hms2 = Parser.#padZeros(hmsInt[2], 2)
        return sign + hms0 + ":" + hms1 + ":" + hms2;
    }

    /**
     * Cleanse a datetime string to YYYY-MM-DD HH:MM:SS format. Milliseconds are
     * dropped. If no date is given, insert the current date. If no time is
     * given, insert the current time.
     */
    static #cleanseDateTime(datetimeString, language){
        let dt = datetimeString.trim().split(" ")
        if (dt.length === 1) {
            // try both, date or time
            let date = Parser.#cleanseDate(dt[0], language)
            let time = Parser.#cleanseTime(dt[0], false) // always with hours
            if (date != null)
                return date + " 00:00:00";
            else if (time != null) {
                let dtNow = new Date()
                let dateNow = Parser.#padZeros(dtNow.year, 4) + "-" + Parser.#padZeros(dtNow.month + 1,2)
                return dateNow + "-" + Parser.#padZeros(dtNow.getDate(), 2)
        } else {
            Findings.addFinding(1, datetimeString.trim())
            return null
        }
    } else {
        let date = Parser.#cleanseDate(dt[0], language)
        let time = Parser.#cleanseTime(dt[1], false) // always with hours
        if ((date == null) || (time == null)) {
            Findings.addFinding(1, datetimeString.trim())
            return null
        }
        return date + " " + time;
    }
}

    /**
     * Parse a value from storage or the database for processing. Array values must start and end with square brackets
     * and comma separated (,), quoting is needed (like [a,b,", and c"]). Empty Strings are parsed into empty values
     * (see TypeConstraints) or empty Lists. For Language::SQL the String NULL without quotes is also parsed into an empty value.
     * Boolean values will be true for any non-empty String except the String "false" (not case-sensitive) and the String
     * "0". For Languages .CSV and .SQL quoted Strings are unquoted before parsing. The function never returns null.
     * If the value is not a string but matches the target native type of the parser, it is returned unchanged.
     */
    static parse(value, parser, language = Parser.#language) {
        // remove quotes, if existing.
        if (typeof value != "string") {
            if (Parser.isMatchingNative(value, parser))
                return value;
            let valueForError = value.toString();
            Findings.addFinding(3, valueForError, typeof value);
            return ParserConstraints.empty(parser);
        }
        let toParse = value
        if ((language === Language.CSV)
            && value.startsWith( "\"") && value.endsWith("\""))
            toParse = value.substring(1, value.length - 1)
                .replace("\"\"", "\"").trim()
        else if (language === Language.SQL) {
            if (value.startsWith( "'") && value.endsWith("'"))
                toParse = value.substring(1, value.length - 1)
                    .replace("\\'", "'").trim()
            else
            if (value.toLowerCase() === "null")
                // Special case: unquoted NULL for Language.SQL
                return ParserConstraints.empty(parser)
        }
        // parse value
        switch (parser) {
            case ParserName.BOOLEAN: return Parser.#parseBoolean(toParse)
            case ParserName.INT: return Parser.#parseInt(toParse, language)
            case ParserName.INT_LIST: return Parser.#parseList(toParse, language, ParserName.INT)
            case ParserName.LONG: return Parser.#parseLong(toParse, language)
            case ParserName.DOUBLE: return Parser.#parseDouble(toParse, language)
            case ParserName.DATE: return Parser.#parseDate(toParse, language)
            case ParserName.DATETIME: return Parser.#parseDateTime(toParse, language)
            case ParserName.TIME: return Parser.#parseTime(toParse)
            case ParserName.STRING: return value
            case ParserName.STRING_LIST: return Parser.#parseList(toParse, language, ParserName.STRING)
            case ParserName.NONE: return ""
            default: return ""
        }
    }

    /**
     * Convert a String to boolean. Returns false if bool_string = "FALSE" or
     * bool_string = "false", else this will return true. Note that null or
     * undefined are also converted to false, see #parseSingle().
     */
    static #parseBoolean(boolString) {
        return ((boolString.length > 0) && (boolString.toLowerCase() !== "false")
            && (boolString !== "0"))
    }

    /**
     * Convert a not-empty String to an integer number. If parsing fails, this will
     * return Constraints.empty(Name.LONG).
     */
    static #parseLong(longString, language = Parser.#language) {
        if (longString.length === 0)
            return ParserConstraints.empty(ParserName.LONG)
        let toParse = longString.trim().replace(" ", "")
        toParse =  (language.decimalPoint) ?
            toParse.replace(",", "") :
            toParse.replace(".", "")
        if (toParse.length === 0)
            return ParserConstraints.empty(ParserName.LONG)
        let ret = parseInt(toParse)
        return (isNaN(ret)) ? ParserConstraints.empty(ParserName.LONG) : ret
    }

    /**
     * Convert a not-empty String to an integer number. If parsing fails, this will
     * return Constraints.empty(Name.INT). If parsing results into an integer outside the Int range,
     * this will return the respective range limit
     */
    static #parseInt(intString, language = Parser.#language) {
        if (intString.length === 0)
            return ParserConstraints.empty(ParserName.INT)
        let long = Parser.#parseLong(intString, language)
        if (long === ParserConstraints.empty(ParserName.LONG))
            return ParserConstraints.empty(ParserName.INT)
        if (long < ParserConstraints.min(ParserName.INT))
            return ParserConstraints.min(ParserName.INT)
        if (long > ParserConstraints.max(ParserName.INT))
            return ParserConstraints.max(ParserName.INT)
        return long
    }

    /**
     * Convert a not-empty String to a number. In case of errors, this will be return
     * Constraints.empty(Name.DOUBLE)
     */
    static #parseDouble(floatString, language = Parser.#language) {
        let toParse = floatString.trim().replace(" ", "")
        toParse = (language.decimalPoint) ?
            toParse.replace(",", "") :
            toParse.replace(".", "").replace(",", ".")
        if (toParse.length === 0)
            return ParserConstraints.empty(ParserName.LONG)
        let ret = parseFloat(toParse)
        return (isNaN(ret)) ? ParserConstraints.empty(ParserName.DOUBLE) : ret
    }

    /**
     * Convert a String to a number of seconds. no limits to the number of hours
     * apply. In case of errors, this will be return Constraints.empty(Name.TIME)
     */
    static #parseTime(timeString, noHours = false) {
        let cleansed = Parser.#cleanseTime(timeString, noHours)
        if (cleansed == null) {
            Findings.addFinding(2, timeString)
            return ParserConstraints.empty(ParserName.TIME)
        }
        let sign = (timeString[0] === '-') ? -1 : 1;
        let hms = cleansed.split(":")
        let hour = parseInt(hms[0])
        let minute = parseInt(hms[1])
        let second = parseInt(hms[2])
        return sign * (hour * 3600 + minute * 60 + second)
    }

    /**
     * Convert a String to a Date. If the year is two digits only, It will be assumed to be in the
     * range of this year -89 years ... +10 years. In case of errors, this will return
     * Constraints.empty(Name.DATE)
     */
    static #parseDate(dateString, language = Parser.#language) {
        // cleanse the date. THis will return a CSV formatted String or null
        let dateCleansed = Parser.#cleanseDate(dateString, language)
        if (dateCleansed == null)
            return ParserConstraints.empty(ParserName.DATE)
        let ret = new Date(dateCleansed)
        if (isNaN(ret.valueOf())){
            Findings.addFinding(2, dateString)
            return ParserConstraints.empty(ParserName.DATE)
        }
        return ret
    }

    /**
     * Convert a datetime String to a DateTimeImmutable Object. If no time is given, the current
     * time is inserted. In case of errors, this will return Constraints.empty(Name.DATETIME)
     */
    static #parseDateTime(datetimeString, language = Parser.#language) {
        // cleanse the datetime. This will return a CSV formatted String or null
        let dateTimeCleansed = Parser.#cleanseDateTime(datetimeString, language)
        if (dateTimeCleansed == null)
            return ParserConstraints.empty(ParserName.DATETIME)
        // parse the cleansed datetime
        let ret = new Date(dateTimeCleansed)
        if (isNaN(ret.valueOf())){
            Findings.addFinding(2, datetimeString)
            return ParserConstraints.empty(ParserName.DATETIME)
        }
        return ret
    }

    /**
     * Convert a String with a List of Integer like [1,2,3,4] or Strings like [a,"b,c",d] (3 elements!) into an array
     * by parsing all values. Empty Strings return an empty array. If the brackets are missing, the $value is simply
     * split along all commas into an array. Empty elements result in Constraints.empty($singleParser).
     */
    static #parseList(value, language = Parser.#language, parser) {
        let parsed = []
        if (value === "")
            return parsed
        if (value.startsWith("[") && value.endsWith("]"))
            value = value.substring(1, value.length - 1).trim()
        let values = Codec.splitCsvRow(value, ",")
        if (parser === ParserName.INT) {
            for (let v of values)
                parsed.push(Parser.#parseInt(v.trim(), language))
        } else {
            for (let v of values)
                parsed.push(v.trim())
        }
        return parsed
    }

    /**
     * Get the best matching parser for a native value of unknown Type
     */
    static nativeToParser(value) {
        if (Array.isArray(value)) {
            if ((value.length === 0) || (typeof value[0] != "number"))
                return ParserName.STRING_LIST
            else
                return ParserName.INT_LIST
        }
        switch (typeof value) {
            case "boolean":
                return ParserName.BOOLEAN;
            case "bigint":
                return ParserName.LONG;
            case "number":
                return ParserName.DOUBLE;
            case "string":
                return ParserName.STRING;
            case "object":
                return (Object.prototype.toString.call(value) === "Date") ? ParserName.DATETIME : ParserName.NONE;
            default:
                return ParserName.NONE
        }
    }

    /**
     * returns true if the native type of $value matches the ParserName requirements
     */
    static isMatchingNative(value, parserName) {
        switch (parserName) {
            case ParserName.BOOLEAN:
                return (typeof value == "boolean");
            case ParserName.TIME:
            case ParserName.LONG:
            case ParserName.DOUBLE:
            case ParserName.INT:
                return (typeof value == "number");
            case ParserName.INT_LIST:
                return (Array.isArray(value)) && ((value.length === 0) || (typeof value[0] == "number"));
            case ParserName.DATE:
            case ParserName.DATETIME:
                return (typeof value == "object") && (value instanceof Date);
            case ParserName.STRING:
                return (typeof value == "string");
            case ParserName.STRING_LIST:
                return (Array.isArray(value)) && ((value.length === 0) || (typeof value[0] == "string"));
            case ParserName.NONE:
                return true
        }
    }

}