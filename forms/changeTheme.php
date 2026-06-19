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

namespace tfyh\forms;

use tfyh\control\Runner;
use tfyh\util\I18n;

$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";

$runner = Runner::getInstance();
$i18n = I18n::getInstance();
// The colour designer needs no layout file but holds the layout itself

// === APPLICATION LOGIC ==============================================================

// read the template and the default colours.
$appColors = file_get_contents("../resources/app-colors.txt");
$appStyle = file_get_contents("../resources/app-style-no_colors.css");
$colors = [];
$colorKeys = [];
foreach (explode("\n", $appColors) as $color) {
    if (count(explode("=", $color)) > 1) {
        $key = explode("=", $color)[0];
        $value = explode("=", $color)[1];
        $colors[$key] = $value;
        $colorKeys[] = $key;
    }
}

// if applicable, read data were entered in the last step
$changeColors = (isset($_GET["changeColors"])) ? intval($_GET["changeColors"]) : 0;
if ($changeColors > 0) {
    
    if ($changeColors == 1) {
        foreach ($colors as $key => $value) {
            // The enclosing apostrophes are not in $_POST, but just the inner key.
            $postKey = substr($key, 1, strlen($key) - 2);
            if (isset($_POST[$postKey])) {
                $colors[$key] = $_POST[$postKey];
            }
        }
    } elseif (($changeColors == 2) && file_exists("../resources/app-colors-previous.txt")) {
        // the new colours are the previous ones
        $previousColors = file_get_contents("../resources/app-colors-previous.txt");
        foreach (explode("\n", $previousColors) as $color) {
            $key = explode("=", $color)[0];
            $value = explode("=", $color)[1];
            $colors[$key] = $value;
        }
    } elseif (($changeColors == 3) && file_exists("../resources/app-colors-default.txt")) {
        // the new colours are the default ones
        $previousColors = file_get_contents("../resources/app-colors-default.txt");
        foreach (explode("\n", $previousColors) as $color) {
            $key = explode("=", $color)[0];
            $value = explode("=", $color)[1];
            $colors[$key] = $value;
        }
    }
    
    // Create new style sheet and save color set
    $appColorsNew = "";
    $appStyleNew = $appStyle;
    foreach ($colors as $key => $value) {
        if (isset($key) && (strlen($key) > 0)) {
            $appColorsNew .= $key . "=" . trim($value) . "\n";
            $appStyleNew = str_replace($key, trim($value), $appStyleNew);
        }
    }
    $success = file_put_contents("../resources/app-colors-previous.txt", $appColors);
    $success = file_put_contents("../resources/app-colors.txt", $appColorsNew) && $success;
    $success = file_put_contents("../resources/app-style.css", $appStyleNew) && $success;
    
    // wait a little to let the file writing complete and
    // restart anew.
    sleep(1);
    header("Location: changeTheme.php");
}

// === PAGE OUTPUT ===================================================================
echo $runner->pageStart();

// page heading, identical for all workflow steps

echo "<h3>" . $i18n->t("KDNuD3|Change colours and font") . "</h3>";

// example elements
echo "<h1>" . $i18n->t("iX6ImJ|Example Headline 1") . "</h1>";
echo "<h4>" . $i18n->t("DybBcu|Example Headline 4") . "</h4>";
echo "<p><a href='#'>" . $i18n->t("HsHWSA|Example Link") . "</a></p>";
echo "<label class='cb-container'>" . $i18n->t("rTgJH2|Radio button checked") .
         "<input type='radio' name='radioexample1' value='' checked /><span class='cb-radio'></span></label><br>";
echo "<label class='cb-container'>" . $i18n->t("SYuuyO|Radio button unchecked") .
         "<input type='radio' name='radioexample2' value='' /><span class='cb-radio'></span></label><br>";
echo "<label class='cb-container'>" . $i18n->t("3MS3H9|Checkbox checked") .
         "<input type='checkbox' name='checkboxexample1' value='' checked /><span class='cb-checkmark'></span></label><br>";
echo "<label class='cb-container'>" . $i18n->t("nMSzXi|Checkbox unchecked") .
         "<input type='checkbox' name='checkboxexample1' value='' /><span class='cb-checkmark'></span></label><br>";
echo "<select class='formSelector' name='selectorexample' style='width: 15em'>";
echo "<option value='option1'>" . $i18n->t("5qx7Bd|option") . " #1</option>";
echo "<option selected value='option2'>" . $i18n->t("ZiS5as|option") . " #2</option>";
echo "<option value='option3'>" . $i18n->t("Rqkqic|option") . " #3</option>";
echo "</select>\n<p>&nbsp;</p>\n<form method=POST action='?changeColors=1'>";

// colour table
echo "<table style='width: 90%'>\n<thead>\n<tr><th>" . $i18n->t("CS3ieI|colour application") . "</th><th>&nbsp;</th><th>" .
         $i18n->t("xmC7bW|colour value") . "</th></tr></thead><tbody>";
foreach ($colorKeys as $color_key) {
    if (strlen($color_key) > 0) {
        if (str_starts_with($color_key, '#'))
            $row = "<tr><td><h5>" . substr($color_key, 1) . "</h5></td><td>&nbsp;</td><td>&nbsp;</td></tr>";
        else
            $row = "<tr><td>" . $color_key . "</td>"
                . "<td style='background-color:" . $colors[$color_key] . "'>&nbsp;&nbsp;&nbsp;</td>"
                . "<td>" . "<input class='formInput' name=" . $color_key
                . " value='" . $colors[$color_key] . "' type=text /></td></tr>";
        echo $row;
    }
}
echo "    </tbody>\n   </table>\n   <p>\n" .
         "    <input name='submit' value='Test' type='submit' class='formButton' />\n" .
         "   </p>\n  </form>\n  <p>";
if (file_exists("../resources/app-colors-previous.txt")) {
    echo "<a href='?changeColors=2' class='formButton'>" . $i18n->t("OdDC93|Back to previous setting") . "</a>";
}
if (file_exists("../resources/app-colors-default.txt")) {
    echo "&nbsp;&nbsp;&nbsp;<a href='?changeColors=3' class='formbutton'>" .
             $i18n->t("j5CpiO|Back to standard colours") . "</a>";
}
echo "</p>\n<p>";
echo $i18n->t(
        "Z1rWgP|Note: Usually the browse...");
echo "</p>\n</div>";
$runner->endScript();
