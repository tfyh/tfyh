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

import org.tfyh.data.Findings.addFinding
import org.tfyh.util.I18n
import kotlin.math.max
import kotlin.math.min

object Validator {

    /* ------------------------------------------------------------------------ */
    /* ----- DATA EQUALITY ---------------------------------------------------- */
    /* ------------------------------------------------------------------------ */

    /**
     * Drill down for difference check in arrays. Keys must also be identical, but
     * not in their sequence.
     */
    private fun diffArrays(a: Map<String, Any>, b: Map<String, Any>): String {
        val i18n = I18n.getInstance()
        var diff = ""
        var keysChecked = emptyArray<String>()
        for (key in a.keys) {
            keysChecked += key
            val aVal = a[key]
            val bVal = b[key]
            diff += if (bVal == null)
                i18n.t("PP2UVk|Missing field in B.")
            else if (aVal == null)
                i18n.t("DiSB4N|Null value field in A.")
            else
                diffSingle(aVal, bVal)
        }
        for (key in b.keys) {
            if (!keysChecked.contains(key))
                diff += i18n.t("6H3gWj|Extra field in B.")
        }
        return diff
    }

    /**
     * Create a difference statement for two values.
     */
    private fun diffSingle(a: Any, b: Any): String {
        val i18n = I18n.getInstance()
        var diff = ""

        when (a) {
            // start with simple cases: array equality
            is Array<*> -> if (b !is Array<*>) diff += i18n.t("Q3220i|A is an array, but B not...")
            else -> // a is a single value
                if (b is Array<*>) diff += i18n.t("Bczhcw|A is a single value, but...")
                // single values
                else when (a) {
                    // boolean
                    is Boolean -> diff += if (b is Boolean) {
                        if (a == b) ""
                        else i18n.t("KH8xj4|A is boolean, B not.")
                    } else i18n.t("ofZjXx|boolean A is not(boolean...")
                    // long
                    is Long -> diff += if (b is Long) {
                        if (a == b) ""
                        else i18n.t("1l4ZE2|number A != number B.")
                    } else i18n.t("ncH3n3|A is a number, B not.")
                    // double
                    is Double -> diff += if (b is Double) {
                        if (a == b) ""
                        else i18n.t("1l4ZE2|number A != number B.")
                    } else i18n.t("ncH3n3|A is a number, B not.")
                    // date, time, datetime
                    is NoZoneDateTime -> diff += if (b is NoZoneDateTime) {
                        if (a.compareTo(b) == 0) ""
                        else i18n.t("LyKzyV|dateTime A != dateTime B...")
                    } else i18n.t("BAEDaf|A is a dateTime, B not.")
                    // String
                    is String -> diff += if (b is String) {
                        if (a == b) ""
                        else i18n.t("gCk9cA|string A differs from st...")
                    } else i18n.t("QYQwlG|A is a string, B not.")

                    else -> diff += i18n.t("CbG7UM|equality check failed du...") + a.toString() + "'."
                }
        }
        return diff
    }

    /**
     * Drill down for equality check in arrays. Keys must also be identical, but
     * not in their sequence. a<k> == null is regarded as equal to both b<k>> not
     * set and b<k>> = null. The same vice versa.
     */
    @Suppress("Unused")
    fun isEqualArrays(a: Map<String, Any>, b: Map<String, Any>) = (diffArrays(a, b).isEmpty())

    /**
     * Check whether two values of data are equal.
     */
    fun isEqualValues(a: Any, b: Any) = (diffSingle(a, b).isEmpty())

    /* ---------------------------------------------------------------------- */
    /* ----- TYPE CHECK ----------------------------------------------------- */
    /* ---------------------------------------------------------------------- */

    private fun isMatchingType(value: Any, type: Type): Boolean {
        if (!Parser.isMatchingNative(value, type.parser())) {
            addFinding(13,
                Formatter.format(value, Parser.nativeToParser(value),
                    Config.getInstance().language()),
                type.parser().name)
            return false
        }
        return true
    }

    /* ---------------------------------------------------------------------- */
    /* ----- LIMIT CHECKS --------------------------------------------------- */
    /* ---------------------------------------------------------------------- */

