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

namespace tfyh\control;

use tfyh\data\Config;
use tfyh\data\Item;
include_once '../_Data/Config.php';
include_once '../_Data/Item.php';

// internationalisation support on needed to translate the menu and provide an allowance profile for a role.
use tfyh\util\I18n;
include_once '../_Util/I18n.php';

/**
 * Class file for the Menu class. This class reads the menu and returns it as html, filtered to those entries which are
 * permitted to the current user.
 */
class Menu
{

    /**
     * HTML snippet at the start of a menu
     */
    private string $htmlMenuStart = "\n" .
    "<!--============================== menu - start =========================-->" . "\n";

    /**
     * HTML snippet at the start of the level 1 list
     */
    private string $htmlListL1 = '<div class="w3-padding-64 w3-large">' . "\n";

    /**
     * HTML snippet at the start of level 1 item. In the case of top for submenus use {link} = "javascript:void(0)"
     * for a submenu open trigger, {onclick} = 'onclick="openSubMenu([idOfParent])"', and {caret} =
     * '<b>&#x23f7</b>'. Else set {onclick} = '', {caret} = '', and {link} to the target link.
     */
    private string $htmlItemL1 = '<a{href} class="w3-bar-item menuitem" id="{id}" ' .
    '{onclick}{hidden}>{headline}{caret}</a>' . "\n";

    /**
     * HTML snippet at the start of a level 2 list.
     */
    private string $htmlListL2 = '';

    /**
     * HTML snippet at the start of the level 2 item.
     */
    private string $htmlItemL2 = '<div class="w3-bar-block w3-hide w3-medium subMenu{parent}">' . "\n" .
    '<a{href} class="w3-bar-item w3-bar-item-2 menuitem" id="{id}" ' .
    '{onclick}{hidden}>{headline}</a>' . "\n" . '</div>' . "\n";

    /**
     * HTML snippet at the end of the menu
     */
    private string $htmlMenuEnd = '<footer class="w3-small w3-center" id="footer">' .
    "<br><br>##user##<br>##version## (##language##)<br>##copyright##<br><br>".
    "<img src='../resources/app_logo_64.png' alt='application logo'><br>&nbsp;</footer></div>" . "\n" .
    "<!--============================== menu - end ===========================-->" . "\n";

    /**
     * the menu definition array, as was read from the csv file passed in the constructor
     */
    private array $menuDefArray = [];

    /**
     * will be set to true, if the menu path is not "../Config/access/menuForPublic"
     */
    private bool $isNotPublic;

    /**
     * Construct the menu from its template file. A template file is a flat file of menu items, starting with
     * a programmatic name, followed by name=value pairs preceded by a dot. Name value pairs define the menu
     * item. Menu items will be displayed in the sequence of the file. Level 2 item names must start with a
     * "_".
     * @param string $accessType the type of access: if this "public", the menu is not filtered by the user's
     * role.
     */
    function __construct(string $accessType)
    {
        $this->isNotPublic = (strcasecmp($accessType, "public") != 0);
        $this->setDefinition($accessType, $this->menuDefArray);
        // menu footer: user, version, copyright.
        $sessions = Sessions::getInstance();
        $username = $sessions->userFullName() . " (" . $sessions->userRole() . ")";
        $this->htmlMenuEnd = str_replace("##user##", $username, $this->htmlMenuEnd);
        $config = Config::getInstance();
        $this->htmlMenuEnd = str_replace("##version##", $config->appVersion,
            str_replace("##language##", $config->language()->value,
                str_replace("##copyright##",$config->getItem(".framework.app.copyright")->valueStr(),
                    $this->htmlMenuEnd)));
    }

