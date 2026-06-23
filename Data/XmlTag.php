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
 * class file for a simple XML tag, supporting the Tfyh_xml class.
 */
class XmlTag
{
    public string $id = "";
    public string $attr = "";
    public string $txtOpen = "";
    public string $txtClose = "";
    public bool $isClose = true;
    public ?XmlTag $parent = null;
    public array $children = [];

    public function __construct() {}

    /**
     * Look through the entire branch for the first occurrence of $tagIdToFind. Recursive function.
     * @param string $tagIdToFind the id of the tag to find.
     * @return XmlTag|null the first tag with the given id, or null if not found.
     */
    private function findFirstTagInBranch(string $tagIdToFind): ?XmlTag
    {
        if (strcasecmp($this->id, $tagIdToFind) == 0)
            return $this;
        foreach ($this->children as $child) {
            $found = $child->findFirstTagInBranch($tagIdToFind);
            if (!is_null($found))
                return $found;
        }
        return null;
    }

    /**
     * Converts the hierarchical structure of a specified table root tag into an array representation.
     * Find the tag with the given $tableRootTagId closest to $this and create a table with all records.
     * For it to work, the table root tag must only contain table record tags, and they must not have more
     * than one level of subtag.
     * @param string $tableRootTagId The ID of the table root tag to parse.
     * @return array An array of records, where each record is an associative array of field IDs and their corresponding values.
     */
    public function getAsArray(string $tableRootTagId): array
    {
        // parse table
        $tableRoot = $this->findFirstTagInBranch($tableRootTagId);
        if (is_null($tableRoot))
            return [];
        $fieldNames = [];
        $records = [];
        foreach ($tableRoot->children as $recordXml) {
            $recordArray = [];
            foreach ($recordXml->children as $field) {
                if (!isset($fieldNames[$field->id]))
                    $fieldNames[$field->id] = true;
                $recordArray[$field->id] = $field->txtOpen;
            }
            $records[] = $recordArray;
        }
        return $records;
    }

}
