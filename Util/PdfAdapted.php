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

use TCPDF;
include_once "../../Tcpdf/tcpdf.php";

/**
 * An extended TCPDF class that provides custom footer functionality for PDF generation.
 */
class PdfAdapted extends TCPDF
{
    public string $footerText;

    // Page footer
    public function Footer(): void
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10,
            $this->footerText . "         " . $this->getAliasNumPage() .
            '/' . $this->getAliasNbPages(), 0, false, 'C', 0);
    }
}
