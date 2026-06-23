<?php
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

namespace Data;

use DateTimeImmutable;

// internationalisation support needed to reflect validation errors such as for a password, an IBAN or similar.
use Util\I18n;

/**
 * Provides static utility methods for data validations,
 * including equality checks, type matching, and value
 * adjustments respecting limits and constraints.
 */
class Validator {

    /* ------------------------------------------------------------------------ */
    /* ----- DATA EQUALITY ---------------------------------------------------- */
    /* ------------------------------------------------------------------------ */

    /**
     * Drill down for difference check in arrays. Keys must also be identical, but
     * not in their sequence.
     */
    /**
     * @param array $a array to be compared
     * @param array $b the other array to be compared
     * @return String a statement describing the differences between the two arrays.
     */
    public static function diffArrays (array $a, array $b): String
    {
        $diff = "";
        $keys_checked = [];
        foreach ($a as $k => $v) {
            $diff_single = self::diffSingle($v, (isset($b[$k])) ? $b[$k] : null);
            $keys_checked[] = $k;
            $diff .= (strlen($diff_single) > 0) ? $k . ": " . $diff_single . ", " : "";
        }
        foreach ($b as $k => $v) {
            if (! in_array($k, $keys_checked))
                $diff .= $k . ": " . I18n::getInstance()->t("XPWCVX|Extra field in B") . ",";
        }
        return $diff;
    }

    /**
     * Create a difference statement for two values.
     * @param array|bool|DateTimeImmutable|float|int|string|null $a a value to be compared
     * @param array|bool|DateTimeImmutable|float|int|string|null $b the other value to be compared
     * @return String a statement describing the differences between the two values.
     */
    private static function diffSingle (array|bool|DateTimeImmutable|float|int|string|null $a,
                                        array|bool|DateTimeImmutable|float|int|string|null $b): String
    {
        $diff = "";
        $i18n = I18n::getInstance();

        // start with simple cases: null equality
        if (is_null($a))
            $diff .= (is_null($b)) ? "" : $i18n->t("EMt2mp|A is null, but B is not ...") . " ";
        // start with simple cases: array type equality
        elseif (is_array($a) && ! is_array($b))
            $diff .= $i18n->t("B8udF2|A is an array, but B not...") . " ";
        elseif (! is_array($a) && is_array($b))
            $diff .= $i18n->t("sF4MkY|A is a single value, but...") . " ";

        // drill down in case of two arrays
        elseif (is_array($a))
            $diff .= self::diffArrays($a, $b);

        // single values
        // boolean
        elseif (is_bool($a))
            $diff .= (is_bool($b)) ? (($a == $b) ? "" : $i18n->t("4igrr0|boolean A is not(boolean...")) : $i18n->t(
                "xitScV|A is boolean, B not.");
        // integer or time
        elseif (is_int($a))
            $diff .= (is_int($b)) ? (($a == $b) ? "" : $i18n->t("jCvlcK|integer A != integer B.")) : $i18n->t(
                "YA9xLD|A is integer, B not.");
        // floating point, maximum requested precision is 1E-11
        elseif (is_float($a))
            $diff .= (is_float($b)) ? (($a == $b) ? "" : $i18n->t("YgV1U0|float A != float B.")) : $i18n->t(
                "qQXEIR|A is float, B not.");
        // date, time, datetime
        elseif (is_object($a)) {
            // only DateTimImmutable objects are allowed in the Tfyh data context as value objects
            if (! is_object($b))
                $diff .= $i18n->t("8qn1n1|A is object, B not.");
            elseif (strcasecmp(get_class($a), "DateTimeImmutable") != 0)
                $diff .= $i18n->t("OHYGer|A is object, but not a D...");
            elseif (strcasecmp(get_class($b), "DateTimeImmutable") != 0)
                $diff .= $i18n->t("K8RkEM|A is DateTimeImmutable, ...");
            $diff .= ((strcmp($a->format("Y-m-d H:i:s"), $b->format("Y-m-d H:i:s")) == 0)) ? "" : $i18n->t(
                "z5G34s|datetime A != datetime B...");
        } elseif (is_string($a)) // String
            $diff .= (is_string($b)) ? ((strcmp($a, $b) == 0) ? "" : $i18n->t("fkVBQK|string A differs from st...")) : $i18n->t(
                "y5iS0B|A is a string, B not.");
        // no other values supported. They are always regarded as unequal.
        else
            $diff .= $i18n->t("Wxhd4A|equality check failed du...") . json_encode($a) . ".";

        // echo " result: " . $diff . "<br>";
        return $diff;
    }

