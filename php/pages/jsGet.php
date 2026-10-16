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

use Control\Runner;
use Data\Config;
use Util\ListHandler;
include_once "../../tfyh/Data/Config.php";

// ===== public information is returned without any further checking.
// get all last modifications. Quite a frequent call
$info = (isset($_GET["info"])) ? $_GET["info"] : "";
if (strcasecmp($info, "modified") == 0) {
    echo Config::getModified();
    exit();
}

include_once "../../tfyh/Control/Runner.php";
include_once "../../tfyh/Util/I18n.php";
include_once "../../tfyh/Util/ListHandler.php";

$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";
$runner = Runner::getInstance();
$runner->startScript($userRequestedFile);

$set = (isset($_GET["set"])) ? $_GET["set"] : "";
$listName = (isset($_GET["list_name"])) ? $_GET["list_name"] : "";
$userId = $runner->sessions->userId();

// ===== special case: get current user ID. This is allowed for all, returns -1 for any anonymous request
if (strcasecmp($info, "userid") == 0) {
    echo $userId;
    $runner->endScript(false);
}

// ===== all other information is blocked for anonymous users.
if (($userId <= 0) || (strcasecmp($runner->sessions->userRole(), $runner->users->anonymousRole) == 0)) {
    if (strcasecmp($info, "executionProgress") == 0)
        echo "idle";
    else
        echo "404: Forbidden.";
    $runner->endScript(false);
}

// ===== configuration read
// ===== return a configuration file
if ((strlen($info) > 0) && (strlen($set) == 0)) {
    $configFileContents = file_get_contents("../../" . $info);
    if ($configFileContents !== false)
        echo $configFileContents;
    $runner->endScript(false);
}

// cancel progress information
if (strcasecmp($info, "executionCancel") == 0) {
    unlink("../../var/Run/executionProgress." . session_id());
    $runner->endScript(false);
}

// return a list content
if ((strlen($set) > 0) && (strlen($listName) > 0)) {
    $list = new ListHandler($set, $listName);
    $csv = $list->getCsv("", "", "");
    echo $csv;
    $runner->endScript(false);
}
