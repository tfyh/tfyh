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

namespace tfyh\data;
use tfyh\util\Language;

include_once "../_Data/Validator.php";

/**
 * Class file to maintain a word index, referencing to database records. The index itself is a database
 * table. PHP-only implementation.
 */
class WordIndex
{

    /**
     * To separate the words and create an index of the text, we need to parse the text, separate the
     * words, and transcribe the characters to ASCII to avoid sorting and misspelling problems. Characters which
     * are not listed are used as in-word characters and replaced by an underscore '_'.
     */
    private static array $charBlocks = ["stay" => "abcdefghijklmnopqrstuvwxyz0123456789_-",
        // characters which stay one character
        "from_1" => "ABCDEFGHIJKLMNOPQRSTUVWXYZÁÀÂÄÇÉÈÊËÍÌÎÏÑÓÒÔÚÙÛŸáàâçéèêëíìîïñóòôúùûÿ",
        "to_1" => "abcdefghijklmnopqrstuvwxyzaaaaceeeeiiiinooouuuyaaaceeeeiiiinooouuuy",
        // special characters which become two characters
        "from_2" => "ÅÄÆØÖŒÜåäæøöœüß", "to_2" => "AaAeAeOeOeOeUeaaaeaeoeoeoeuess",
        // word separator characters
        "separate" => " ',;.:#+*/=§$%&@€|<>(){}[]?!`\"\f\n\r\t\v\\"
    ];

    /**
     * The minimum length of a wor to become part of the index, usually 3+
     *
     * @var integer
     */
    const MIN_WORD_LENGTH = 3;

     /**
     * The index which leads from the uid to the record.
     */
    private array $uids;

    /**
     * cache for the words and uuids of a record
     */
    private array $extracted;

    /**
     * cache for the uids of the search result;
     */
    public array $findResult;

    /**
     * The index.
     */
    private array $words;

    private string $indexDir = "../Run/index";

    private string $buildLog = "../Run/index/word_index.log";

    private string $wordsFile = "../Run/index/words";

    private string $uidsFile = "../Run/index/uids";

    public function __construct() {}

    /**
     * Find the words and return all uids of records matching. This will not look at any user permission. The
     * result ist stored at "$this->find_result"
     * @param string $words a text with the words to search for.
     * @param bool $logicalAnd if true, the result will be the intersection of all words. If false, the result will be
     * the union of all words.
     * @param bool $like if true, the words are treated as wildcards. If false, the words are treated as exact matches.
     * @return void
     */
    public function find(string $words, bool $logicalAnd, bool $like): void
    {
        $words = $this->extractWords($words);
        $this->readIndex();
        $searchResultAllWords = [];
        // iterate over all words
        foreach ($words as $word) {
            $searchResultThisWord = [];
            // uids have a separate index for not to be case-sensitive
            if (Ids::isUid($word)) {
                foreach ($this->uids as $uid => $tableName) {
                    $useIt = (strcmp($uid, $word) == 0) || ($like && (str_contains($uid, $word)));
                    if ($useIt && !in_array($uid, $searchResultThisWord))
                        $searchResultThisWord[] = $uid;
                }
            }
            $wordLowerAscii = self::toLowerAscii($word);
            foreach ($this->words as $wordOfIndex => $uids) {
                $useIt = (strcmp($wordOfIndex, $wordLowerAscii) == 0) ||
                    ($like && (str_starts_with($wordOfIndex, $wordLowerAscii)));
                if ($useIt)
                    $searchResultThisWord = array_unique(
                        array_merge($searchResultThisWord, $uids));
            }
            if ($logicalAnd && (count($searchResultAllWords) > 0))
                // if logical and intersect, but starting from the second word. If not, this will always intersect
                // with an empty array.
                $searchResultAllWords = array_intersect($searchResultAllWords,
                    $searchResultThisWord);
            else
                $searchResultAllWords = array_unique(
                    array_merge($searchResultAllWords, $searchResultThisWord));
        }

        // get the result usably
        $language = Config::getInstance()->language();
        $this->findResult = [];
        foreach ($searchResultAllWords as $uid) {
            // the $this->find_result is a two-level array: per table and per record
            $tableName = $this->uids[$uid];
            $record = [];
            if (!isset($this->findResult[$tableName]))
                $this->findResult[$tableName] = [];
            if (isset($tableName)) {
                // read the relevant fields of the record
                $recordItem = Config::getInstance()->getItem(".tables." . $tableName);
                $recordHandler = new Record($recordItem);
                $templateColumns = $recordHandler->templateFields("short");
                $sql = "SELECT `uid`";
                $columns = [ "uid" ];
                foreach ($recordItem->getChildren() as $child) {
                    $handling = $child->nodeHandling();
                    if (str_contains($handling, "w") || in_array($child->name(), $templateColumns)) {
                        $columns[] = $child->name();
                        $sql .= ", `" . $child->name() . "`";
                    }
                }
                $row = [];
                $sql = $sql . " FROM `" . $tableName . "` WHERE `uid` = '" . $uid . "';";
                $res = DatabaseConnector::getInstance()->customQuery($sql, $this);
                if (isset($res->num_rows) && (intval($res->num_rows) > 0))
                    $row = $res->fetch_row();

                $c = 0;
                $toParse = [];
                foreach ($columns as $column)
                    $toParse[$column] = $row[$c++];
                $recordHandler->parse($toParse, Language::SQL);
                $record["@short"] = $recordHandler->recordToTemplate("short");
                $record["@in_fields"] = "";
                $record["@display_as"] = "";
                $c = 0;
                foreach ($columns as $column) {
                    if (!is_null($row[$c]) && $recordItem->hasChild($column)) {
                        $record[$column] = $row[$c];
                        $child = $recordItem->getChild($column);
                        $label = $child->label();
                        foreach ($words as $word)
                            if (str_contains(self::toLowerAscii($row[$c]), $word)) {
                                $record["@in_fields"] .= $label . ", ";
                                $valueNative = Parser::parse($row[$c], $child->type()->parser(), $language);
                                $displayAs = Formatter::format($valueNative, $child->type()->parser());
                                $record["@display_as"] .= $displayAs . ", ";
                                $record["@bold_where_is"] = self::boldWhereIs($displayAs, $word);
                            }
                    }
                    $c++;
                }
            }
            $this->findResult[$tableName][$uid] = $record;
        }
    }