    /**
     * Check whether a value fits the native PHP type matching the type
     * constraints and its min/max limits. Single values only, no arrays
     * allowed. Values exceeding limits are adjusted to the exceeded limit.
     */
    private fun adjustToLimitsSingle(value: Any, type: Type, min: Any, max: Any, size: Int): Any {
        if (value is Array<*>) {
            addFinding(16, type.name().lowercase())
            return value
        }
        if (! isMatchingType(value, type))
            return value

        // identify validation data type
        val uLimit: Any
        val lLimit: Any
        val exceeds: Boolean
        val undergoes: Boolean
        // at that point the data type is one of the programmatically
        // defined packaged types
        when (type.parser()) {
            // set limits for common handling later
            ParserName.INT -> {
                lLimit = max((min as Int), ParserConstraints.INT_MIN)
                uLimit = min((max as Int), ParserConstraints.INT_MAX)
                exceeds = (value as Int) > uLimit
                undergoes = value < lLimit
            }

            ParserName.LONG -> {
                lLimit = max((min as Long), ParserConstraints.LONG_MIN)
                uLimit = min((max as Long), ParserConstraints.LONG_MAX)
                exceeds = (value as Long) > uLimit
                undergoes = value < lLimit
            }

            ParserName.DOUBLE -> {
                lLimit = max((min as Double), ParserConstraints.DOUBLE_MIN)
                uLimit = min((max as Double), ParserConstraints.DOUBLE_MAX)
                exceeds = (value as Double) > uLimit
                undergoes = value < lLimit
            }

            ParserName.DATE, ParserName.DATETIME -> {
                val datetimeMinMillis = ParserConstraints.DATETIME_MIN.millis()
                val datetimeMaxMillis = ParserConstraints.DATETIME_MAX.millis()
                val maxMillis = (max as NoZoneDateTime?)?.millis() ?: datetimeMaxMillis
                val minMillis = (min as NoZoneDateTime?)?.millis() ?: datetimeMinMillis
                val valMillis = (value as NoZoneDateTime).millis()
                lLimit = if (datetimeMinMillis < minMillis) minMillis else datetimeMinMillis
                uLimit = if (datetimeMaxMillis > maxMillis) maxMillis else datetimeMaxMillis
                exceeds = valMillis > uLimit
                undergoes = valMillis < lLimit
            }

            ParserName.TIME -> {
                lLimit = max((min as Int), ParserConstraints.TIME_MIN)
                uLimit = min((max as Int), ParserConstraints.TIME_MAX)
                exceeds = (value as Int) > uLimit
                undergoes = value < lLimit
            }

            // and handle in this clause and return for boolean and String and other
            ParserName.BOOLEAN ->
                return value // a boolean value never has limits
            ParserName.STRING -> {
                uLimit = if (type.name().lowercase() == "text") min(size, ParserConstraints.TEXT_SIZE)
                            else min(size, ParserConstraints.STRING_SIZE)
                if (((value as String).length) > uLimit) {
                    // shorten String, if too long
                    addFinding(15, ((value as String?)
                        ?.substring(0, min(((value).length), 20)) ?: "")
                                + "(" + ((value).length) + ")",
                        "" + uLimit
                    )
                    val adjusted =
                        if (uLimit > 12) value.substring(0, (uLimit) - 4) + " ..."
                        else value.substring(0, (uLimit))
                    return adjusted
                } else
                    return value
            }

            else -> {
                // unknown type
                addFinding(14, type.name().lowercase())
                return ""
            }
        }

        // adjust value to not exceed the limits and return it
        val language = Config.getInstance().language()
        if (undergoes) {
            addFinding(10,
                Formatter.format(value, type.parser(), language),
                Formatter.format(lLimit, type.parser(), language)
            )
            return lLimit
        } else if (exceeds) {
            addFinding(11,
                Formatter.format(value, type.parser(), language),
                Formatter.format(uLimit, type.parser(), language)
            )
            return uLimit
        } else
        return value
    }

