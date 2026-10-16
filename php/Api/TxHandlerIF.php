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

use Control\Menu;

interface TxHandlerIF
{
    /**
     * Executes a single transaction based on the provided index and menu permission settings. The result_code and the
     *  result_message fields of the transaction are also set according to the transaction result.
     *
     * @param Container $container The container of transactions of aa API handshake.
     * @param int $index The index of the transaction in the transaction container.
     * @param Menu $menu An instance of the Menu class used to verify permissions for the transaction.
     * @return void This method does not return a value.
     */
    public function executeTransaction(Container $container, int $index, Menu $menu): void;
}