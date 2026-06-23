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

namespace Util;

use DateTimeImmutable;
use JetBrains\PhpStorm\NoReturn;

include_once "../../tfyh/Util/ListHandlerKernel.php";

use Control\Sessions;
use Data\DatabaseConnector;
use Data\Formatter;
use Data\ParserName;
use Data\Validator;

/**
 * This class provides a list segment for a web file. <p>The definition must be a CSV-file, all entries
 * without line breaks, with the first line being always "id;permission;name;select;from;where;options" and
 * the following lines the respective values. The values in select, from, where and options are combined to
 * create the needed SQL-statement to retrieve the list elements from the database.</p><p>options
 * are<ul><li>sort=[-]column[.[-]column]: order by the respective column in ascending or descending (-)
 * order</li><li>filter=column.value: filter the column for the given value, always using the LIKE operator
 * with '*' before and after the value</li><li>link=[link]: link the first column to the given url e.g.
 * '../../tfyh/forms/changeUser.php?id=id' replacing the column name at the end (here: id) by the respective
 * value.</li></ul></p> <p>The list is always displayed as a table grid. It will show the default sorting if
 * no sorting option is provided.</p>
 */
class ListHandler extends ListHandlerKernel
{

    /**
     * Limit the size on an entry to a number of characters
     */
    public int $entrySizeLimit = 0;

    /**
     * Build a list set based on the definition provided in the csv file at "../../Config/lists/$set". Use the list with
     * name $nameOrDefinition as the current list name or none, if $name = "", or put your complete set definition to
     * $nameOrDefinition and "@dynamic" to $set to generate a list programmatically. Use the count() function to see
     * whether list definitions could be parsed.
     * @param string $set the name of the list set
     * @param string $nameOrDefinition the name or the the full definition of the list
     * @param array $args the arguments' values to be used the list definition
     */
    public function __construct(string $set, string $nameOrDefinition = "", array $args = []) {
        parent::__construct($set, $nameOrDefinition, $args);
    }

    /**
     * Get the arguments used in a list definition as comma-separated string.
     * @param array $listDefinition the list definition
     * @return string the arguments' values as comma-separated string'
     */
    public function getArgs (array $listDefinition): string
    {
        $args = "";
        foreach ($listDefinition as $value) {
            $brace_open = - 1;
            while ($brace_open !== false) {
                $brace_open = strpos($value, "{", $brace_open + 1);
                $brace_close = ($brace_open === false) ? false : strpos($value, "}", $brace_open);
                if (($brace_close !== false) && ($brace_open < $brace_close))
                    $args .= "," . substr($value, $brace_open + 1, $brace_close - $brace_open - 1);
            }
        }
        if (strlen($args) > 0)
            return substr($args, 1);
        return "";
    }

    /**
     * Return a zip-Download-link for this list based on its definition or the provided options.
     * @param string $oSortsList the list sorting options
     * @param string $oFilter the list filter options
     * @param string $oFValue the list filter value
     * @param int $zipMode the zip mode, will be just prependend to the link. Is 0 for a link to a filter, 1 for a
     * link to a zip or pivot zip.
     * @return string the get parameters for the link to the zip file
     */
    private function getLink(string $oSortsList, string $oFilter, string $oFValue, int $zipMode): string
    {
        if ($this->noValidCurrentList())
            return "?set=" . $this->set . "&name=";
        $sortString = (strlen($oSortsList) == 0) ? "" : "&sort=" . $oSortsList;
        $filterString = (strlen($oFilter) == 0) ? "" : "&filter=" . $oFilter;
        $fValueString = (strlen($oFValue) == 0) ? "" : "&fvalue=" . $oFValue;
        return "?set=" . $this->set . "&name=" . $this->listDefinitions[$this->currentListIndex]["name"] .
            "&zip=" . $zipMode . $sortString . $filterString . $fValueString;
    }

