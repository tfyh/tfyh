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
include_once "../_Util/Pdf.php";
include_once "../_Util/PdfAdapted.php";

use tfyh\data\Config;
use tfyh\data\DatabaseConnector;
use tfyh\data\Formatter;
include_once "../_Data/Config.php";
include_once "../_Data/DatabaseConnector.php";
include_once "../_Data/Formatter.php";

/**
 * A class to produce a pdf based on the html layout and a set of data from the database.
 */
class Pdf
{

    public function __construct() {}

    /**
     * Create a pdf based on table data
     * @param string $templateName the name of the template file, without the .html extension.
     * @param string $subject the subject of the pdf.
     * @param int $id the id of the data set to use.
     * @param array $directValues an array of values to use directly, without looking up the data set.
     * @return string the path to the created pdf file.
     */
    public function createPdf(string $templateName, string $subject, int $id,
                              array  $directValues = array()): string
    {
        $templatePath = "../Templates/" . $templateName . ".html";
        $html = $this->fillHtmlTemplate($templatePath, $id, $directValues);
        // vvv for debugging purposes, if the PDF is empty.
        // file_put_contents("../Pdfs/" . $template_name . "_" . $id . ".html", $html);
        // ^^^ for debugging purposes, if the PDF is empty.
        $pdfPath = "../Pdfs/" . $templateName . "_" . $id . ".pdf";
        $this->convertHtmlToPdf($html, $pdfPath, $templateName, $subject);
        chmod($pdfPath, 0766);
        // vvv for debugging purposes, if the PDF is empty.
        // copy($pdfPath, $pdfPath . ".tmp");
        // ^^^ for debugging purposes, if the PDF is empty.
        return $pdfPath;
    }

    /**
     * Create a pdf based on the HTML code provided.
     * @param string $html the HTML code to use.
     * @param string $title the title of the pdf.
     * @param string $subject the subject of the pdf.
     * @return string the path to the created pdf file.
     */
    public function createPdfFromHtml(string $html, string $title, string $subject): string
    {
        // vvv for debugging purposes, if the PDF is empty.
        // file_put_contents("../Pdfs/" . $template_name . "_" . $id . ".html", $html);
        // ^^^ for debugging purposes, if the PDF is empty.
        $pdfPath = "../Pdfs/" . Formatter::toIdentifier($title) . ".pdf";
        $this->convertHtmlToPdf($html, $pdfPath, $title, $subject);
        chmod($pdfPath, 0766);
        // vvv for debugging purposes, if the PDF is empty.
        // copy($pdfPath, $pdfPath . ".tmp");
        // ^^^ for debugging purposes, if the PDF is empty.
        return $pdfPath;
    }

    /**
     * Remove all created pdf files. To be used by cron jobs.
     * @return void
     */
    public static function clearAllCreatedFiles(): void
    {
        if (file_exists("../pdfs")) {
            $files = scandir("../pdfs");
            if ($files !== false)
                foreach ($files as $file)
                    if (strcmp(substr($file, 0, 1), ".") != 0)
                        unlink("../Pdfs/$file");
        }
    }

    /**
     * Create a pdf document from the provided html String. Margins, footer text, and document author
     * are taken from the util configuration.
     * @param string $html the html code to use.
     * @param string $filePath the path to the pdf file to be created.
     * @param string $title the title of the pdf.
     * @param string $subject the subject of the pdf.
     * @return void
     */
    private function convertHtmlToPdf(string $html, string $filePath, string $title, string $subject): void
    {

        $config = Config::getInstance();
        // Create the PDF document
        $pdf = new PdfAdapted(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->footerText = $config->getItem(".framework.pdf.footer_text")->value();

        // load TCPDF library
        require_once('../Tcpdf/tcpdf.php');

        // add document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($config->getItem(".framework.app.name")->value());
        $pdf->SetTitle($title);
        $pdf->SetSubject($subject);

        // add header und footer information
        $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // Set margins
        $margins = $config->getItem(".framework.pdf.margins")->value();
        $pdf->SetMargins($margins[0], $margins[1], $margins[2], true);
        $pdf->SetHeaderMargin($margins[3]);
        $pdf->SetFooterMargin($margins[4]);

        // remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter();

        // automatic page breaks
        $pdf->SetAutoPageBreak(TRUE, 15);

        // image scale
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // font
        $pdf->SetFont('dejavusans', '', 9);

        // add a new page to start
        $pdf->AddPage();

        // Insert the HTML code into the PDF document
        $pdf->writeHTML($html, true, false, true);

        // Store the PDF document in the directory
        $saved_at = dirname(__FILE__) . '/' . $filePath;
        $pdf->Output($saved_at, 'F');
    }

    /**
     * Fills an HTML template with values based on the provided dataset and direct values.
     * Template placeholders in the format `{#key#}` or `{#object.property#}` are replaced with corresponding values.
     * Data is retrieved from a database or directly from the provided array.
     *
     * @param string $templatePath The file path to the HTML template.
     * @param int $id The primary identifier used to fetch associated data from the database.
     * @param array $directValues An associative array of direct values used to replace placeholders in the template.
     * @return string The fully processed HTML template with all placeholders replaced by corresponding values.
     */
    private function fillHtmlTemplate(string $templatePath, int $id, array $directValues): string
    {
        $dbc = DatabaseConnector::getInstance();
        // read template and fill in
        $templateString = file_get_contents($templatePath);
        $filledString = "";
        $snippetStart = 0;
        $snippetEnd = strpos($templateString, "{#");
        $dataSet = null;
        while ($snippetEnd !== false) {
            // copy snippet and get find-value
            $filledString .= substr($templateString, $snippetStart,
                $snippetEnd - $snippetStart);
            $tokenStart = $snippetEnd + 2;
            $tokenEnd = strpos($templateString, "#}", $tokenStart);
            $findString = substr($templateString, $tokenStart, $tokenEnd - $tokenStart);
            $findElements = explode(".", $findString);
            $snippetStart = $tokenEnd + 2;
            $snippetEnd = strpos($templateString, "{#", $snippetStart);
            if (count($findElements) == 1) {
                // use provided values of $direct_values array
                $valueToUse = $directValues[$findElements[0]];
            } else {
                // find direct data set
                if (!isset($dataSet[$findElements[0]]))
                    $dataSet[$findElements[0]] = $dbc->find($findElements[0], "transactionId", $id);
                // look up secondary data set, if needed
                if (count($findElements) == 3) {
                    $secondary_table_path = $findElements[0] . "." . $findElements[1];
                    if (!isset($dataSet[$secondary_table_path])) {
                        $secondary_id = $dataSet[$findElements[0]][$findElements[1]];
                        $dataSet[$secondary_table_path] = $dbc->find($findElements[1], "transactionId", $secondary_id);
                    }
                    $valueToUse = $dataSet[$secondary_table_path][$findElements[2]];
                } else {
                    $valueToUse = $dataSet[$findElements[0]][$findElements[1]];
                }
            }
            // add value to filled string and continue
            $filledString .= (strlen($valueToUse) == 0) ? "-" : $valueToUse;
        }
        // add the remainder of the template to the filled template.
        $filledString .= substr($templateString, $snippetStart,
            strlen($templateString) - $snippetStart);
        return $filledString;
    }
}