    /**
     * Create the item's definition array for the menu.
     * @param String $levelOne the name of the level 1 menu item, if this is a submenu, otherwise empty.
     * @param Item $menuItem the configuration item to be processed.
     * @return array the item definition array.
     */
    private function setItemDefinition(String $levelOne, Item $menuItem): array {

        $definition = [];
        // identify the position in the hierarchy
        $isLevelOne = (strlen($levelOne) == 0);
        $definition["level"] = ($isLevelOne) ? 1 : 2;
        $definition["parent"] = (! $isLevelOne) ? $levelOne : "";

        // set the parameters
        $definition["id"] = ($isLevelOne) ? $menuItem->name() : "_" . $levelOne . "_" . $menuItem->name();
        $definition["permission"] = $menuItem->nodeReadPermissions();
        $definition["headline"] = $menuItem->label();
        $definition["link"] = $menuItem->valueStr();
        $definition["hidden"] = (Runner::getInstance()->users->isHiddenItem($definition["permission"]))
            ? " style='display:none'" : "";

        // set the menu-related actions
        $definition["caret"] = "";
        $definition["onclick"] = "";
        $definition["href"] = "";
        $hasLink = (strlen($definition["link"]) > 0);
        if ($hasLink) {
            if (str_starts_with($definition["link"], "event:"))
                // The id is used for event binding if it is an event call.
                $definition["id"] = "do-" . substr($definition["link"], 6);
            else
                $definition["href"] = " href='" . $definition["link"] . "'";
        } else {
            if ($isLevelOne) {
                // if the link is empty, open a submenu at level 1
                $definition["onclick"] = ' onclick="openSubMenu(\'' . $definition["id"] . '\')"';
                $definition["caret"] = ' <b>&#x25be;</b>';
            }
        }

        return $definition;
    }

    /**
     * Create the menu definition array from the menu definition file. The file is a flat file of menu items,
     * starting with a programmatic name, followed by name=value pairs preceded by a dot. Name value pairs
     * define the menu item. Menu items will be displayed in the sequence of the file. Level 2 item names must
     * start with a "_".
     * @param string $type the type of access, as are available from the configuration.
     * @param array $rawMenuDefArray the menu definition array to be filled.
     */
    private function setDefinition(string $type, array &$rawMenuDefArray): void {
        $menu = Config::getInstance()->getItem(".access.menus.$type");
        $rawMenuDefArray = [];
        foreach ($menu->getChildren() as $menuItem) {
            $rawMenuDefArray[] = $this->setItemDefinition("", $menuItem);
            $levelOne = $menuItem->name();
            foreach ($menuItem->getChildren() as $subMenuItem)
                $rawMenuDefArray[] = $this->setItemDefinition($levelOne, $subMenuItem);
        }
    }

    /**
     * Return a list of allowed activities per role as html-formatted text
     */
    public function getAllowanceProfileHtml(): string
    {
        $i18n = I18n::getInstance();
        $allowanceArray = [];
        foreach ($this->menuDefArray as $rawMenuDefinition) {
            $roles = explode(",", str_replace(".", "", $rawMenuDefinition["permission"]));
            $activity = $i18n->t(trim($rawMenuDefinition["headline"]));
            foreach ($roles as $role) {
                $prefix = substr($role, 0, 1);
                if (($prefix != '#') && ($prefix != '@') && ($prefix != '$')) {
                    if (!isset($allowanceArray[$role]))
                        $allowanceArray[$role] = $activity;
                    else
                        $allowanceArray[$role] .= ", " . $activity;
                }
            }
        }
        $allowanceStr = "<ul>";
        $roles = Config::getInstance()->getItem(".access.roles");
        foreach ($roles->getChildren() as $role)
            $allowanceStr .= "<li><b>" . $role->name() . "</b>: " .
                ((!isset($allowanceArray[$role->name()]))
                    ? $i18n->t("LlqlPF|not used.") : $allowanceArray[$role->name()]) . "</li>\n";
        return $allowanceStr . "</ul>";
    }

