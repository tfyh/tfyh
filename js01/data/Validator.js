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
 * Provides static utility methods for data validations,
 * including equality checks, type matching, and value
 * adjustments respecting limits and constraints.
 */class Validator {

    /* ------------------------------------------------------------------------ */
    /* ----- DATA EQUALITY ---------------------------------------------------- */
    /* ------------------------------------------------------------------------ */

    /**
     * Drill down for difference check in arrays. Keys must also be identical, but
     * not in their sequence.
     */
    static diffArrays(a, b)
    {
        let diff = "";
        let keys_checked = [];
        for (let k in a) {
            keys_checked.push(k);
            diff += Validator.#diffSingle(a[k], b[k]);
        }
        for (let k in b) {
            if (keys_checked.indexOf(k) < 0)
                diff +=  i18n.t("6H3gWj|Extra field in B.");
        }
        return diff;
    }

    /**
     * Create a difference statement for two values.
     */
    static #diffSingle(a, b)
    {
        let diff = "";
        // start with simple cases: null equality
        if (a == null)
            diff += (b == null) ? "" : i18n.t("kSCib2|A is null, but B is not ...") + " ";
        // start with simple cases: array type equality
        else if (Array.isArray(a) && ! Array.isArray(b))
            diff += i18n.t("Q3220i|A is an array, but B not...") + " ";
        else if (! Array.isArray(a) && Array.isArray(b))
            diff += i18n.t("Bczhcw|A is a single value, but...") + " ";

        // drill down in case of two arrays
        else if (Array.isArray(a))
            diff += Validator.diffArrays(a, b);

            // single values
        // boolean
        else if (typeof a == "boolean")
            diff += (typeof b == "boolean") ? ((a === b) ? "" : i18n.t("ofZjXx|boolean A is not(boolean...")) : i18n.t(
                "KH8xj4|A is boolean, B not.");
            // integer or time or float. JavaScript does not distinguish int
        // and float
        else if (typeof a == "number")
            diff += (typeof b == "number") ? ((a === b) ? "" : i18n.t("1l4ZE2|number A != number B."))
                : i18n.t("ncH3n3|A is a number, B not.");
        // date, time, datetime
        else if (typeof a == "object") {
            // only Date objects are allowed in the Tfyh data context as
            // value objects
            if (typeof b != "object")
                diff += i18n.t("JiCPzn|A is object, B not.");
            else if (a.constructor.name !== "Date")
                diff += i18n.t("wMABQI|A is object, but not a D...");
            else if (b.constructor.name !== "Date")
                diff += i18n.t("lEUpOn|A is Date, B not.");
            diff += (a.toISOString() === b.toISOString()) ? "" : i18n.t("Gh6rpp|datetime A != datetime B...");
        } else if (typeof a == "string") // String
            diff += (typeof b == "string") ? ((a === b) ? "" : i18n.t("gCk9cA|string A differs from st...")) : i18n.t(
                "QYQwlG|A is a string, B not.");
            // no other values supported. They are always regarded as
        // unequal.
        else
            diff += i18n.t("CbG7UM|equality check failed du...") + a + "'.";

        // echo " result: " + diff + "<br>";
        return diff;
    }

    /**
     * Drill down for equality check in arrays. Keys must also be identical, but
     * not in their sequence. a<k> == null is regarded as equal to both b<k>> not
     * set and b<k>> = null. The same vice versa.
     */
    static isEqualArrays(a, b) { return (Validator.diffArrays(a, b).length === 0) }

    /**
     * Check whether two values of data are equal.
     */
    static isEqualValues(a, b) { return (Validator.#diffSingle(a, b).length === 0) }

    /* ---------------------------------------------------------------------- */
    /* ----- TYPE CHECK ----------------------------------------------------- */
    /* ---------------------------------------------------------------------- */

    static #isMatchingType(value, type) {
        if (!Parser.isMatchingNative(value, type.parser())) {
            Findings.addFinding(13, Formatter.format(value, Parser.nativeToParser(value)),
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
    static #adjustToLimitsSingle(value, type, min, max, size) {
        if (Array.isArray(value)) {
            Findings.addFinding(16, type.name().toLowerCase())
            return value
        }
        if (! Validator.#isMatchingType(value, type))
            return value

        // identify validation data type
        let uLimit
        let lLimit
        let exceeds
        let undergoes
        // at that point the data type is one of the programmatically
        // defined packaged types
        switch (type.parser()) {
            // set limits for common handling later
            case ParserName.INT: 
                lLimit = Math.max(min, ParserConstraints.INT_MIN)
                uLimit = Math.min(max, ParserConstraints.INT_MAX)
                exceeds = value > uLimit
                undergoes = value < lLimit
                break

            case ParserName.LONG:
                lLimit = Math.max(min, ParserConstraints.LONG_MIN)
                uLimit = Math.min(max, ParserConstraints.LONG_MAX)
                exceeds = value > uLimit
                undergoes = value < lLimit
                break

            case ParserName.DOUBLE:
                lLimit = Math.max(min, ParserConstraints.DOUBLE_MIN)
                uLimit = Math.min(max, ParserConstraints.DOUBLE_MAX)
                exceeds = value > uLimit
                undergoes = value < lLimit
                break
            
            case ParserName.DATE:
            case ParserName.DATETIME:
                // the time zone only used for comparison of date and datetime.
                // Therefore, it does not matter which time zone is used, as long as it is the same
                // for all conversions
                let minTime = min.getTime()
                let maxTime = max.getTime()
                lLimit = Math.max(minTime, ParserConstraints.DATETIME_MIN.getTime())
                uLimit = Math.min(maxTime, ParserConstraints.DATETIME_MIN.getTime())
                exceeds = value.getTime() > uLimit
                undergoes = value.getTime() < lLimit
                break
            
            case ParserName.TIME:
                lLimit = Math.max(min, ParserConstraints.TIME_MIN)
                uLimit = Math.min(max, ParserConstraints.TIME_MAX)
                exceeds = value > uLimit
                undergoes = value < lLimit
                break

            // and handle in this clause and return for boolean and String and other
            case ParserName.BOOLEAN:
                return value // a boolean value never has limits
            case ParserName.STRING:
                uLimit = (type.name().toLowerCase() === "text") ?
                    Math.min(size, ParserConstraints.TEXT_SIZE) :
                    Math.min(size, ParserConstraints.STRING_SIZE)
                if (value.length > uLimit) {
                    // shorten String, if too long
                    Findings.addFinding(15, value.substring(0, Math.min(value.length, 20))
                        + "(" + ((value).length) + ")", uLimit)
                    return (uLimit > 12) ? value.substring(0, uLimit - 4) + " ..." : value.substring(0, uLimit)
                } else
                    return value
            default:
                // unknown type
                Findings.addFinding(14, type.name().toLowerCase())
                return ""
        }

        // adjust value to not exceed the limits and return it
        if (undergoes) {
            Findings.addFinding(10,
                Formatter.format(value, type.parser()),
                Formatter.format(lLimit, type.parser())
            )
            return lLimit
        } else if (exceeds) {
            Findings.addFinding(11,
                Formatter.format(value, type.parser()),
                Formatter.format(uLimit, type.parser())
            )
            return uLimit
        } else
            return value
    }

    /**
     * Check whether a value fits the native PHP type matching the type
     * constraints and its min/max limits. Values exceeding limits are adjusted
     * to the exceeded limit. Null values are replaced by their type's
     * ParserConstraints.empty value
     */
    static adjustToLimits(value, type, min, max, size) {
        if (ParserConstraints.isEmpty(value, type.parser()))
            return value
        if ((value == null) || (typeof value === 'undefined'))
            // never return null
            return ParserConstraints.empty(type.parser());
        // no limit checking for arrays yet. They are always formatted as string and may be capped by the Formatter.
        if (Array.isArray(value))
            return value
        // validate single
        return Validator.#adjustToLimitsSingle(value, type, min, max, size)
    }

    /* ------------------------------------------------------------------------ */
    /* ----- SEMANTIC CHECKS -------------------------------------------------- */
    /* ------------------------------------------------------------------------ */

    /**
     * Check, whether the pwd complies to password rules.
     */
    static #checkPassword (pwd)  {
        if (pwd.length < 8)
            Findings.addFinding(6, i18n.t("aJ5Cy9|The password must be bet..."));
        let numbers = (/\d/.test(pwd)) ? 1 : 0;
        let lowercase = (pwd.toUpperCase() === pwd) ? 0 : 1;
        let uppercase = (pwd.toLowerCase() === pwd) ? 0 : 1;
        // Four ASCII blocks: !"#$%&'*+,-./ ___ :;<=>?@ ___ [\]^_` ___ {|}~
        let specialChars = (pwd.match(/[!-\/]+/g) || pwd.match(/[:-@]+/g) || pwd.match(/[\[-`]+/g) ||
            pwd.match(/[{-~]+/g)) ? 1 : 0;
        if ((numbers + lowercase + uppercase + specialChars) < 3)
            Findings.addFinding(6, i18n.t("iJUmCH|The password must contai..."));
    }

    /**
     * my_bcMod - get modulus (substitute for bcMod) string my_bcMod( string left_operand, int modulus)
     * left_operand can be gigantic but be careful with modulus :( by Todrius Baranauskas and Laurynas
     * Butkus :) Vilnius, Lithuania
     * https://stackoverflow.com/questions/10626277/function-bcmod-is-not-available
     */
    static #myBcMod (x, y)
    {
        // how many numbers to take at once? careful not to exceed (int)
        let take = 5
        let mod = 0
        let xm = x
        do {
            let a = mod + parseInt(xm.substring(0, take))
            xm = xm.substring(take)
            mod = a % y
        } while (xm.length > 0)
        return mod
    }

    /**
     * Check, whether the IBAN complies to IBAN rules. removes spaces from IBAN prior to check and ignores
     * the letter case. Make sure the IBAN has the appropriate letter case when being entered in the form. Snippet
     * copied from https://stackoverflow.com/questions/20983339/validate-iban-php and transferred to Kotlin
     */
    // TODO not tested.
    static #checkIBAN (iban, strict = false)
    {
        if (strict && iban.toUpperCase().substring(0, 2) !== iban.substring(0, 2)) {
            Findings.addFinding(6, i18n.t("aYo4k5|The IBAN must start with..."));
            return;
        }
        let ibanLc = (!strict) ? iban.toLowerCase().replace(" ", "") : iban.toLowerCase()
        let countries = {
            al: 28, ad: 24, at: 20, az: 28, bh: 22, be: 16, ba: 20,
            br: 29, bg: 22, cr: 21, hr: 21, cy: 28, cz: 24, dk: 18, do: 28,
            ee: 20, fo: 18, fi: 18, fr: 27, ge: 22, de: 22, gi: 23, gr: 27,
            gl: 18, gt: 28, hu: 28, is: 26, ie: 22, il: 23, it: 27, jo: 30,
            kz: 20, kw: 30, lv: 21, lb: 28, li: 21, lt: 20, lu: 20, mk: 19,
            mt: 31, mr: 27, mu: 30, mc: 27, md: 24, me: 22, nl: 18, no: 15,
            pk: 24, ps: 29, pl: 28, pt: 25, qa: 29, ro: 24, sm: 27, sa: 24,
            rs: 22, sk: 24, si: 19, es: 24, se: 24, ch: 21, tn: 24, tr: 26,
            ae: 23, gb: 22, vg: 24 } 
        let chars = {
            a: 10, b: 11, c: 12, d: 13, e: 14, f: 15, g: 16, h: 17,
            i: 18, j: 19, k: 20, l: 21, m: 22, n: 23, o: 24, p: 25, q: 26,
            r: 27, s: 28, t: 29, u: 30, v: 31, w: 32, x: 33, y: 34, z: 35 }

        if (ibanLc.length !== countries[iban.substring(0, 2)]) {
            Findings.addFinding(6, i18n.t("B8QA1g|The IBAN length doesn°t ..."));
            return;
        }

        let movedChar = ibanLc.substring(4) + ibanLc.substring(0, 4)
        let newString = ""
        for (let i = 0; i < movedChar.length; i++) {
            if (chars[movedChar[i]] != null) {
                // TODO this was not tested. It is not clear whether String.fromCharCode() is equivalent to ->toChar() in PHP
                movedChar[i] = String.fromCharCode(chars[movedChar[i]])
            }
            newString += movedChar[i]
        }
        if (Validator.#myBcMod(newString, 97) !== 1)
            Findings.addFinding(6, i18n.t("xI1ac4|The IBAN parity check fa..."));
    }

    /**
     * An identifier is a String consisting of [_a-zA-Z] followed by [_a-zA-Z0-9] and of 1 ... 64 characters
     * length
     */
    static #checkIdentifier (identifier)
    {
        let alpha = "_abcdefghijklmnopqrstuvwxyz"
        let alNum = "_abcdefghijklmnopqrstuvwxyz0123456789"
        if (identifier.length === 0) {
            Findings.addFinding(6, i18n.t("HE2ICg|Empty identifier"));
            return;
        }
        if (identifier.length > 64)
            Findings.addFinding(6, i18n.t("VfEQj7|The maximum identifier l..."))
        let first = identifier.substring(0, 1).toLowerCase()
        let remainder = identifier.substring(1).toLowerCase()
        if (alpha.indexOf(first) < 0)
            Findings.addFinding(6, i18n.t("cVYtkK|Numeric start character ...", identifier))
        for (let i = 0; i < remainder.length; i++)
            if (alNum.indexOf(remainder[i]) <= 0)
                Findings.addFinding(6, i18n.t("WVta4w|Invalid identifier: %1.", identifier))
    }

    /**
     * This will apply a validation rule to the value. Return value is "", if compliant or an error String,
     * if not compliant.
     */
    static checkAgainstRule (value, rule)
    {
        if (typeof value == "string") {
            switch (rule) {
                case "iban": Validator.#checkIBAN(value); break
                case "identifier": Validator.#checkIdentifier(value); break
                case "password": Validator.#checkPassword(value); break
                case "uid": if (!Ids.isUid(value)) Findings.addFinding(6, "The uid is invalid"); break
                case "uuid": if (!Ids.isUuid(value) && !Ids.isShortUuid(value)) Findings.addFinding(6, "The uuid is invalid")
            }
        }
    }

}
