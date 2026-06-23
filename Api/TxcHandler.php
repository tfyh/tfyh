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

namespace Api;

use Control\LoggerSeverity;
use Control\Menu;
use Control\Runner;

/**
 * The transaction container handling class. Clients are the app web-Application or the app Java application or
 * other.
 */
class TxcHandler
{

    /**
     * the app API class providing API transaction support.
     */

    private Runner $runner;
    private bool $debugOn;
    private Container $container;

    /**
     * public Constructor.
     */
    public function __construct()
    {
        $this->runner = Runner::getInstance();
        $this->debugOn = $this->runner->debugOn;
        $this->container = Container::getInstance();
    }

    /**
     * This method verifies the transaction token and sets the current transaction array.
     * @param Menu $menu the menu object to verify the user rights.
     * @return void
     */
    public function handleRequestContainer(TxHandlerIF $txHandler, Menu $menu): void
    {
        if ($this->debugOn)
            $this->runner->logger->log(LoggerSeverity::DEBUG, "handleRequestContainer",
                "Started");
        for ($i = 0; $i < count($this->container->txs); $i++) {
            $this->runner->logger->log(LoggerSeverity::DEBUG, "handleRequestContainer",
                "Executing " . $this->container->transactionToLog($i, false));
            $txHandler->executeTransaction($this->container, $i, $menu);
            $isError = (intval($this->container->txs[$i]["resultCode"]) >= 40);
            if ($isError) {
                $this->runner->logger->log(LoggerSeverity::ERROR, "handleRequestContainer",
                    "Transaction handling failed for " . $this->container->transactionToLog($i, true));
            } else
                $this->runner->logger->log(LoggerSeverity::INFO, "handleRequestContainer",
                    "Transaction handling succeeded for " . $this->container->transactionToLog($i, false));
        }
    }

}