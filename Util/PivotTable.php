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

namespace tfyh\util;

use tfyh\data\Parser;
use tfyh\data\ParserConstraints;

/**
 * PivotTable provided pivoting tables for the Lists class. Currently
 * only one row field, one column field, and one data field with aggregation = sum or cnt.
 */
class PivotTable
{

    /**
     * The pivot table. Two-dimensional associative array of double.
     */
    private array $pivotTable;

    private String $rowColumn;
    private String $columnColumn;
    private String $dataColumn;

    private array $rowItems;
    private array $columnItems;
    private int $aggregationMode;
    private String $emptyString;

    /**
     * The constructor. Reads the list and builds the table upon construction.
     *
     * @param ListHandler $list
     *            the list to be pivoted
     * @param String $rowField
     *            the row items field
     * @param String $columnField
     *            the column items field
     * @param String $dataField
     *            the data field (must be numeric for aggregation method "sum")
     * @param String $aggregation
     *            the data aggregation method ("sum" or "cnt")
     */
    public function __construct (ListHandler $list, String $rowField, String $columnField,
                                 String      $dataField, String $aggregation)
    {
        $this->aggregationMode = (strcasecmp($aggregation, "sum") == 0) ? 1 : 0;
        $this->emptyString = I18n::getInstance()->t("izUp6M|(empty)");
        $this->pivotTable = [];
        $this->rowColumn = $rowField;
        $this->columnColumn = $columnField;
        $this->dataColumn = $dataField;
        $this->columnItems = [];
        // extract the record field pivot items. Create a record within the pivot table for each.
        $this->rowItems = $this->extractPivotItems($list, $rowField);
        // extract the column field pivot items. Create a record within the pivot table for each.
        $this->columnItems = $this->extractPivotItems($list, $columnField);
        // initialise the pivot table
        $this->pivotTable = [];
        foreach ($this->rowItems as $rItem) {
            $this->pivotTable[$rItem] = [];
            foreach ($this->columnItems as $cItem) {
                $this->pivotTable[$rItem][$cItem] = 0;
            }
        }
        // sum up or count the data field.
        foreach ($list->getRows("localized") as $record)
            $this->aggregateData($record);
    }

    /**
     * Aggregate a pivot data value into the pivot table.
     * @param array $record the record to aggregate
     * @return void
     */
    private function aggregateData (array $record): void
    {
        $rItem = $record[$this->rowColumn];
        if (strlen($rItem) == 0)
            $rItem = $this->emptyString;
        $cItem = $record[$this->columnColumn];
        if (strlen($cItem) == 0)
            $cItem = $this->emptyString;
        $value = $record[$this->dataColumn];
        if (! ParserConstraints::isEmpty($value, Parser::nativeToParser($value))) {
            if ($this->aggregationMode == 0) // count
                $this->pivotTable[$rItem][$cItem] ++;
            elseif ($this->aggregationMode == 1) // sum
                $this->pivotTable[$rItem][$cItem] += $value;
        }
    }

    /**
     * Extract the pivot set of a column of the list
     * @param ListHandler $list the list to extract the pivot set from
     * @param String $column the column to extract the pivot set from
     * @return array the pivot set
     */
    private function extractPivotItems (ListHandler $list, String $column): array
    {
        $pivotItems = [];
        // extract the colum field pivot items. Create a row within the pivot table for each.
        foreach ($list->getRows("localized") as $record) {
            $entry = $record[$column];
            if (strlen($entry) == 0)
                $entry = $this->emptyString;
            $found = false;
            foreach ($pivotItems as $pivotItem)
                if (strcasecmp($pivotItem, $entry) == 0)
                    $found = true;
            if (! $found)
                $pivotItems[] = $entry;
        }
        sort($pivotItems);
        return $pivotItems;
    }

    /**
     * Get the pivot table as an HTMl-formatted string for web display.
     *
     * @param String $format
     *            number format for data, see native sprintf() for format String definitions.
     *            Default is "%d".
     * @return string pivot table as html String.
     */
    public function getHtml (String $format = "%d"): string
    {
        // print header, top left corner is empty
        $html = "<table style='border: 2px solid'><tr><td style='padding-right: 5px;border: 1px solid;'>&nbsp;</td>";
        foreach ($this->columnItems as $cItem) {
            $html .= "<td style='padding-right: 5px;border: 1px solid;'>" . $cItem . "</td>";
        }
        $html .= "</tr>\n";
        // print rows
        ksort($this->pivotTable);
        foreach ($this->pivotTable as $rItem => $prow) {
            $html .= "<tr><td style='padding-right: 5px;'>" . $rItem . "</td>";
            foreach ($prow as $value)
                $html .= "<td style='padding-right: 5px;text-align:center'>" .
                         sprintf($format, $value) . "</td>";
            $html .= "</tr>\n";
        }
        return $html . "</table>";
    }

    /**
     * Get the pivot table as csv-String for download.
     *
     * @param String $format
     *            number format for data, see native sprintf() for format String definitions.
     *            Default is "%d".
     * @return string pivot table as csv String.
     */
    public function getCsv (String $format = "%d"): string
    {
        // print header, top left corner is empty
        $csv = "";
        foreach ($this->columnItems as $cItem)
            $csv .= ";" . $cItem;
        $csv .= "\n";
        // print rows
        ksort($this->pivotTable);
        foreach ($this->pivotTable as $rItem => $prow) {
            $csv .= $rItem;
            foreach ($prow as $value)
                $csv .= ";" . sprintf($format, $value);
            $csv .= "\n";
        }
        return $csv;
    }
}
    