    /**
     * Return the HTML code of this list based on its definition or the provided options.
     * @param string $oSortsList the list sorting options
     * @param string $oFilter the list filter options
     * @param string $oFValue the list filter value
     * @param array $pivot if provided the pivot table will be displayed as well.
     * @return string
     */
    public function getHtml(string $oSortsList, string $oFilter, string $oFValue, array $pivot): string
    {
        if ($this->noValidCurrentList())
            return "<p>" . $this->i18n->t("4t2ytU|Application configuratio...") . "</p>";

        $maxRows = 100;
        $rowsReferenced = $this->getRows("referenced", $oSortsList, $oFilter, $oFValue, $maxRows + 1);
        $errorMessage = "";
        if (!is_array($rowsReferenced)) {
            $errorMessage .= $rowsReferenced;
            $rowsReferenced = [ $rowsReferenced ];
        }
        $countOfListRows = $this->rowsSqlCount;
        $rowsFound = ($countOfListRows > 0);
        $retrievalError = DatabaseConnector::getInstance()->getError();
        $errorMessage .= (strlen($retrievalError) == 0)
            ? "" : I18n::getInstance()->t("cN3sAX|Data retrieval error:" . DatabaseConnector::getInstance()->getError());
        $rowsToShow = (($countOfListRows > $maxRows) ? "&gt;&nbsp;$maxRows" : "$countOfListRows");
        $countNotice = ($rowsFound)
                ? "$rowsToShow   " . $this->i18n->t("C94hEq|Records found.")
                : $this->i18n->t("cwpbfK|The list is empty or no ...") . " " . $errorMessage;
        $countNotice = "<b>" . $countNotice . "</b>";
        if (strlen($errorMessage) > 0)
            $countNotice = "<h5>" . $errorMessage . "</h5>";

        // Find result and usage explanation
        $outHtml = "<p>" . $countNotice;
        if ($countOfListRows > 0)
            $outHtml .= " " . $this->i18n->t("nLel3k|Sort with one click on t...");
        $outHtml .= "</p>";

        // table container
        $tableContainer = "<div style='overflow-x: auto; margin-top:12px; margin-bottom:10px;'>";
        $tableContainer .= "<table style='border: 2px solid transparent;'>";

        // table header
        $tableHeader = "<thead><tr>";
        foreach ($this->columns as $column) {
            // do not display technical ids
            if (($column != "uid") && ($column != "uuid")) {
                // identify ID column for change link
                $cText = ($this->recordItem->hasChild($column)) ? $this->recordItem->getChild($column)->label() : $column;
                $sortsSplit = explode(".", $oSortsList);
                $isAscendingSortColumn = in_array($column, $sortsSplit);
                $isDescendingSortColumn = in_array("-" . $column, $sortsSplit);
                if ($isAscendingSortColumn || $isDescendingSortColumn) {
                    $cText = ($isDescendingSortColumn) ? $cText . '<br /><b>&nbsp;&nbsp;&#9650;</b> ' : $cText .
                        '<br /><b>&nbsp;&nbsp;&#9660;</b> ';
                    $cSort = ($isDescendingSortColumn) ? $column : '-' . $column;
                } else
                    $cSort = $column;
                $ofString = (strlen($oFilter) > 0) ? "&filter=" . $oFilter . "&fvalue=" . $oFValue : "";
                $listParameterStr = (isset($_GET["listparameter"])) ? "&listparameter=" . $_GET["listparameter"] : "";
                $tableHeader .= "<th><a class='table-header' href='?set=" . $this->set . "&name=" . $this->name .
                    "&sort=" . $cSort . $ofString . $listParameterStr . "'>" . $cText . "</a></th>";
            }
        }
        $tableHeader .= "</tr></thead>";

        // table body
        $tableBody = "<tbody>";
        for ($i = 0; ($i < $maxRows) && ($i < $countOfListRows); $i++) {
            $rowReferenced = $rowsReferenced[$i];
            $uid = $rowReferenced["uid"] ?? "";
            $rowHtml = "<tr>";
            $c = 0;
            foreach ($rowReferenced as $column => $valueReferenced)
                // do not display technical ids
                if (($column != "uid") && ($column != "uuid")) {
                    if ((strlen($uid) > 0) && ($c == 0))
                        $rowHtml .= "<td><b><a href='../../tfyh/pages/viewRecord.php?table=" . $this->tableName .
                                "&uid=" . $uid . "'>" . $valueReferenced . "</a></b></td>";
                    else
                        $rowHtml .= "<td>" . $valueReferenced . "</td>";
                    $c++;
                }
            $rowHtml .= "</tr>\n";
            $tableBody .= $rowHtml;
        }
        $tableBody .= "</tbody>";

        // add table to HTML output
        if ($rowsFound)
            $outHtml .= $tableContainer . $tableHeader . $tableBody. "</table></div>";

        // capping notice and filter form
        if ($countOfListRows > 0) {
            $filterForm = "<p>" . $this->i18n->t("ZmqZlm|List capped after %1 rec...", $maxRows)
                . " " . $this->i18n->t("1ci7d1|Please use the filter or...") . "</p>";
            // filter form
            $filterLink = $this->getLink($oSortsList, $oFilter, $oFValue, 0);
            $filterForm .= "<form action='" . $filterLink . "'>" . $this->i18n->t("Rhz5mZ|Filter in column:") .
                "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
            $filterForm .= "<input type='hidden' name='name' value='" . $this->name . "' />";
            $filterForm .= "<input type='hidden' name='set' value='" . $this->set . "' />";
            if (strlen($oSortsList) > 0)
                $filterForm .= "<input type='hidden' name='sort' value='" . $oSortsList . "' />";
            $filterForm .= "<select name='filter' class='formSelector' style='width:20em'>";
            foreach ($this->columns as $column) {
                if (!str_contains($column, ".")) {
                    if (strcasecmp($column, $oFilter) == 0)
                        $filterForm .= '<option value="' . $column . '" selected>' . $column . "</option>\n";
                    else
                        $filterForm .= '<option value="' . $column . '">' . $column . "</option>\n";
                }
            }
            $filterForm .= "</select>";
            $filterForm .= "<br>" . $this->i18n->t("afDbIr|Value") .
                " <input type='text' name='fvalue' class='formInput' value='" . $oFValue .
                "'  style='width:19em' />" . "&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' value='" .
                $this->i18n->t("efjxwi|show filtered list") . "' class='formButton'/></form>";

            $outHtml .= $filterForm;
        }

        // list download link
        $zipLink = $this->getLink($oSortsList, $oFilter, $oFValue, 1);
        $outHtml .= "<p>" . $this->i18n->t("OrvMhQ|get as csv-download file...") . " <a href='" .
            $zipLink . "'>" . $this->tableName . ".zip</a>";
        $outHtml .= ". " . $this->i18n->t("TxBnFe|PLEASE NOTE: Use the inf...") . "</p>";

        // pivot table
        $pivotTableHtml = "";
        if (count($pivot) == 4) {
            $pivotTableHtml .=  "<h4>" . $this->i18n->t("FalTMo|Overview") . "</h4>";
            // pivot table download link
            $pivotZipLink = $this->getLink($oSortsList, $oFilter, $oFValue, 1);
            $pivotTableHtml .=  "<p>" . $this->i18n->t("OrvMhQ|get as csv-download file...") . " <a href='" .
                $pivotZipLink . "'>" . $this->tableName . ".pivot.zip</a>";
            $pivotTableHtml .= ". " . $this->i18n->t("TxBnFe|PLEASE NOTE: Use the inf...") . "</p>";
            // pivot table
            $pivotTable = new PivotTable($this, $pivot[0], $pivot[1], $pivot[2], $pivot[3]);
            $pivotTableHtml .= $pivotTable->getHtml();
        }
        $outHtml .= $pivotTableHtml;

        return $outHtml;
    }