    /**
     * Check whether a value fits the native PHP type matching the type
     * constraints and its min/max limits. Values exceeding limits are adjusted
     * to the exceeded limit.
     */
    fun adjustToLimits(value: Any, type: Type, min: Any, max: Any, size: Int): Any {
        // no null option in kotlin
        if (ParserConstraints.isEmpty(value, type.parser()))
            return value
        // no limit checking for arrays yet. They are always formatted as string and may be capped by the Formatter.
        if (value is Array<*>)
            return value
        // validate single
        return adjustToLimitsSingle(value, type, min, max, size)
    }

    /* ------------------------------------------------------------------------ */
    /* ----- SEMANTIC CHECKS -------------------------------------------------- */
    /* ------------------------------------------------------------------------ */

    /**
     * Check, whether the pwd complies to password rules.
     *
     * @param pwd String
     *            pwd password to be checked
     * @return String list of errors found. Returns empty String, if no errors
     *         were found.
     */
    private fun checkPassword(pwd: String) {
        val i18n = I18n.getInstance()
        if (pwd.length < 8)
            addFinding(6, i18n.t("FWl90i|The password must be at ..."))
        val regexNum = Regex("[0-9]+")
        val regexLCase = Regex("[a-z]+")
        val regexUCase = Regex("[A-Z]+")
        val regexSpecial1 = Regex("[!-/]+")
        val regexSpecial2 = Regex("[:-@]+")
        val regexSpecial3 = Regex("[\\[-`]+")
        val regexSpecial4 = Regex("[{-~]+")
        val numbers = if (regexNum.containsMatchIn(pwd)) 1 else 0
        val lowercase = if (regexLCase.containsMatchIn(pwd)) 1 else 0
        val uppercase = if (regexUCase.containsMatchIn(pwd)) 1 else 0
        // Four ASCII blocks: !"#$%&"*+,-./ ___ :;<to?@ ___ [\]^_` ___ {|}~
        val specialChars =
            if (regexSpecial1.containsMatchIn(pwd) || regexSpecial2.containsMatchIn(pwd)
                || regexSpecial3.containsMatchIn(pwd) || regexSpecial4.containsMatchIn(pwd)
            ) 1 else 0
        if ((numbers + lowercase + uppercase + specialChars) < 3)
            addFinding(6, i18n.t("iJUmCH|The password must contai..."))
    }

    /**
     * my_bcmod - get modulus (substitute for bcmod) string my_bcmod ( string left_operand, int modulus )
     * left_operand can be gigantic, but be careful with modulus :( by Todrius Baranauskas and Laurynas
     * Butkus :) Vilnius, Lithuania
     * https://stackoverflow.com/questions/10626277/function-bcmod-is-not-available
     */
    private fun myBcMod (x: String, y: Int): Int
    {
        // how many numbers to take at once? careful not to exceed (int)
        val take = 5
        var mod = 0
        var xm = x
        do {
            val a = mod + (xm.substring(0, take)).toInt()
            xm = xm.substring(take)
            mod = a % y
        } while (xm.isNotEmpty())
        return mod
    }

