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

/**
 * This class provides some basic functions to encode and decode data to and from csv, and to and from base64.
 */
class Codec
{
    const BASE62 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

    /*
     * BASE64 TRANSCODING TO UTF-8
     */

    /**
     * Encode the api message
     */
    public static function apiEncode(string $plain): string
    {
        return str_replace("=", ".",
            str_replace("/", "_",
                str_replace("+", "-", base64_encode($plain))));
    }

    /**
     * Decode the api message
     */
    public static function apiDecode(string $encoded): string
    {
        return base64_decode(
            str_replace(".", "=",
                str_replace("_", "/",
                    str_replace("-", "+", $encoded))));
    }

    /*
     * TRANSCODING FROM AND TO CSV
     */

    /**
     * return the character position of the next line end. Skip line breaks in
     * between double quotes. Return the length of csvString if there is no more
     * line break.
     * @param String $csvString the csv file
     * @param int $cLineStart the position of the line start to be used.
     * @return Int the position of the next line end.
     */
    private static function nextCsvLineEnd (String $csvString, int $cLineStart): Int {
        $nextLinebreak = mb_strpos($csvString, "\n", $cLineStart);
        $nextDoubleQuote = mb_strpos($csvString, "\"", $cLineStart);
        $doubleQuotesPassed = 0;
        while ((($nextDoubleQuote !== false) && ($nextLinebreak !== false) && ($nextDoubleQuote < $nextLinebreak))
            || ($doubleQuotesPassed % 2 == 1)) {
            $doubleQuotesPassed++;
            $nextLinebreak = mb_strpos($csvString, "\n", $nextDoubleQuote);
            $nextDoubleQuote = mb_strpos($csvString, "\"", $nextDoubleQuote + 1);
        }
        return ($nextLinebreak === false) ? strlen($csvString) : $nextLinebreak;
    }

    /**
     * Split a csv-formatted line into an array.
     * @param String|null $line the line to be splitted.
     * @param String $separator the separator character.
     * @return array the splitted entries.
     */
    public static function splitCsvRow (String|null $line, String $separator = ";"): array {
        // split entries by parsing the String, it may contain quoted elements.
        $entries = [];
        if (is_null($line))
            return $entries;
        $entryStartPos = 0;

        while ($entryStartPos < mb_strlen($line)) {
            // trim start if blank chars precede a """ character
            while (($entryStartPos < mb_strlen($line)) && (mb_substr($line, $entryStartPos, 1) == ' '))
                $entryStartPos++;
            // Check for quotation
            $entryEndPos = $entryStartPos;
            $quoted = false;
            // while loop to jump over twin double quotes
            $c = mb_substr($line, $entryEndPos, 1);
            while (($entryEndPos < strlen($line)) && ($c == "\"")) {
                $quoted = true;
                // Put pointer to first character after next double quote.
                $entryEndPos = mb_strpos($line, "\"", $entryEndPos + 1) + 1;
                $c = mb_substr($line, $entryEndPos, 1);
            }
            $entryEndPos = mb_strpos($line, $separator, $entryEndPos);
            if ($entryEndPos === false)
                $entryEndPos = mb_strlen($line);

            $entry = mb_substr($line, $entryStartPos, $entryEndPos - $entryStartPos);
            if ($quoted) {
                // remove opening and closing double quotes.
                $entryToParse = mb_substr($entry, 1, mb_strlen($entry) - 2);
                // replace all inner twin double quotes by single double quotes
                $nextSnippetStart = 0;
                $nextDoubleQuote = mb_strpos($entryToParse, "\"\"", $nextSnippetStart);
                $entry = "";
                while ($nextDoubleQuote !== false) {
                    // add the segment to the next twin double quote and the
                    // first double quote in it
                    $entry .= mb_substr($entryToParse, $nextSnippetStart, $nextDoubleQuote - $nextSnippetStart + 1);
                    $nextSnippetStart = $nextDoubleQuote + 2;
                    // continue search after the second of the twin double quotes
                    $nextDoubleQuote = mb_strpos($entryToParse, "\"\"", $nextSnippetStart);
                }
                // add last segment (or full entry if there are no twin
                // double quotes
                $entry .= mb_substr($entryToParse, $nextSnippetStart);
            }
            $entries[] = $entry;
            $entryStartPos = $entryEndPos + 1;
            // if the line ends with a separator char, add an empty entry.
            if ($entryStartPos == mb_strlen($line))
                $entries[] = "";
        }
        return $entries;
    }

    /**
     * Join an array of Strings into a row.
     * @param array $row
     * @param string $separator the separator character.
     * @return string the joined row.
     */
    public static function joinCsvRow (array $row, string $separator = ";"): string {
        $joined = "";
        foreach ($row as $entry)
            $joined .= $separator . self::encodeCsvEntry($entry, $separator);
        return (strlen($joined) == 0) ? "" : substr($joined, 1);
    }

    /**
     * Read a csv String (; and " formatted) into an array<rows><columns>. It is
     * not checked whether all rows have the same column width. This is plain
     * text parsing.
     * @param String $csvFilePath the path to the csv file.
     * @return array the parsed csv file as array.
     */
    public static function csvFileToArray (String $csvFilePath): array {
        $csvString = file_get_contents($csvFilePath);
        if ($csvString === false)
            return [];
        else return self::csvToArray($csvString);
    }