    /**
     * Check whether the session user shall get access to the given path. The file name
     * and parent directory name must be the same as in the item definition. This will essentially link the
     * file path to the item and then use the toolbox to check the item's permission against the user's
     * permissions. Files may have multiple invocations within the menu. All will be checked until a
     * permission is found.
     * @param string $path the path to be checked.
     * @return bool true if the user is allowed to access the path.
     */
    public function isAllowedMenuItem(string $path) : bool
    {
        $pathElements = explode("/", $path);
        $cpe = count($pathElements);
        // now control specific checks
        $isAllowedItem = false;
        $runner = Runner::getInstance();
        foreach ($this->menuDefArray as $item) {
            $hasLink = isset($item["link"]) && (strlen($item["link"]) > 0);
            if ($hasLink) {
                $link = $item["link"];
                if (mb_strpos($link, "?") !== false)
                    $link = mb_substr($link, 0, strpos($link, "?"));
                $linkElements = explode("/", $link);
                $cle = count($linkElements);
                // split off any parameters from path
                if (str_contains($linkElements[$cle - 1], "?"))
                    $linkElements[$cle - 1] = substr($linkElements[$cle - 1], 0,
                        strpos($linkElements[$cle - 1], "?"));
                // error page display is always allowed. Check whether the link ends with
                // 'pages/error.php'
                if ((strcasecmp("error.php", $pathElements[$cpe - 1]) == 0) && (strcasecmp("pages",
                            $pathElements[$cpe - 2]) == 0))
                    return true;
                // do normal role check: compare the paths for the menu item and the requested path.
                if ((strcasecmp($linkElements[$cle - 1], $pathElements[$cpe - 1]) == 0) && (strcasecmp(
                            $linkElements[$cle - 2], $pathElements[$cpe - 2]) == 0)) {
                    $isAllowedItem = $isAllowedItem || $runner->users->isAllowedItem(
                            $item["permission"]);
                }
            }
        }

        // If the page is not allowed, this may also be a publicly allowed page, but now in a session
        // with an authenticated user. In order not to blow up the internal menu, Access allowance of the
        // public
        // menu is now checked, and if allowed, access is granted.
        if (!$isAllowedItem && $this->isNotPublic) {
            $pMenu = new Menu("public");
            $isAllowedItem = $pMenu->isAllowedMenuItem($path);
            unset($pMenu);
        }

        // return result.
        return $isAllowedItem;
    }

    /**
     * Check whether a different role shall be allowed to be used by a verified user, usually for test
     * purposes.
     * @param string $userRole the role of the user.
     * @param string $useAsRole the role to be used.
     * @return bool true if the role change is allowed.
     */
    function isAllowedRoleChange(string $userRole, string $useAsRole): bool
    {
        if (strcasecmp($useAsRole, $userRole) == 0)
            return true;
        $includedRoles = Runner::getInstance()->users->includedRoles[$userRole];
        foreach ($includedRoles as $role)
            if (strcasecmp($useAsRole, $role) == 0)
                return true;
        return false;
    }

    /**
     * Get the menu based on the role of the session user. The role will be expanded according to the
     * hierarchy, and all included roles are as well checked. If $role is null, the allowance is checked for the
     * anonymous role.
     * @return string the menu as html.
     */
    function getMenu(): string
    {
        $runner = Runner::getInstance();
        $mHtml = $this->htmlMenuStart;
        if ($runner->debugOn)
            $mHtml .= "<span style='color:#b00;background-color:#fff;text-align:center;' class='w3-bar-item'><b>" .
                I18n::getInstance()->t("xhXR6R|DEBUG MODE") . "</b></span>\n";
        $mHtml .= $this->htmlListL1;
        $l = 1;
        $l1i = 0;
        foreach ($this->menuDefArray as $item) {
            if ($item["level"] === 2) {
                // level 2 menu item.
                $iHtml = $this->htmlItemL2;
                // if last item was level 1, change level and remove list close tag '</ul>'
                if ($l == 1) {
                    // change level
                    $l = 2;
                    // the current level 1 item may have been a disallowed item. Then there is
                    // no close tag, which can be removed.
                    if ($l1i > 0) {
                        $mHtml .= $this->htmlListL2;
                    }
                }
            } else {
                // level 1 menu item.
                $iHtml = $this->htmlItemL1;
                // if last item was level 2, change level
                if ($l == 2) {
                    $l = 1;
                    if ($l1i > 0) {
                        $l1i = 0;
                    }
                }
            }
            if ($runner->users->isAllowedItem($item["permission"])) {
                foreach (["headline", "parent", "id", "hidden", "href", "onclick", "caret"
                         ] as $itemDefField)
                    $iHtml = str_replace("{" . $itemDefField . "}", $item[$itemDefField], $iHtml);
                $mHtml .= $iHtml;
                if ($l == 1)
                    $l1i++;
            }
        }
        $mHtml .= $this->htmlMenuEnd;
        return $mHtml;
    }
}