    /**
     * Read the index from the file.
     */
    private function readIndex(): void
    {
        $this->words = [];
        $this->uids = [];
        $wordsFile = file_get_contents($this->wordsFile);
        if ($wordsFile === false)
            return;
        $uidsFile = file_get_contents($this->uidsFile);
        if ($uidsFile === false)
            return;
        $wordsLines = explode("\n", $wordsFile);
        foreach ($wordsLines as $wordsLine)
            if (strlen($wordsLine) > 0) {
                $posComma = strpos($wordsLine, ",");
                $word = substr($wordsLine, 0, $posComma);
                $this->words[$word] = explode(",", substr($wordsLine, $posComma + 1));
            }
        $uidsLines = explode("\n", $uidsFile);
        foreach ($uidsLines as $uidsLine)
            if (strlen($uidsLine) > 0) {
                $parts = explode(",", $uidsLine);
                $this->uids[$parts[0]] = $parts[1];
            }
    }

    /**
     * Filter a word and add it to either the uuid-index or the word-index.
     * @param string $word the word to add.
     * @return void
     */
    private function addWord(string $word): void
    {
        $isUuid = Ids::isUuid($word);
        $isUid = Ids::isUid($word);
        if ($isUuid) {
            // for UUID and uid stay case-sensitive
            if (!in_array($word, $this->extracted))
                $this->extracted[] = $word;
        } elseif ($isUid && isset($this->uids[$word])) {
            // for UUID and uid stay case-sensitive
            if (!in_array($word, $this->extracted))
                $this->extracted[] = $word;
        } else {
            $snippets = explode("-", $word);
            foreach ($snippets as $snippet)
                if ((strlen($snippet) >= self::MIN_WORD_LENGTH) && !is_numeric($snippet) && !in_array(
                        $snippet, $this->extracted))
                    $this->extracted[] = $snippet;
        }
    }

    /**
     * Find the needle in a text and mark that part of the text boldface (HTML)
     * @param String $haystack the text to search in.
     * @param String $needle the needle to find.
     * @return String the text with the needle marked boldface.
     */
    public static function boldWhereIs(String $haystack, String $needle): String {
        $needleAscii  = self::toLowerAscii($needle);
        $needleLength  = strlen($needle);
        $haystackAscii = self::toLowerAscii($haystack);
        // match backwards, because positions change after replacement
        $matchedPosition = strrpos($haystackAscii, $needleAscii);
        while ($matchedPosition !== false) {
            // enclose the needle with <b></b> tags
            $haystack = substr($haystack, 0, $matchedPosition) .
                "<b>" . substr($haystack, $matchedPosition, $needleLength) . "</b>" .
                substr($haystack, $matchedPosition+ $needleLength);
            // search the front remainder
            $haystackAscii = self::toLowerAscii(substr($haystack, 0, $matchedPosition));
            $matchedPosition = strrpos($haystackAscii, $needleAscii);
        }
        return $haystack;
    }