    /**
     * Read a csv String (; and " formatted) into an array<rows><columns>. It is
     * not checked whether all rows have the same column width. This is plain
     * text parsing.
     * @param String|null $csvString the csv file as String.
     * @return array the parsed csv file as array.
     */
    public static function csvToArray (String|null $csvString): array {
        $table = [];
        if ($csvString == null)
            return $table;
        $cLineStart = 0;
        $cLineEnd = self::nextCsvLineEnd($csvString, $cLineStart);
        while ($cLineEnd > $cLineStart) {
            $line = mb_substr($csvString, $cLineStart, $cLineEnd - $cLineStart);
            if (strlen($line) > 0) {
                $entries = self::splitCsvRow($line);
                $table[] = $entries;
            }
            $cLineStart = $cLineEnd + 1;
            if ($cLineStart < strlen($csvString))
                $cLineEnd = self::nextCsvLineEnd($csvString, $cLineStart);
        }
        return $table;
    }

    /**
     * Read a csv String (; and " formatted) into an associative array, where
     * the keys are the entries of the first line. All rows must have the same
     * column width. However, this is not checked. Returns an empty array if the file
     * cannot be read.
     * @param String $csvFilePath the path to the csv file.
     * @return array the parsed csv file as associative array.
     */
    public static function csvFileToMap (String $csvFilePath): array {
        $csvString = file_get_contents($csvFilePath);
        if ($csvString === false)
            return [];
        else return self::csvToMap($csvString);
    }

    /**
     * Read a csv String (; and " formatted) into an associative array, where
     * the keys are the entries of the first line. All rows must have the same
     * column width. However, this is not checked.
     * @param String|null $csvString the csv file as String.
     * @return array the parsed csv file as associative array.
     */
    public static function csvToMap (String|null $csvString): array {
        if (is_null($csvString))
            return [];
        $table = self::csvToArray($csvString);
        $list = [];
        $header = [];
        $r = 0;
        foreach ($table as $rowCsv) {
            if ($r == 0) {
                $header = $rowCsv;
            } else {
                $listRow = [];
                $c = 0;
                foreach ($rowCsv as $entry) {
                    if (isset($header[$c]))
                        // never set more fields than in the header
                        $listRow[$header[$c]] = $entry;
                    $c++;
                }
                $list[] = $listRow;
            }
            $r++;
        }
        return $list;
    }

    /**
     * Encode a csv entry. Uses ";" as a separator default and double quotes (")
     * @param String|null $entry the entry to be encoded.
     * @param String $separator the separator character.
     * @return string the encoded entry.
     */
    public static function encodeCsvEntry (String|null $entry, String $separator = ";"): string
    {
        if (is_null($entry) || strlen($entry) == 0)
            return "";
        if (str_contains($entry, "\n") || str_contains($entry, $separator) ||
            str_contains($entry, "\""))
            return "\"" . str_replace("\"", "\"\"", $entry) . "\"";
        return $entry;
    }

    /**
     * Encode an array into a csv table.
     * @param array $tableRecords the array to be encoded.
     * @return string the encoded table.
     */
    public static function encodeCsvTable (array $tableRecords): string
    {
        if (count($tableRecords) == 0)
            return "";
        $headline = "";
        $keys = [];
        foreach ($tableRecords[0] as $key => $ignored) {
            $keys[] = $key;
            $headline .= ";" . self::encodeCsvEntry($key);
        }
        $csv = substr($headline, 1);
        foreach ($tableRecords as $record) {
            $rowCsv = "";
            foreach ($keys as $key)
                $rowCsv .= ";" . ((isset($record[$key])) ? self::encodeCsvEntry($record[$key]) : "");
            $csv .= "\n" . substr($rowCsv, 1);
        }
        return $csv;
    }

    /**
     * Transform an array of rows into a table formatted as html. The first row contains the headline.
     * @param array $table
     *             the table which shall be transformed
     * @return String representing the table-html code
     */
    public static function tableToHtml(array $table, bool $headline_on): String
    {
        // create the layout
        $html = "<table>";
        if ($headline_on) {
            $html .= "<thead><tr>";
            for ($c = 0; $c < count($table[0]); $c++)
                $html .= "<th>" . $table[0][$c] . "</th>";
            $html .= "</tr></thead>";
        }
        $html .= "<tbody>";
        for ($r = 1; $r < count($table); $r++) {
            $html .= "<tr>";
            for ($c = 0; $c < count($table[$r]); $c++)
                $html .= "<td>" . $table[$r][$c] . "</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";
        return $html;
    }

    /**
     * Transform an array of rows into a csv table. The first row contains the headline,
     * if $withHeadline == true
     * @param array $table the table which shall be transformed.
     * @param bool $headline_on if true, the first row contains the headline.
     * @return string representing the table-csv code.
     */
    public static function tableToCsv(array $table, bool $headline_on): string
    {
        // create the layout
        $csv = "";
        if ($headline_on) {
            for ($c = 0; $c < count($table[0]); $c++)
                $csv .= ";" . self::encodeCsvEntry($table[0][$c]);
            $csv = "\n" . substr($csv, 1);
        }
        for ($r = 1; $r < count($table); $r++) {
            for ($c = 0; $c < count($table[0]); $c++)
                $csv .= ";" . self::encodeCsvEntry($table[$r][$c]);
            $csv =  "\n" . substr($csv, 1);
        }
        return substr($csv, 1);
    }

    /*
    * TRANSCODING the html special characters like in PHP native
    */

    /**
     * Apply the PHP htmlspecialchars() encoding. Implemented for consistency with JavaScript and kotlin.
     */
    public static function htmlSpecialChars(String $plain): String {
        return htmlspecialchars($plain);
    }
    /**
     * Revert the PHP htmlspecialchars() encoding. Implemented for consistency with JavaScript and kotlin.
     */
    public static function htmlSpecialCharsDecode(String $encoded): String {
        return htmlspecialchars_decode($encoded);
    }
}