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

// Localization information
// The common list of parsers
import org.tfyh.util.Language
import kotlin.math.abs

/**
 * A utility object to format type values from native memory representation into either
 * a UI readable String or a technical CSV String for API exchange.
 */
@Suppress("Unused")
object Formatter {

    /* ------------------------------------------------------------------------ */
    /* ----- DATA FORMATTING -------------------------------------------------- */
    /* ----- No errors are documented ----------------------------------------- */
    /* ------------------------------------------------------------------------ */

    /**
     * Format a boolean value for storage in files and the database.
     */
    private fun formatBoolean(bool: Boolean, language: Language): String
    {
        if (language == Language.SQL)
            return if (bool) "1" else "0"
        if (language == Language.CSV)
            return if (bool) "true" else "false"
        return if (bool) "on" else ""
    }

    private fun formatInt(int: Int): String = int.toString()
    private fun formatLong(long: Long): String = long.toString()

    /**
     * Format a floating point value for storage in files and the database.
     */
    private fun formatDouble(double: Double, language: Language): String
    {
        val numberString = double.toString()
        if (language.decimalPoint)
            return numberString
        return numberString.replace(".", ",")
    }

    /**
     * Format a date value for storage in files and the database.
     */
    private fun formatDate(date: NoZoneDateTime, language: Language)
        = date.toDateString(language)

    /**
     * Convert a time integer to HH:MM:SS format. Return the min/max, if beyond the borders.
     */
    private fun formatTime(timeInt: Int, language: Language): String
    {
        // split sign and amount of seconds
        val sign = if (timeInt < 0) "-" else ""
        var ti = abs(timeInt)
        // limit amount of seconds
        if (ti >= ParserConstraints.TIME_MAX)
            ti = ParserConstraints.TIME_MAX
        if (ti <= ParserConstraints.TIME_MIN)
            ti = abs(ParserConstraints.TIME_MIN)
        // return as integer for SQL. No quotes.
        if (language == Language.SQL)
            return sign + ti
        // return as string else.
        val s = (timeInt % 60).toString().padStart(2, '0')
        val m = ((timeInt / 60) % 60).toString().padStart(2, '0')
        val h = (timeInt / 3600).toString().padStart(2, '0')
        return "$sign$h:$m:$s"
    }

    /**
     * Format a datetime value for storage in files and the database.
     */
    private fun formatDateTime(datetime: NoZoneDateTime, language: Language):
            String = datetime.toDateTimeString(language)

    /**
     * Format a string list for storage in files and the database.
     */
    private fun formatList (list: MutableList<*>, language: Language, parser: ParserName): String
    {
        if (list.isEmpty()) return "[]"
        var formatted = ""
        for (element in list)
            formatted += when (element) {
                is Int ->  ", " + format(element, parser, language)
                is String ->  ", " + Codec.encodeCsvEntry(element, ",")
                else -> ", "
            }
        return "[" + formatted.substring(2) + "]"
    }

    /**
     * Format a value for storage in files and the database. Arrays will be formatted as bracketed, comma-separated
     * list (like [a,b,", and c"]). For empty values (see TypeConstraints) an empty String is returned. Null values
     * return an empty String or NULL (Language::SQL) and boolean values "on" and "" for true and false on any but
     * Language::CSV (true or false). For Language::CSV and Language::SQL the appropriate double and single quotes are
     * included.
     */
    fun format(value: Any, parser: ParserName, language: Language): String
    {
        if (!Parser.isMatchingNative(value, parser)) {
            Findings.addFinding(1, value.toString(), parser.name)
            return format(ParserConstraints.empty(parser), parser, language)
        }
        if (ParserConstraints.isEmpty(value, parser))
            return ""
        return try {
            when (parser) {
                ParserName.BOOLEAN -> formatBoolean(value as Boolean, language)
                ParserName.INT -> formatInt(value as Int)
                ParserName.INT_LIST -> formatList(value as MutableList<*>, language, ParserName.INT)
                ParserName.LONG -> formatLong(value as Long)
                ParserName.DOUBLE -> formatDouble(value as Double, language)
                ParserName.DATE -> formatDate(value as NoZoneDateTime, language)
                ParserName.DATETIME -> formatDateTime(value as NoZoneDateTime, language)
                ParserName.TIME -> formatTime(value as Int, language)
                ParserName.STRING -> value as String
                ParserName.STRING_LIST -> formatList(value as MutableList<*>, language, ParserName.STRING)
                ParserName.NONE -> ""
            }
        } catch (_: Exception) {
            Findings.addFinding(3, value.toString())
            format(ParserConstraints.empty(parser), parser, language)
        }
    }