    /**
     * Check, whether the IBAN complies to IBAN rules. removes spaces from IBAN prior to check and ignores
     * letter case. Make sure the IBAN has the appropriate letter case when being entered in the form. Snippet
     * copied from https://stackoverflow.com/questions/20983339/validate-iban-php and transferred to Kotlin
     */
    // TODO not tested.
    private fun checkIBAN (iban: String, strict: Boolean = false)
    {
        val i18n = I18n.getInstance()
        if (strict && iban.uppercase().substring(0, 2) != iban.substring(0, 2)) {
            addFinding(6, i18n.t("x3HFjo|The IBAN must start with..."))
            return
        }
        val ibanLc =
            if (!strict)
                iban.lowercase().replace(" ", "")
            else iban.lowercase()
        val countries = mapOf(
            "al" to 28,"ad" to 24,"at" to 20,"az" to 28,"bh" to 22,"be" to 16,"ba" to 20,
            "br" to 29,"bg" to 22,"cr" to 21,"hr" to 21,"cy" to 28,"cz" to 24,"dk" to 18,"do" to 28,
            "ee" to 20,"fo" to 18,"fi" to 18,"fr" to 27,"ge" to 22,"de" to 22,"gi" to 23,"gr" to 27,
            "gl" to 18,"gt" to 28,"hu" to 28,"is" to 26,"ie" to 22,"il" to 23,"it" to 27,"jo" to 30,
            "kz" to 20,"kw" to 30,"lv" to 21,"lb" to 28,"li" to 21,"lt" to 20,"lu" to 20,"mk" to 19,
            "mt" to 31,"mr" to 27,"mu" to 30,"mc" to 27,"md" to 24,"me" to 22,"nl" to 18,"no" to 15,
            "pk" to 24,"ps" to 29,"pl" to 28,"pt" to 25,"qa" to 29,"ro" to 24,"sm" to 27,"sa" to 24,
            "rs" to 22,"sk" to 24,"si" to 19,"es" to 24,"se" to 24,"ch" to 21,"tn" to 24,"tr" to 26,
            "ae" to 23,"gb" to 22,"vg" to 24
        )
        val chars = mapOf(
            'a' to 10,'b' to 11,'c' to 12,'d' to 13,'e' to 14,'f' to 15,'g' to 16,'h' to 17,
            'i' to 18,'j' to 19,'k' to 20,'l' to 21,'m' to 22,'n' to 23,'o' to 24,'p' to 25,'q' to 26,
            'r' to 27,'s' to 28,'t' to 29,'u' to 30,'v' to 31,'w' to 32,'x' to 33,'y' to 34,'z' to 35
        )

        if (ibanLc.length != countries[iban.substring(0, 2)]){
            addFinding(6, i18n.t("xMGDkq|The IBAN length doesn°t ..."))
            return
        }

        val movedChar = ibanLc.substring(4) + ibanLc.substring(0, 4)
        val movedCharArray = movedChar.toCharArray()
        var newString = ""
        for (i in movedCharArray.indices) {
            if (chars[movedCharArray[i]] != null) {
                movedCharArray[i] = chars[movedCharArray[i]]?.toChar() ?: 'a'
            }
            newString += movedCharArray[i]
        }
        if (myBcMod(newString, 97) != 1)
            addFinding(6, i18n.t("ulleNr|The IBAN parity check fa..."))
    }

    /**
     * An identifier is a String consisting of [_a-zA-Z] followed by [_a-zA-Z0-9] and of 1 through 64 characters
     * length
     */
    private fun checkIdentifier (identifier: String)
    {
        val alpha = "_abcdefghijklmnopqrstuvwxyz"
        val alNum = "_abcdefghijklmnopqrstuvwxyz0123456789"
        val i18n = I18n.getInstance()
        if (identifier.isEmpty()) {
            addFinding(6, i18n.t("HE2ICg|Empty identifier"))
            return
        }
        if (identifier.length > 64)
            addFinding(6, i18n.t("VfEQj7|The maximum identifier l..."))
        val first = identifier.substring(0, 1).lowercase()
        val remainder = identifier.substring(1).lowercase()
        if (alpha.indexOf(first) < 0)
            addFinding(6, i18n.t("cVYtkK|Numeric start character ...", identifier))
        for (element in remainder)
            if (alNum.indexOf(element) <= 0)
                addFinding(6, i18n.t("WVta4w|Invalid identifier: %1.", identifier))
    }

    /**
     * This will apply a validation rule to the value. Return value is "", if compliant or an error String,
     * if not compliant.
     */
    fun checkAgainstRule (value: Any, rule: String)
    {
        if (value is String)
            when (rule) {
                "iban" -> checkIBAN(value)
                "identifier" -> checkIdentifier(value)
                "password" -> checkPassword(value)
                "uid" -> if (! Ids.isUid(value)) addFinding(6, "The uid is invalid")
                "uuid" -> if (! Ids.isUuid(value) && ! Ids.isShortUuid(value)) addFinding(6, "The uuid is invalid")
            }
    }


