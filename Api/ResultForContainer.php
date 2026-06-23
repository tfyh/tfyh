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

enum ResultForContainer: Int {
    case UNDEFINED = 0;

    // success
    case REQUEST_AUTHENTICATED = 20;
    case RESPONSE_SUCCESSFULLY_PARSED = 22;

    // connection failure (assumed to be temporary)
    case INTERNET_CONNECTION_FAILED = 40;
    case INTERNET_CONNECTION_TIMEOUT = 42;
    case HTTP_COMMUNICATION_ERROR = 44;
    case SERVER_ERROR = 46;
    case SERVER_OVERLOAD = 48;

    // content error (assumed to be permanent)
    case SYNTAX_ERROR = 60;
    case API_VERSION_NOT_SUPPORTED = 62;
    case MISMATCHING_ID = 64;
    case UNKNOWN_CLIENT = 66;
    case AUTHENTICATION_FAILED = 68;
    case EMPTY_RESPONSE_CONTAINER = 70;
    case TX_ID_NOT_MATCHED = 72;
    case MISSING_TRANSACTION = 74;
    case INVALID_RESULT_CODE = 76;

    public static function valueOfOrInvalid(int $code): ResultForContainer {
        return ResultForContainer::from(strtolower($code)) ?? ResultForContainer::INVALID_RESULT_CODE;
    }
    public static function text(int $code): string {
        return str_replace(strtolower(self::valueOfOrInvalid($code)->name), "_", " ");
    }
}