    /**
     * Transform a word into its ASCII representation by replacing special characters like ä, é with ae, e and
     * putting the whole word into lower case.
     * @param string $word the word to transform.
     * @return string the word in lower case ASCII.
     */
    public static function toLowerAscii(string $word): string
    {
        $len = mb_strlen($word);
        $wordAscii = "";
        for ($i = 0; $i < $len; $i++) {
            $c = mb_substr($word, $i, 1);
            // check character. THose, which need no handling first.
            if (mb_strpos(self::$charBlocks["stay"], $c) !== false)
                $wordAscii .= $c;
            else {
                // check character. Special characters which are extended to two characters
                $posFrom2 = mb_strpos(self::$charBlocks["from_2"], $c);
                if ($posFrom2 !== false)
                    $wordAscii .= mb_substr(self::$charBlocks["to_2"], $posFrom2 * 2, 2);
                else {
                    // check character. Special characters which are replaced by a single character
                    $posFrom1 = mb_strpos(self::$charBlocks["from_1"], $c);
                    if ($posFrom1 !== false)
                        $wordAscii .= mb_substr(self::$charBlocks["to_1"], $posFrom1, 1);
                    else
                        // not in the character's list of characters with known handling. replace it by
                        // underscore.
                        $wordAscii .= "_";
                }
            }
        }
        return $wordAscii;
    }

    /**
     * Extract all word from a text. Lower case characters are kept, Upper case characters are converted to lower
     * case characters, and special characters are replaced by proper transcoding. Words are separated and collected into a
     * deduplicated array.
     */
    private function extractWords(string $text): array
    {
        $this->extracted = [];
        $word = "";
        $len = mb_strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $c = mb_substr($text, $i, 1);
            if (mb_strpos(self::$charBlocks["separate"], $c) !== false) {
                $this->addWord(self::toLowerAscii($word));
                $word = "";
            } else
                $word .= $c;
        }
        // add the last word, if not empty (e.g. because the text ended with a separator)
        if (strlen(self::toLowerAscii($word)) > 0)
            $this->addWord(self::toLowerAscii($word));
        return $this->extracted;
    }

    /**
     * Build or rebuild the word index. This will delete the respective index files
     */
    public function rebuild(): void
    {
        if (!file_exists($this->indexDir))
            mkdir($this->indexDir);
        file_put_contents($this->buildLog, date("Y-m-d H:i:s") . ": Starting word index build.\n");
        $startTime = time();

        // iterate over all tables
        $this->words = [];
        $this->uids = [];
        $config = Config::getInstance();
        $tablesItem = $config->getItem(".tables");
        foreach ($tablesItem->getChildren() as $recordItem) {
            file_put_contents($this->buildLog, date("Y-m-d H:i:s") . ": Reading " . $recordItem->name() . ": ",
                FILE_APPEND);
            // collect all word index relevant fields from the configuration
            $sql = "SELECT `uid`, ";
            $c = 0;
            foreach ($recordItem->getChildren() as $columnItem) {
                $handling = $columnItem->nodeHandling();
                if (str_contains($handling, "w")) {
                    $c++;
                    $sql .= "`" . $columnItem->name() . "`, ";
                }
            }
            if ($c > 0) {
                file_put_contents($this->buildLog, date("Y-m-d H:i:s") . ": Analysing " . $recordItem->name() . ": ",
                    FILE_APPEND);
                $sql = mb_substr($sql, 0, mb_strlen($sql) - 2) . " FROM `" . $recordItem->name() .
                    "`WHERE 1;";
                $res = DatabaseConnector::getInstance()->customQuery($sql, $this);
                $r = 0;
                if (isset($res->num_rows) && (intval($res->num_rows) > 0)) {
                    $row = $res->fetch_row();
                    while ($row) {
                        $r++;
                        if (($r % 100) == 0)
                            file_put_contents($this->buildLog, ".", FILE_APPEND);
                        if (($r % 500) == 0)
                            file_put_contents($this->buildLog, " ", FILE_APPEND);
                        $uid = $row[0];
                        if (!isset($this->uids[$uid]))
                            $this->uids[$uid] = $recordItem->name();
                        for ($i = 1; $i <= $c; $i++) {
                            $text = $row[$i];
                            if (isset($text)) {
                                $this->extractWords($text);
                                foreach ($this->extracted as $word) {
                                    if (!isset($this->words[$word]))
                                        $this->words[$word] = [];
                                    if (!in_array($uid, $this->words[$word]))
                                        $this->words[$word][] = $uid;
                                }
                            }
                        }
                        $row = $res->fetch_row();
                    }
                }
                file_put_contents($this->buildLog,
                    "\n" . $recordItem->name() . " completed. $r records analysed. Words so far: " . count($this->words) .
                    "\n", FILE_APPEND);
            } else
                file_put_contents($this->buildLog, date("Y-m-d H:i:s") . ": Skipping $recordItem->name().\n",
                    FILE_APPEND);
        }

        // store the result
        $wordsFile = "";
        foreach ($this->words as $word => $uids) {
            $wordsFile .= $word;
            foreach ($uids as $uid)
                $wordsFile .= "," . $uid;
            $wordsFile .= "\n";
        }
        file_put_contents($this->wordsFile, $wordsFile);
        $uidsFile = "";
        foreach ($this->uids as $uid => $tableName)
            $uidsFile .= $uid . "," . $tableName . "\n";
        file_put_contents($this->uidsFile, $uidsFile);

        file_put_contents($this->buildLog,
            date("Y-m-d H:i:s") . ": Completed word index build in " . (time() - $startTime) .
            " seconds.\n", FILE_APPEND);
    }
}


