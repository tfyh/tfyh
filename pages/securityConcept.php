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

/**
 * A page to create a security concept for security-auditing purposes
 */

namespace tfyh\pages;

use tfyh\control\Runner;
use tfyh\control\SecurityMonitor;
use tfyh\util\FileHandler;
use tfyh\util\I18n;

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$runner = Runner::getInstance();
$i18n = I18n::getInstance();

$sec_concept_html = false;
if (isset($_GET["create"]) && (intval($_GET["create"]) >= 1)) {
    $sec_concept = new SecurityMonitor();
    if ((intval($_GET["create"]) == 1)) {
        $sec_concept_html = $sec_concept->create_HTML();
    } elseif ((intval($_GET["create"]) == 2)) {
        $sec_concept_html = $sec_concept->create_HTML();
        FileHandler::returnStringAsZip($sec_concept_html, "efaCloud_SecurityConcept.html");
    } elseif ((intval($_GET["create"]) == 3)) {
        $saved_at = $sec_concept->create_PDF();
        copy($saved_at, $saved_at . ".previous");
        FileHandler::returnFileToUser($saved_at);
    }
}

$selection = "<p><a class='formbutton' href='?create=1'>" . $i18n->t("2G9KEy|View now as web page") . "</a>&nbsp; &nbsp;" .
        "<a class='formbutton' href='?create=2'>" . $i18n->t("qrMNsF|html download") . "</a>&nbsp; &nbsp;" .
        "<a class='formbutton' href='?create=3'>" . $i18n->t("kS4t6V|PDF download") . "</a></p>";

// ===== start page output
echo $runner->pageStart();

echo "<h3>" . $i18n->t("6tNZ2D|Create data protection c...") . "</h3>";

if ($sec_concept_html) {
    echo $sec_concept_html;
    echo "</div>";
} else {
    echo "<p>" .
            $i18n->t(
                    "5pIFfg|An up-to-date security c...") .
                    "</p>";
    echo $selection;
}
echo "</div>";
$runner->endScript();