    /* -------------------------------------------------------------------------- */
    /* ------------- KOTLIN ONLY DATA COMPARISON -------------------------------- */
    /* -------------------------------------------------------------------------- */
    /**
     * an is lower than b. If one of a, b does not math the parsers native type, false is returned
     */
    fun lt(a: Any, b: Any, parser: ParserName): Boolean {
        return when (parser) {
            ParserName.BOOLEAN -> ((a is Boolean) && (b is Boolean) && a && !b)
            ParserName.INT -> ((a is Int) && (b is Int) && (a < b))
            ParserName.LONG -> ((a is Long) && (b is Long) && (a < b))
            ParserName.DOUBLE -> ((a is Double) && (b is Double) && (a < b))
            ParserName.DATE,
            ParserName.DATETIME -> ((a is NoZoneDateTime) && (b is NoZoneDateTime) && (a < b))
            ParserName.TIME -> ((a is Int) && (b is Int) && (a < b))
            ParserName.STRING -> ((a is String) && (b is String) && (a < b))
            ParserName.NONE -> false
            ParserName.INT_LIST -> false
            ParserName.STRING_LIST -> false
        }
    }

    /**
     * a contains b. For Boolean and numbers, this returns true for equality. For date & time it
     * converts a and b to Strings, for lists it returns true if the element b is contained in the
     * list a. If b is an array, false is returned. If one of a, b does not math the parsers
     * native type, false is returned
     */
    fun contains(a: Any, b: Any, parser: ParserName): Boolean {
        return when (parser) {
            ParserName.BOOLEAN -> ((a is Boolean) && (b is Boolean) && a == b)
            ParserName.INT -> ((a is Int) && (b is Int) && (a == b))
            ParserName.LONG -> ((a is Long) && (b is Long) && (a == b))
            ParserName.DOUBLE -> ((a is Double) && (b is Double) && (a == b))
            ParserName.DATE,
            ParserName.DATETIME,
            ParserName.TIME -> Formatter.formatCsv(a, parser).contains(Formatter.formatCsv(b, parser))
            ParserName.STRING -> ((a is String) && (b is String) && (a.contains(b)))
            ParserName.NONE -> false
            ParserName.INT_LIST -> ((a is List<*>) && (b is Int) && (a.contains(b)))
            ParserName.STRING_LIST -> ((a is List<*>) && (b is String) && (a.contains(b)))
        }
    }

    /**
     * an equals b. For Boolean and numbers, this returns true for equality. For date & time it
     * converts a and b to Strings, for lists it returns true if the element b is contained in the
     * list a. If one of a, b does not math the parsers native type, false is returned
     */
    fun equals(a: Any, b: Any, parser: ParserName): Boolean {
        return when (parser) {
            ParserName.BOOLEAN -> ((a is Boolean) && (b is Boolean) && a == b)
            ParserName.INT -> ((a is Int) && (b is Int) && (a == b))
            ParserName.LONG -> ((a is Long) && (b is Long) && (a == b))
            ParserName.DOUBLE -> ((a is Double) && (b is Double) && (a == b))
            ParserName.DATE,
            ParserName.DATETIME -> ((a is NoZoneDateTime) && (b is NoZoneDateTime) && (a == b))
            ParserName.TIME -> ((a is Int) && (b is Int) && (a == b))
            ParserName.STRING -> ((a is String) && (b is String) && (a == b))
            ParserName.NONE -> false
            ParserName.INT_LIST,
            ParserName.STRING_LIST -> ((a is List<*>) && (b is List<*>) && (a == b))
        }
    }

    /**
     * Return a comparable value in order to be able to sort. The returned value is a number or a String
     */
    fun comparable(a: Any, parser: ParserName): Comparable<*> {
        return when (parser) {
            ParserName.BOOLEAN -> if (a is Boolean) { if (a) 1 else 0 } else 0
            ParserName.INT -> a as? Int ?: 0
            ParserName.LONG -> a as? Long ?: 0L
            ParserName.DOUBLE -> a as? Double ?: 0.0
            ParserName.DATE,
            ParserName.DATETIME -> a as? NoZoneDateTime ?: NoZoneDateTime.now()
            ParserName.TIME -> a as? Int ?: 0
            ParserName.STRING -> a as? String ?: ""
            ParserName.NONE -> 0
            ParserName.INT_LIST,
            ParserName.STRING_LIST -> if (a is List<*>) a.size else 0
        }
    }

}
