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

/**
 * class file for simple XML reading without library support.
 */
class Xml
{
    public array $tagIds = [];
    public string $encoding = "";
    public ?XmlTag $dataRoot = null;
    private string $xml = "";
    private int $l = 0;

    public function __construct() {}

    /**
     * Read an XML tag within an eFa file and its following text. No self-closing tags are allowed.
     */
    private function readTag(): ?XmlTag
    {
        // find tag itself
        $posLt = strpos($this->xml, "<", $this->l);
        $posGt = strpos($this->xml, ">", $posLt + 1);

        // none to come, return false
        if (($posLt === false) || ($posGt === false))
            return null;
        // get tag id and attributes (no attribute parsing)
        $idAttr = substr($this->xml, $posLt + 1, $posGt - $posLt - 1);
        $id = explode(" ", $idAttr, 2)[0];
        $attr = (!str_contains($idAttr, " ")) ? "" : explode(" ", $idAttr, 2)[1];
        // get the following text and classify the tag as open or close
        $posLt = strpos($this->xml, "<", $posGt + 1);
        if ($posLt === false) {
            $txt = "";
            $this->l = strlen($this->xml);
        } else {
            $txt = substr($this->xml, $posGt + 1, $posLt - $posGt - 1);
            $this->l = $posLt;
        }
        // cleanse the text and put it to the correct position
        $txt = trim(str_replace("  ", " ", str_replace("  ", " ", str_replace("\n", " ", $txt))));
        $tag = new XmlTag();
        $tag->id = $id;
        $tag->attr = $attr;
        if (str_starts_with($id, "/")) {
            $tag->txtClose = $txt;
            $tag->isClose = true;
        } else {
            $tag->txtOpen = $txt;
            $tag->isClose = false;
        }
        // add the id to the flat ids list.
        if (!$tag->isClose)
            if (!isset($this->tagIds[$id]))
                $this->tagIds[$id] = 1;
            else
                $this->tagIds[$id]++;
        return $tag;
    }

    /**
     * Read an XML string into $this->xmlTree. Encoding must be UTF-8.
     * @param string $xml the xml string to be read.
     * @return XmlTag|null the root tag of the xml tree, or null if the xml string is invalid.
     */
    public function readFile(string $xml): ?XmlTag
    {
        $this->tagIds = [];
        $this->l = 0;
        $this->xml = $xml;
        $isUtf8 = mb_check_encoding($xml, "UTF-8");
        if (!$isUtf8)
            $this->xml = mb_convert_encoding($xml, 'UTF-8', 'ISO-8859-1');

        // read the first tag. skip it if it is the xml definition but use the encoding information.
        $this->dataRoot = $this->readTag();
        if (is_null($this->dataRoot))
            return null;
        if (strcasecmp($this->dataRoot->id, "?xml") == 0)
            $this->dataRoot = $this->readTag();
        if (is_null($this->dataRoot))
            return null;

        // read tree recursively from root.
        $closeTag = $this->dataRoot;
        do {
            // read the tag
            $tag = $this->readTag();
            if (!is_null($tag)) {
                // there was a tag, handle it.
                if ($tag->isClose) {
                    // hand over to parent on closing
                    $closeTag->txtClose = $tag->txtClose;
                    $closeTag = $closeTag->parent;
                } else {
                    // add child on opening
                    $closeTag->children[] = $tag;
                    // add parent to new tag
                    $tag->parent = $closeTag;
                    // change current context to the new tag
                    $closeTag = $tag;
                }
            }
        } while (! is_null($tag));
        return $this->dataRoot;
    }

    /**
     * Write an xml-String based on the provided root tag. No <?xml ...> header tag is included. Generates an array of
     * strings representing the XML structure of the given tag and its children.
     *
     * @param XmlTag $xmlTag The XML tag to be written, including its attributes, text, and children.
     * @param string $indent The indentation to be applied to the generated lines, for proper formatting.
     * @return array An array of strings where each item represents a formatted line of XML code.
     */
    public function writeFile(XmlTag $xmlTag, string $indent): array
    {
        // write open tag including attributes in first line
        $openTag = $indent . "<" . $xmlTag->id;
        if (strlen($xmlTag->attr) > 0)
            $openTag .= " " . $xmlTag->attr;
        $xmlLines = [];

        // leaf tags (i.e. without children) are put to a single line, and the text
        // following the close tag is added in a second line, if existing.
        if (count($xmlTag->children) == 0) {
            $xmlLines[] = $openTag . ">" . $xmlTag->txtOpen . "</" . $xmlTag->id . ">";
            if (strlen($xmlTag->txtClose) > 0)
                $xmlLines[] = $indent . $xmlTag->txtClose;
            return $xmlLines;
        }

        // branch tags, first line is tag id.
        $xmlLines[] = $openTag . ">";
        // write text following the open tag in the second line, if existing
        if (strlen($xmlTag->txtOpen) > 0)
            $xmlLines[] = $indent . "  " . $xmlTag->txtOpen;
        // add all children, recursively
        foreach ($xmlTag->children as $childTag) {
            $childLines = $this->writeFile($childTag, "  " . $indent);
            $xmlLines = array_merge($xmlLines, $childLines);
        }
        // write close tag in second or third line
        $xmlLines[] = $indent . "</" . $xmlTag->id . ">";
        // write text following the close tag in the third/fourth line, if existing
        if (strlen($xmlTag->txtClose) > 0)
            $xmlLines[] = $indent . $xmlTag->txtClose;
        return $xmlLines;
    }
}
