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

// ===== start page output
echo file_get_contents('../Config/snippets/page_01_start');
echo file_get_contents('../Config/snippets/page_02_nav_to_body');
echo "<div class='w3-container'><h3><br><br><br><br><br>Maintenance</h3>";
echo "<p>Unfortunately, dilbo is currently unavailable due to maintenance until<br><b>" . $_GET["until"];
echo "</b><br>We apologize and ask for your patience<br><br><br><br>&nbsp;</p></div>";
echo "<p>Malheureusement, dilbo est actuellement indisponible pour cause de maintenance jusqu'à<br><b>" . $_GET["until"];
echo "</b><br>Nous vous présentons nos excuses et vous demandons de faire preuve de patience<br><br><br><br>&nbsp;</p></div>";
echo "<p>Purtroppo dilbo non è attualmente disponibile a causa di una manutenzione fino a<br><b>" . $_GET["until"];
echo "</b><br>Ci scusiamo e vi chiediamo di pazientare<br><br><br><br>&nbsp;</p></div>";
echo "<p>Leider ist dilbo derzeit wegen Wartungsarbeiten nicht verfügbar, bis<br><b>" . $_GET["until"];
echo "</b><br>Wir entschuldigen uns und bitten um Ihre Geduld<br><br><br><br>&nbsp;</p></div>";
echo "<p>Helaas is dilbo momenteel niet beschikbaar wegens onderhoud tot<br><b>" . $_GET["until"];
echo "</b><br>We verontschuldigen ons en vragen om uw geduld<br><br><br><br>&nbsp;</p></div>";
echo file_get_contents('../Config/snippets/page_03_footer');