    /**
     * Check whether a field is contained in a list.
     * @param string $fieldName the name of the field to check
     * @return bool true, if the field is contained in the list, false otherwise.
     */
    public function hasField(string $fieldName): bool
    {
        return array_key_exists($fieldName, $this->columns);
    }

    /**
     * Get the name of the table source of the list.
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Check whether a field is contained in a list.
     * @param int $index the index of the column to check
     * @return string|null the name of the column at the provided index, or "???", if the index is out of range.
     */
    public function getColumnName(int $index): ?string
    {
        if ($index < 0 || $index >= count($this->columns))
            return "???";
        return $this->columns[$index];
    }

    /**
     * Provide a csv file, filter, and sorting, according to default. Csv data content is UTF-8 encoded - i.e.
     * uses the data as they are provided by the database.
     * @param string $oSortsList the list sorting options
     * @param string $oFilter the list filter options
     * @param string $oFValue the list filter value
     * @param String|null $onlyFirstOf if provided, only the first occurence of the value will be returned.
     * @return bool|string the csv file content, or false, if no list is currently selected within the set.
     */
    public function getCsv(string $oSortsList, string $oFilter, string $oFValue, String $onlyFirstOf = null): bool|string
    {
        if ($this->noValidCurrentList())
            return false;

        // format and compile list
        $csv = "";
        $records = $this->getRows("csv", $oSortsList, $oFilter, $oFValue);
        $lastChecked = null;
        $firstOf = "";
        $useEntrySizeLimit = ($this->entrySizeLimit > 0);
        $entrySizeLimit = ($this->entrySizeLimit < 10) ? 10 : $this->entrySizeLimit;
        $r = 0;
        foreach ($records as $record) {
            if ($r == 0) {
                foreach ($record as $key => $value)
                    $csv .= ";" . $key;
                $csv = substr($csv, 1) . "\n";
            }
            $rowStr = "";
            foreach ($record as $key => $valueCsv) {
                if ($useEntrySizeLimit && (strlen($valueCsv) > $entrySizeLimit))
                    $valueCsv = substr($valueCsv, 0, $entrySizeLimit - 3) . "...";
                if ((str_contains($valueCsv, "\"")) || (str_contains($valueCsv, "\n")) ||
                    (str_contains($valueCsv, ";")))
                    $rowStr .= '"' . str_replace('"', '""', $valueCsv) . '";';
                else
                    $rowStr .= ";" . $valueCsv;
                if (!is_null($onlyFirstOf) && ($key == $onlyFirstOf))
                    $firstOf = $valueCsv;
            }
            $r++;

            // add line or filter, if requested
            if (!$onlyFirstOf || is_null($lastChecked) || Validator::isEqualValues($firstOf, $lastChecked)) {
                $csv .= substr($rowStr, 1) . "\n";
                $lastChecked = $firstOf;
            }
        }
        return $csv;
    }

    /**
     * Provide a csv file for download. Will not return, but exit via $toolbox->return_string_as_zip()
     * function.
     * @param string $oSortsList the list sorting options
     * @param string $oFilter the list filter options
     * @param string $oFValue the list filter value
     * @return string in case of success this method will not return. When it failed it will return an error
     * message.
     */
    #[NoReturn] public function returnZip(string $oSortsList, string $oFilter, string $oFValue) : string
    {
        if ($this->noValidCurrentList())
            return "<p>" . $this->i18n->t("eG3R6d|Application configuratio...") . "</p>";

        $csv = $this->getCsv($oSortsList, $oFilter, $oFValue);
        // add timestamp, source and destination
        $sessions = Sessions::getInstance();
        $destination = $sessions->userFullName() . " (" . $sessions->userId() . ", " . $sessions->userRole() . ")";
        $csv .= "\n" . $this->i18n->t("JX2kP6|Provided on %1 by %2 to ...",
                Formatter::format(new DateTimeImmutable("now"), ParserName::DATETIME),
                $_SERVER['HTTP_HOST'], $destination) . "\n";
        FileHandler::returnStringAsZip($csv, $this->tableName . ".csv");
    }
}
