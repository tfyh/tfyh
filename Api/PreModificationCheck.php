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

namespace tfyh\api;

use tfyh\data\Record;

interface PreModificationCheck
{
    /**
     * Run application-specific semantic checks or evaluations on the parsed record. This may modify the record's
     * values without further notice, e.g. by lookup of Ids or similar
     * @param Record $record the record to check. Make sure to parse the content into it before calling the preModificationCheck().
     * @param int $mode 1 = insert, 2 = update, 3 = delete
     * @return bool true, if all checks were successful
     */
    public function isOk(Record $record, int $mode): bool;

}