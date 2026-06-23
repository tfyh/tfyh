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

use DateInterval;
use DateTime;
use DateTimeImmutable;
use Exception;

use Util\Language;

/**
 * Handles the formatting of various data types and structures for storage or display
 * across different formats such as SQL, CSV, or plain text. The class is fully static.
 * Its counterpart is the Parser. Its main method is the format() method, but some
 * helper methods are also available.
 */
class Formatter
{

    /**
     * @var Language the default language to use for formatting. Cached from the configuration.
     */
    private static Language $language = Language::EN;

    /**
     * @param Language $language the default language to use for formatting.
     * @return void
     */
    public static function setLocale(Language $language): void {
        self::$language = $language;
    }

    /* ------------------------------------------------------------------------ */
    /* ----- DATA FORMATTING -------------------------------------------------- */
    /* ----- No errors are documented ----------------------------------------- */
    /* ------------------------------------------------------------------------ */

    private static function formatBoolean (bool $bool, Language $language): string
    {
        if ($language == Language::SQL)
            return ($bool) ? "1" : "0";
        if ($language == Language::CSV)
            return ($bool) ? "true" : "false";
        return ($bool) ? "on" : "";
    }

    private static function formatInt (int $int): string { return strval($int); }

    private static function formatLong (float $long): string {
        return number_format($long, 0, "", "");
    }

    private static function formatDouble (float $double, Language $language): string
    {
        $numberString = strval($double);
        if (Language::settingsOf($language)->decimalPoint)
            return $numberString;
        return str_replace(".", ",", $numberString);
    }

    private static function formatDate (DateTimeImmutable $date, Language $language): string
    {
        return $date->format(Language::settingsOf($language)->dateTemplate);
    }

    private static function formatTime (int $time, Language $language): string
    {
        // split sign and number of seconds
        $sign = ($time < 0) ? "-" : "";
        $time_int = abs($time);
        // limit number of seconds
        if ($time >= ParserConstraints::TIME_MAX)
            $time_int = ParserConstraints::TIME_MAX;
        if ($time <= ParserConstraints::TIME_MIN)
            $time_int = abs(ParserConstraints::TIME_MIN);
        // return as integer for SQL. No quotes.
        if ($language == Language::SQL)
            return $sign . $time_int;
        // return as string else.
        $s = $time_int % 60;
        $m = (($time_int - $s) / 60) % 60;
        $h = (($time_int - $m * 60 - $s) / 3600);
        return $sign . sprintf("%'.02d", $h) . ":" .
            sprintf("%'.02d", $m) . ":" . sprintf("%'.02d", $s);
    }

    private static function formatDatetime (DateTimeImmutable $datetime, Language $language): string
    {
        return $datetime->format(Language::settingsOf($language)->dateTemplate . " H:i:s");
    }

    /**
     * Format a string list for storage in files and the database.
     */
    private static function formatList (array $list, Language $language, ParserName $singleParser): String
    {
        if (count($list) == 0)
            return "[]";
        $formatted = "";
        foreach ($list as $element)
            $formatted .= ", " . Codec::encodeCsvEntry(self::format($element, $singleParser, $language), ",");
        return "[" . substr($formatted, 2) . "]";
    }

    /**
     * Format a value for storage in files and the database. Arrays will be formatted as bracketed, comma-separated
     * list (like [a,b,", and c"]). For empty values (see TypeConstraints), an empty String is returned. Null values
     * return an empty String or NULL (Language::SQL) and boolean values "on" and "" for true and false on any but
     * Language::CSV (true or false). For Language::CSV and Language::SQL the appropriate double and single quotes are
     * included.
     * @param mixed $value the value to format.
     * @param ParserName $parser the parser to use for formatting.
     * @param Language|null $language the language to use for formatting. If null, the default language is used.
     * @return string the formatted value.
     */
    public static function format (mixed $value, ParserName $parser, Language $language = null): string
    {
        if (is_null($value))
            return ($language == Language::SQL) ? "NULL" : "";
        if (is_null($language))
            $language = Config::getInstance()->language();
        if (!Parser::isMatchingNative($value, $parser)) {
            Findings::addFinding(1, strval($value), $parser->name);
            return self::format(ParserConstraints::empty($parser), $parser, $language);
        }
        if (($language != Language::SQL) && (ParserConstraints::isEmpty($value, $parser)))
            return "";
        try {
            return match ($parser) {
                ParserName::BOOLEAN => self::formatBoolean($value, $language),
                ParserName::INT => self::formatInt($value),
                ParserName::INT_LIST => self::formatList($value, $language, ParserName::INT),
                ParserName::LONG => self::formatLong($value),
                ParserName::DOUBLE => self::formatDouble($value, $language),
                ParserName::DATE => self::formatDate($value, $language),
                ParserName::DATETIME => self::formatDatetime($value, $language),
                ParserName::TIME => self::formatTime($value, $language),
                ParserName::STRING => $value,
                ParserName::STRING_LIST => self::formatList($value, $language, ParserName::STRING),
                ParserName::NONE => ""
            };
        } catch (Exception) {
            Findings::addFinding(3, strval($value), $parser->name);
            return self::format(ParserConstraints::empty($parser), $parser, $language);
        }
    }

    /**
     * Convenience method for formatting a value for storage in files.
     */
    public static function formatCsv (mixed $value, ParserName $parser): string {
        return self::format($value, $parser, Language::CSV);
    }

    /* ------------------------------------------------------------------------ */
    /* ----- SPECIAL FORMATTING ----------------------------------------------- */
    /* ----- No errors are documented ----------------------------------------- */
    /* ------------------------------------------------------------------------ */

