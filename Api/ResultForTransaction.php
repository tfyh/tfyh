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

enum ResultForTransaction: Int {
    case UNDEFINED = 0;

    // success
    case REQUEST_SUCCESSFULLY_PROCESSED = 21;
    case RESPONSE_SUCCESSFULLY_PROCESSED = 23;

    // container failure (the container will then care for retry, if necessary)
    case CONTAINER_ERROR = 41;
    case MISSING_IN_RESPONSE_CONTAINER = 43;

    // content failure (the transaction is moved from the busy into the failed queue)
    case SYNTAX_ERROR = 61;
    case MISMATCHING_ID = 63;
    case TRANSACTION_INVALID = 65;
    case TRANSACTION_FAILED = 67;
    case TRANSACTION_FORBIDDEN = 69;
    case INVALID_RESULT_CODE = 71;

    public static function valueOfOrInvalid(int $code): ResultForTransaction {
        return ResultForTransaction::from($code) ?? ResultForTransaction::INVALID_RESULT_CODE;
    }
    public static function text(int $code): string {
        return str_replace(strtolower(self::valueOfOrInvalid($code)->name), "_", " ");
    }
}