    /**
     * Drill down for equality check in arrays. Keys must also be identical, but
     * not in their sequence. A<k> == null is regarded as equal to both b<k>> not
     * set and b<k>> = null. The same vice versa.
     * @param array $a the array to be compared
     * @param array $b the other array
     * @return bool true, if the arrays are equal, false otherwise.
     */
    private static function isEqualArrays(array $a, array $b): bool {
        return (strlen(self::diffArrays($a, $b)) == 0);
    }

    /**
     * Check whether two values of data are equal.
     * @param array|bool|DateTimeImmutable|float|int|string $a a value to be compared
     * @param array|bool|DateTimeImmutable|float|int|string $b the other value.
     * @return bool true, if the values are equal, false otherwise.
     */
    public static function isEqualValues(array|bool|DateTimeImmutable|float|int|string $a,
                                         array|bool|DateTimeImmutable|float|int|string $b): bool {
        return (strlen(self::diffSingle($a, $b)) == 0);
    }

    /* ---------------------------------------------------------------------- */
    /* ----- TYPE CHECK ----------------------------------------------------- */
    /* ---------------------------------------------------------------------- */

    /**
     * Check whether a value fits the native PHP type matching the type
     * constraints.
     * @param bool|int|float|DateTimeImmutable|string $value the value to be checked
     * @param Type $type the type to be checked against
     * @return bool true, if the value fits the type, false otherwise.
     */
    public static function isMatchingType (bool|int|float|DateTimeImmutable|string $value, Type $type): bool
    {
        if (!Parser::isMatchingNative($value, $type->parser())) {
            Findings::addFinding(13, Formatter::format($value, Parser::nativeToParser($value)),
                $type->parser()->name);
            return false;
        }
        return true;
    }

    /* ---------------------------------------------------------------------- */
    /* ----- LIMIT CHECKS --------------------------------------------------- */
    /* ---------------------------------------------------------------------- */

    /**
     * Check whether a value fits the native PHP type matching the type constraints and its min/max limits.
     * Single values only, no arrays allowed. Values exceeding limits are adjusted to the exceeded limit.
     * @param mixed $value the value to be checked
     * @param Type $type the type to be checked against
     * @param mixed $min the minimum value allowed
     * @param mixed $max the maximum value allowed
     * @param int $size the maximum size of the value, if it is a string.
     * @return mixed the adjusted value, if it exceeds the limits, false otherwise.
     */
    private static function adjustToLimitsSingle (mixed $value, Type $type, mixed $min, mixed $max, int $size): mixed
    {
        if (is_array($value)) {
            Findings::addFinding(16, $type->name());
            return false;
        }
        if (! self::isMatchingType($value, $type))
            return false;

        switch ($type->parser()) {
            case ParserName::INT:
                $lLimit = max($min, ParserConstraints::INT_MIN);
                $uLimit = min($max, ParserConstraints::INT_MAX);
                break;
            case ParserName::LONG:
                $lLimit = max($min, ParserConstraints::LONG_MIN);
                $uLimit = min($max, ParserConstraints::LONG_MAX);
                break;
            case ParserName::DOUBLE:
                $lLimit = max($min, ParserConstraints::DOUBLE_MIN);
                $uLimit = min($max, ParserConstraints::DOUBLE_MAX);
                break;
            case ParserName::DATE:
            case ParserName::DATETIME:
                $lLimit = max($min, ParserConstraints::$DATETIME_MIN);
                $uLimit = min($max, ParserConstraints::$DATETIME_MAX);
                break;
            case ParserName::TIME:
                $lLimit = max($min, ParserConstraints::TIME_MIN);
                $uLimit = min($max, ParserConstraints::TIME_MAX);
                break;
            case ParserName::BOOLEAN:
                return $value; // a boolean value never has limits
            case ParserName::STRING:
                $uLimit = (strcasecmp($type->name(), "text") == 0) ?
                    min($size, ParserConstraints::TEXT_SIZE) :
                    min($size, ParserConstraints::STRING_SIZE);
                if (mb_strlen($value) > $uLimit) {
                    // shorten String, if too long
                    Findings::addFinding(17,
                        mb_substr($value, 0, min(mb_strlen($value), 20)) . "[" . $type . "(" .
                        mb_strlen($value) . "])", strval($uLimit));
                    return ($uLimit > 12) ? mb_substr($value, 0, $uLimit - 4) . " ..." : mb_substr($value, 0,
                        $uLimit);
                } else
                    return $value;
            default:
                Findings::addFinding(14, $type->name());
                return false;

        }

        // adjust value to not exceed the limits
        if ($value < $lLimit) {
            Findings::addFinding(10, Formatter::format($value, $type->parser()), Formatter::format($lLimit, $type->parser()));
            return $lLimit;
        } elseif ($value > $uLimit) {
            Findings::addFinding(11, Formatter::format($value, $type->parser()), Formatter::format($uLimit, $type->parser()));
            return $uLimit;
        } else
            return $value;
    }