    /**
     * Convert a String into an Identifier by replacing forbidden characters by an underscore and cutting the
     * length to 64 characters maximum.
     *
     * @param String $str
     *            the String to convert.
     * @return string the converted String
     */
    public static function toIdentifier (String $str): string
    {
        $identifier = "";
        $firstAllowed = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_";
        $subsequentAllowed = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_0123456789";
        $first = mb_substr($str, 0, 1);
        if (!str_contains($firstAllowed, $first))
            $identifier .= "_";
        for ($i = 0; ($i < mb_strlen($str)) && (strlen($identifier) < 64); $i ++) {
            $c = mb_substr($str, $i, 1);
            $identifier .= ((!str_contains($subsequentAllowed, $c))) ? (($c == " ") ? "_" : "") : $c;
        }
        return $identifier;
    }

    /**
     * Convert micro time float to a datetime object
     * @param float $microTime the micro time float to convert.
     * @return DateTimeImmutable the converted datetime.
     */
    public static function microTimeToDateTime (float $microTime): DateTimeImmutable
    {
        // convert micro time float to datetime
        $dt0 = new DateTime("1970-01-01 00:00:00");
        $seconds = sprintf("%u", $microTime); // %u => integer formatting, not 32 bit limited
        if (strlen($seconds) >= 11)
            $seconds = "9999999999"; // 1.0e11 - 1
        try {
            $dti = new DateInterval(sprintf("PT%uS", $seconds));
            $dt = $dt0->add($dti);
            return DateTimeImmutable::createFromMutable($dt);
        } catch (Exception) {
            return new DateTimeImmutable("1970-01-01 00:00:00");
        }
    }

    /**
     * Convert micro time float to a datetime String
     * @param float $microTime the micro time float to convert.
     * @param Language|null $language the language to use for formatting. If null, the default language is used.
     * @return String the converted datetime String.
     */
    public static function microTimeToString (float $microTime, Language $language = null): String
    {
        if (is_null($language)) $language = self::$language;
        return self::format(self::microTimeToDateTime($microTime), ParserName::DATETIME, $language);
    }

    /**
      *
     * The input string can contain special markers to represent formatting styles such as bold, italic,
     * underline, strikethrough, superscript, subscript, and code. These markers are translated to their
     * corresponding HTML tags.
     * This formats a string by replacing ,* by &lt;b&gt;, ,/ by &lt;i&gt;, ,_ by &lt;u&gt;, .- by &lt;s&gt;,
     * ,^ by &lt;sup&gt;, ,, by &lt;sub&gt;, and ,# by &lt;code&gt;. The next following occurrence of ,. Will
     * close the respective tag. The new line character \n is replaced by &lt;br&gt;.
     * Thus, it converts a styled string with custom formatting markers into an HTML-formatted string.
     *
     * @param string $styled The input string containing custom formatting markers.
     * @return string The formatted HTML string with custom styling applied.
     */
    public static function styleToHtml(String $styled): string {
        $styledHtml = "";
        $tagMap = [ "*" => "b", "/" => "i", "_" => "u", "-" => "s", "^" => "sup", "," => "sub", "#" => "code" ];
        $c1 = mb_substr($styled, 0, 1);
        $tag = "";
        $i = 1;
        while ($i < mb_strlen($styled)) {
            $c2 = mb_substr($styled, $i, 1);
            if (($c1 == ",") && array_key_exists($c2, $tagMap))  {
                // open tag
                $openTag = $tagMap[$c2];
                if (! is_null($openTag)) {
                    $styledHtml .= "<$openTag>";
                    $tag = $openTag;
                    $c2 = mb_substr($styled, $i++, 1); // tags replace two characters
                }
                // close tag
                elseif (($c2 == ".") && (strlen($tag) > 0)) {
                    $styledHtml .= "</$tag>";
                    $tag = "";
                    $c2 = mb_substr($styled, $i++, 1); // tags replace two characters
                }
            }  else
                $styledHtml .= $c1;
            $c1 = $c2;
            $i++;
        }
        $styledHtml .= $c1;
        return $styledHtml;
    }

    /**
     * Print an array as html - for debugging
     *
     * @param array $a
     *            any array
     * @param String $indent
     *            the current indentation for recursive calls, never to be used when calling from outside.
     * @return string the html representation
     */
    public static function arrayToHtml (array $a, String $indent = ""): string
    {
        $html = (strlen($indent) == 0) ? "<span style=\"font-family: 'Courier New', monospace; font-size:0.9rem;\">" : "";
        $n = 0;
        foreach ($a as $key => $value) {
            if (is_array($value)) {
                $html .= "$indent<b>$key:</b><br>";
                $html .= self::arrayToHtml($value, $indent . "&nbsp;&nbsp;");
            } else {
                $disp = (is_null($value)) ? "<span style=\"color:#008;\">NULL</span>" : ((is_bool($value)) ? ("<span style=\"color:#808;\">" .
                    (($value) ? "true" : "false") . "</span>") : ((is_string($value)) ? ("<span style=\"color:#088;\">\"" .
                    htmlspecialchars($value) . "\"</span>") : ((is_object($value)) ? ("<span style=\"color:#088;\">\"object: " .
                    get_class($value) . "\"</span>") : $value)));
                $html .= "$indent$key: $disp<br>";
            }
            $n ++;
        }
        if ($n == 0)
            $html .= $indent . "[]<br>";
        if (strlen($indent) == 0)
            $html .= "</span>";
        return $html;
    }

}