    // convenience shorthand
    fun formatCsv(value: Any, parser: ParserName) = format(value, parser, Language.CSV)

    /* ------------------------------------------------------------------------ */
    /* ----- DATA SPECIAL FORMATS --------------------------------------------- */
    /* ------------------------------------------------------------------------ */

    /**
     * Convert a String into an Identifier by replacing forbidden characters by
     * an underscore and cutting the length to 64 characters maximum.
     */
    fun toIdentifier (str: String): String
    {
        if (str.isEmpty())
            return "_"
        var identifier = ""
        val first = str[0]
        val firstAllowed = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_"
        val subsequentAllowed = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_0123456789"
        if (firstAllowed.indexOf(first) < 0)
            identifier += "_"
        for (i in str.indices) {
            if (i < 64) {
                val c = str[i]
                val d: String = if (subsequentAllowed.indexOf(c) < 0) {
                    if (c == ' ') "_"
                    else ""
                } else c.toString()
                identifier += d
            }
        }
        return identifier
    }

    /**
     * Convert a micro time (time as double) int a date time string
     */
    private fun microTimeToDateTime(microTime: Double): NoZoneDateTime {
        val microTimeLimited = if (microTime <= 1.0e11) microTime else 1.0E11 - 1
        val epochMins = (microTimeLimited / 60000).toInt()
        val micros = microTimeLimited - epochMins * 60000
        return NoZoneDateTime(NoZoneDateTime.epochToDateTimeString(epochMins, micros.toInt()))
    }

    /**
     * Convert micro time float to a datetime String
     */
    fun microTimeToString (microTime: Double, language: Language) = if (microTime == 0.0)
        format(NoZoneDateTime.now(), ParserName.DATETIME, language)
        else format(microTimeToDateTime(microTime), ParserName.DATETIME, language)

    /**
     * see
     * https://stackoverflow.com/questions/1787322/htmlspecialchars-equivalent-in-javascript
     */
    fun escapeHtml (text: String): String {
        // map to run the html-escape function
        val escapeHtmlMap = mapOf(
            "&" to "&amp;",
            "<" to "&lt;",
            ">" to "&gt;",
            "'" to "&quot;",
            "\"" to "&#039;"
        )
        // replace the html reserved characters
        var ret = text
        for (pair in escapeHtmlMap)
            ret = ret.replace(pair.key, pair.value)
        return ret
    }

    /**
     * Format a string by replacing ,* by &lt;b&gt;, ,/ by &lt;i&gt;, ,_ by &lt;u&gt;, .- by &lt;s&gt;
     * ,^ by &lt;sup&gt;, ,, by &lt;sub&gt;, and ,# by &lt;code&gt;. The next following occurrence of ',.' will
     * close the respective tag. The new line character \n is replaced by &lt;br&gt;.
     */
    fun styleToHtml(styled: String): String {
        var styledHtml = ""
        val tagMap = mapOf( "*" to "b", "/" to "i", "_" to "u", "-" to "s", "^" to "sup", "," to "sub", "#" to "code")
        var c1 = styled.substring(0, 1)
        var tag = ""
        var i = 1
        while (i < styled.length) {
            var c2 = styled.substring(i, i + 1)
            if (c1 == ",")  {
                // is open tag?
                val openTag = tagMap[c2]
                if (openTag != null) {
                    styledHtml += "<$openTag>"
                    tag = openTag
                    c2 = styled.substring(i++, i + 1) // tags replace two characters
                }
                // is close tag?
                else if ((c2 == ".") && (tag.isNotEmpty())) {
                    styledHtml += "</$tag>"
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