    /**
     * Check whether a value fits the native PHP type matching the type
     * constraints and its min/max limits. Values exceeding limits are adjusted
     * to the exceeded limit. Null values are replaced by their type's
     * ParserConstraints::Empty value. This applies for values and lists.
     * @param mixed $value the value to be checked
     * @param Type $type the type to be checked against
     * @param mixed $min the minimum value allowed
     * @param mixed $max the maximum value allowed
     * @param int $size the maximum size of the value, if it is a string.
     * @return mixed the adjusted value, if it exceeds the limits, false otherwise.
     */
    public static function adjustToLimits (mixed $value, Type $type, mixed $min, mixed $max, int $size): mixed
    {
        // empty and null are always valid
        if (ParserConstraints::isEmpty($value, $type->parser()))
            return $value;
        if (is_null($value))
            // never return null
            return ParserConstraints::empty($type->parser());
        // no limit checking for arrays yet. They are always formatted as string and may be capped by the Formatter.
        if (is_array($value))
            return $value;
        // validate single
        return self::adjustToLimitsSingle($value, $type, $min, $max, $size);
    }

    /**
     * Check, whether the pwd complies to password rules. Any finding is added to the finding list.
     *
     * @param String $pwd
     *            password to be checked
     * @return void
     */
    public static function checkPassword (String $pwd): void
    {
        $i18n = I18n::getInstance();
        if ((strlen($pwd) < 8) || (strlen($pwd) > 32)) {
            $is_hash = ((strlen($pwd) == 60) && (strcmp(substr($pwd, 0, 2), "$2" == 0)));
            if (! $is_hash)
                Findings::addFinding(6, $i18n->t("aJ5Cy9|The password must be bet..."));
        }
        $numbers = (preg_match("#[0-9]+#", $pwd)) ? 1 : 0;
        $lowercase = (preg_match("#[a-z]+#", $pwd)) ? 1 : 0;
        $uppercase = (preg_match("#[A-Z]+#", $pwd)) ? 1 : 0;
        // Four ASCII blocks: !"#$%&'*+,-./ ___ :;<=>?@ ___ [\]^_` ___ {|}~
        $specialChars = (preg_match("#[!-/]+#", $pwd) || preg_match("#[:-@]+#", $pwd) ||
            preg_match("#[\[-`]+#", $pwd) || preg_match("#[{-~]+#", $pwd)) ? 1 : 0;
        if (($numbers + $lowercase + $uppercase + $specialChars) < 3)
            Findings::addFinding(6, $i18n->t("iJUmCH|The password must contai..."));
    }

    /**
     * my_bcmod - get modulus (substitute for bcmod) string my_bcmod (string left_operand, int modulus)
     * left_operand can be gigantic but be careful with modulus :( by Todrius Baranauskas and Laurynas
     * Butkus :) Vilnius, Lithuania
     * https://stackoverflow.com/questions/10626277/function-bcmod-is-not-available
     */
    private static function myBcMod (String $x, String $y): int
    {
        // how many numbers to take at once? careful not to exceed (int)
        $take = 5;
        $mod = '';

        do {
            $a = (int) $mod . substr($x, 0, $take);
            $x = substr($x, $take);
            $mod = $a % $y;
        } while (strlen($x));

        return $mod;
    }

