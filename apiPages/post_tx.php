<?php

use dilbo\app\TxHandler;
include_once "../../dilbo/App/TxHandler.php";

use tfyh\api\Container;
use tfyh\control\LoggerSeverity;
use tfyh\control\Monitor;
use tfyh\control\Runner;

// ===== initialise the session type to api
$monitor = Monitor::getInstance("api");
$runner = Runner::getInstance();
if ($runner->debugOn)
    $runner->logger->log(LoggerSeverity::DEBUG, "post_tx.php", "Request handling started at " . date("H:i:s"));

// ===== parse tx container and return, if parsing errors occur
$txc = (isset($_POST["txc"])) ? trim($_POST["txc"]) : "";
$container = Container::getInstance();
$container->parseRequest(trim($txc));
if ($container->txc["containerResultCode"] >= 40)
    $container->sendResponseAndExit();

// ===== now start the script execution. Since the $runner and $monitor are singleton classes, the normal script start
// ===== can be called.
$userRequestedFile = __FILE__;
include_once "../../tfyh/init/init.php";

// ===== handle all transactions
$tx_handler = new TxHandler();
$tx_handler->handleRequestContainer($runner->menu);
if ($runner->debugOn)
    $runner->logger->log(LoggerSeverity::DEBUG, "post_tx.php", "Request handling completed at " . date("H:i:s"));
$container->sendResponseAndExit();
