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

use JetBrains\PhpStorm\NoReturn;

use tfyh\api\ResultForTransaction;
include_once "../_Api/ResultForTransaction.php";

use tfyh\control\LoggerSeverity;
use tfyh\control\Runner;
include_once "../_Control/LoggerSeverity.php";
include_once "../_Control/Runner.php";

use tfyh\data\Codec;
use tfyh\data\Config;
use tfyh\data\Findings;
use tfyh\data\Validator;
include_once "../_Data/Codec.php";
include_once "../_Data/Config.php";
include_once "../_Data/Findings.php";
include_once "../_Data/Validator.php";

use tfyh\util\I18n;
include_once "../_Util/I18n.php";

/**
 * The response page to any form post. Provides configuration and data insert, update, and delete
 * capabilities.
 */
#[NoReturn] function return_result (int $code, String $text): void
{
    echo $code . ";" . $text;
    Runner::getInstance()->endScript(false);
}

/**
 * Write the full settings file of the changed item. Because settings files are small and writing rare, this is
 * the most efficient approach.
 */
#[NoReturn] function writeChangeAndReturn(String $changedPath, bool $isBasic, String $response): void {
    $i18n = I18n::getInstance();
    $topBranchPath = explode(".", $changedPath)[1];
    $config = Config::getInstance();
    $topBranch = $config->getItem("." . $topBranchPath);
    $topBranchName = $topBranch->name();
    $settingsDir = ($isBasic) ? "packaged" : "added";
    $settingsFName = "../Config/$settingsDir/$topBranchName";
    $settingsFContents = $topBranch->branchToCsv(99, $isBasic);
    $success = file_put_contents($settingsFName, $settingsFContents);
    if ($success) {
        Runner::getInstance()->logger->log(LoggerSeverity::INFO, "_forms/jsPost.php",
        "Executed configuration change on $changedPath. Mode: " . $_POST["mode"] . ", change: " . $_POST["csv"]);
        return_result(ResultForTransaction::REQUEST_SUCCESSFULLY_PROCESSED->value, $response);
    }
    else {
        Runner::getInstance()->logger->log(LoggerSeverity::INFO, "_forms/jsPost.php",
            "Failed configuration change on $changedPath. Mode: " . $_POST["mode"] . ", reason: " . $success);
        return_result(ResultForTransaction::TRANSACTION_FAILED->value, $i18n->t("8tmTMH|%1 file write failed.", $topBranchName));
    }
}

// ===== initialize
$userRequestedFile = __FILE__;
include_once "../_Control/init.php";
$i18n = I18n::getInstance();
$config = Config::getInstance();
$runner = Runner::getInstance();

$txForbidden = ResultForTransaction::TRANSACTION_FORBIDDEN->value;
$txInvalid = ResultForTransaction::TRANSACTION_INVALID->value;
$txFailed = ResultForTransaction::TRANSACTION_FAILED->value;
$txSuccess = ResultForTransaction::REQUEST_SUCCESSFULLY_PROCESSED->value;

// === APPLICATION LOGIC ==============================================================
$mode = intval($_POST["mode"]);
if ($mode <= 0)
    return_result($txInvalid, $i18n->t("bdF7u8|Invalid mode."));

$csv = $_POST["csv"];
$path = $_POST["path"];
// Modifications are only allowed in two branches.
if ((strlen($path) <= 1) || (!str_starts_with($path, '.')))
    return_result($txInvalid, $i18n->t("ORUUoH|Invalid path °%1°", $path));

// ===========================
// MODIFY A CONFIGURATION ITEM
// ===========================

// set variables for later use
$configItem = $config->getItem($path);
if (($mode > 1) && (!$configItem->isValid()))
    return_result($txFailed, $i18n->t("YYb502|Item to modify not found...", $path));

// execute configuration change
// ============================
if ($mode == 1) {
    // insert. A configuration parent must be there.
    $addableType = $configItem->nodeAddableType();
    if (strlen($addableType) == 0)
        return_result($txFailed,
            $i18n->t("1I7q2d|Adding not permitted to:...", $configItem->getPath()));
    // check the name validity
    $inserts = Codec::csvToMap($csv);
    $definition = [];
    foreach ($inserts as $insert)
        $definition[$insert["field"]] = $insert["value"];
    Findings::clearFindings();
    Validator::checkAgainstRule(($definition["_name"] ?? "?"), "identifier");
    if (Findings::countErrors() > 0)
        return_result($txInvalid, Findings::getFindings(false));
    // A single item is posted for insert. This can never be a packaged item
    $addResult = $configItem->putChild($definition, false);
    if (!$addResult)
        return_result($txInvalid, $i18n->t("lFtrxn|Failed to put child to %...", $configItem->getPath()));
    // added structure is never part of the Config/packaged file set but added to the appropriate Config/added file.
    writeChangeAndReturn($path, false, $i18n->t("SclI3O|insert ok."));
}

if ($mode == 2) {
    // Changes are posted for update, usually the configuration item plus children only.
    $changesList = Codec::csvToMap($csv);
    $changes = [];
    foreach ($changesList as $change)
        $changes[$change["field"]]  = $change["value"];
    $updateResult = "";
    // Parse, i.e. take in the changes
    $configItem->parseDefinition($changes);
    writeChangeAndReturn($path, $configItem->isPackaged(), $i18n->t("a7FsP7|update ok."));
}

if ($mode == 3) {
    // Delete. Check first whether deletion is possible. For that, the item must be of the addable
    // type for its parent.
    $configParent = $configItem->parent();
    if (! $configParent->isOfAddableType($configItem) || $configItem->isPackaged())
        return_result($txForbidden, "Delete forbidden for this configuration item.");
    else {
        // remove and store.
        $configItem->destroy();
        // if an item may be deleted, it is never packaged
        writeChangeAndReturn($path, false, $i18n->t("c92P96|Item deleted."));
    }
}

if (($mode == 4) || ($mode == 5)) {
    // Move up or down. Check first whether a move is possible. For that, the item must be of the
    // addable type for its parent.
    $configParent = $configItem->parent();
    if (! $configParent->isOfAddableType($configItem))
        return_result($txForbidden, $i18n->t("ZNLZE6|Move forbidden for this ..."));
    else {
        // move and store.
        $success = $configParent->moveChild($configItem, ($mode == 4) ? - 1 : 1);
        writeChangeAndReturn($path, $configItem->isPackaged(), $i18n->t("Ck2Nrk|Item moved."));
    }
}

return_result($txInvalid, $i18n->t("WfIwn5|Invalid mode."));