    /**
     * Check, whether the IBAN complies to IBAN rules. Removes spaces from IBAN prior to check and ignores
     * a letter case. Make sure the IBAN has the appropriate letter case when being entered in the form. Snippet
     * copied from https://stackoverflow.com/questions/20983339/validate-iban-php
     */
    private static function checkIBAN ($iban, bool $strict = false): void
    {
        $i18n = I18n::getInstance();
        if ($strict === false)
            $iban = strtolower(str_replace(' ', '', $iban));
        elseif (substr(strtoupper($iban), 0, 2) != substr($iban, 0, 2)) {
            Findings::addFinding(6, $i18n->t("PFcm3H|The IBAN must start with..."));
            return;
        }
        $iban = strtolower($iban);
        $Countries = array('al' => 28,'ad' => 24,'at' => 20,'az' => 28,'bh' => 22,'be' => 16,'ba' => 20,
            'br' => 29,'bg' => 22,'cr' => 21,'hr' => 21,'cy' => 28,'cz' => 24,'dk' => 18,'do' => 28,
            'ee' => 20,'fo' => 18,'fi' => 18,'fr' => 27,'ge' => 22,'de' => 22,'gi' => 23,'gr' => 27,
            'gl' => 18,'gt' => 28,'hu' => 28,'is' => 26,'ie' => 22,'il' => 23,'it' => 27,'jo' => 30,
            'kz' => 20,'kw' => 30,'lv' => 21,'lb' => 28,'li' => 21,'lt' => 20,'lu' => 20,'mk' => 19,
            'mt' => 31,'mr' => 27,'mu' => 30,'mc' => 27,'md' => 24,'me' => 22,'nl' => 18,'no' => 15,
            'pk' => 24,'ps' => 29,'pl' => 28,'pt' => 25,'qa' => 29,'ro' => 24,'sm' => 27,'sa' => 24,
            'rs' => 22,'sk' => 24,'si' => 19,'es' => 24,'se' => 24,'ch' => 21,'tn' => 24,'tr' => 26,
            'ae' => 23,'gb' => 22,'vg' => 24
        );
        $Chars = array('a' => 10,'b' => 11,'c' => 12,'d' => 13,'e' => 14,'f' => 15,'g' => 16,'h' => 17,
            'i' => 18,'j' => 19,'k' => 20,'l' => 21,'m' => 22,'n' => 23,'o' => 24,'p' => 25,'q' => 26,
            'r' => 27,'s' => 28,'t' => 29,'u' => 30,'v' => 31,'w' => 32,'x' => 33,'y' => 34,'z' => 35
        );

        if (strlen($iban) != $Countries[substr(strtolower($iban), 0, 2)]) {
            Findings::addFinding(6, $i18n->t("slMQwZ|The IBAN length doesn°t ..."));
            return;
        }

        $MovedChar = substr($iban, 4) . substr($iban, 0, 4);
        $MovedCharArray = str_split($MovedChar);
        $NewString = "";
        foreach ($MovedCharArray as $key => $value) {
            if (! is_numeric($value)) {
                $MovedCharArray[$key] = $Chars[$value];
            }
            $NewString .= $MovedCharArray[$key];
        }
        if (self::myBcMod($NewString, '97') != 1)
            Findings::addFinding(6, $i18n->t("hQOB0B|The IBAN parity check fa..."));
    }

    /**
     * An identifier is a String consisting of [_a-zA-Z] followed by [_a-zA-Z0-9] and of 1 to 64 characters
     * length
     * @param String $identifier the identifier to be checked
     * @return void
     */
    public static function checkIdentifier (String $identifier): void
    {
        $i18n = I18n::getInstance();
        if (strlen($identifier) < 1) {
            Findings::addFinding(6, $i18n->t("HE2ICg|Empty identifier"));
            return;
        }
        if (strlen($identifier) > 64)
            Findings::addFinding(6, $i18n->t("VfEQj7|The maximum identifier l..."));
        $first = substr($identifier, 0, 1);
        $remainder = str_replace("_", "A", substr($identifier, 1));
        if (! ctype_alpha($first) && (strcmp($first, "_") != 0))
            Findings::addFinding(6, $i18n->t("cVYtkK|Numeric start character ...", $identifier));
        if ((strlen($remainder) > 0) && ! ctype_alnum($remainder))
            Findings::addFinding(6, $i18n->t("WVta4w|Invalid identifier: %1.", $identifier));
    }

    /**
     * Validates a value against a specific rule and performs necessary checks based on the rule type.
     * Supported rules include "iban", "identifier", "password", "uid", and "uuid".
     *
     * @param mixed $value The value to be checked. It can be a string or an array, depending on the rule.
     * @param String $rule The rule to check against. Must be one of the supported rule types.
     * @return void
     */
    public static function checkAgainstRule (mixed $value, String $rule): void
    {
        $i18n = I18n::getInstance();
        if (strcasecmp($rule, "iban") == 0)
            self::checkIBAN($value);
        else if (strcasecmp($rule, "identifier") == 0)
            self::checkIdentifier($value);
        else if (strcasecmp($rule, "password") == 0)
            self::checkPassword($value);
        else if (strcasecmp($rule, "uid") == 0) {
            if (!Ids::isUid($value))
                Findings::addFinding(6, $i18n->t("ChYtZx|The uid $value is invali..."));
        } else if (strcasecmp($rule, "uuid") == 0) {
            // the rule can also apply to lists
            $uuids = (is_array($value)) ? $value : [ $value ];
            foreach ($uuids as $uuid)
                if (!Ids::isUuid($uuid) && !Ids::isShortUuid($uuid))
                    Findings::addFinding(6, $i18n->t("IBKOYL|The uuid $uuid is invali..."));
        }
    }


}
