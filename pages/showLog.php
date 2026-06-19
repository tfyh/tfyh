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

namespace tfyh\pages;

use tfyh\control\Runner;
use tfyh\data\Codec;
use tfyh\util\I18n;

/**
 * Page display file. Shows all logs of the application.
 */

// ===== initialize

$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$runner = Runner::getInstance();
$i18n = I18n::getInstance();

$logName = $_GET["log_name"] ?? "";
$severity = $_GET["severity"] ?? "";
$fileName = "../../var/Log/" . $logName . ".log";

$selection = "<div class='w3-row'>";

$log = "<h4>" . $logName .  "</h4>";
if ((strlen($logName) > 0) && ! file_exists($fileName))
    $log .= $i18n->t("0SC9ay|File does not exist.");
else {
    $log .= "<table><tr><th>" . $i18n->t("GGZexg|Date and Time") . "</th><th>" . $i18n->t("9mIR2E|Severity") .
        "</th><th>" . $i18n->t("1j0vu9|called by") . "</th><th>" . $i18n->t("RsZcKe|Message") . "</th></tr>";
    $log_lines = Codec::csvFileToArray($fileName);
    $countOfLines = 0;
    for ($l = count($log_lines) - 1; ($countOfLines < 50) && ($l >= 0); $l --)
        if ((strlen($severity) == 0) || (strcasecmp($log_lines[$l][2], $severity) == 0)) {
            $log .= "<tr>";
            $c = 0;
            foreach ($log_lines[$l] as $element)
                if ($c++ !== 1)
                    $log .= "<td>" . Codec::htmlSpecialChars($element) . "</td>";
            $log .= "</td></tr>";
            $countOfLines++;
        }
}
$log .= "</table>";

// ===== start page output
echo $runner->pageStart();

echo "<h3>" . $i18n->t("rjunmx|Server logs") . "</h3>";
echo "<p>" . $i18n->t("qfCs0a|Please select the log to...") . "</p>";
echo "<p><a href='?log_name=web'>web</a>/<a href='?log_name=web&severity=ERROR'>web errors</a> - "
    . "<a href='?log_name=api'>api</a>/<a href='?log_name=api&severity=ERROR'>api-errors</a> - "
    . "<a href='?log_name=config'>config</a>/<a href='?log_name=config&severity=ERROR'>config-errors</a></p>";
echo $log;
echo "<!-- END OF Content -->\n</div>";
$runner->endScript();
