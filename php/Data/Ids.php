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

namespace Data;

const base64charsPlus = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";

const NIL_UUID = "00000000-0000-0000-0000-000000000000";

/**
 * Utility class for generating and validating unique identifiers such as UIDs and UUIDs. Fully static.
 */
class Ids {
    /**
     * Create a random uid value. Note that countBytes will be int-divided by 3 to create a result String
     * with (countBytes / 3) * 4 characters
     * @param int $bytes the number of bytes to generate.
     * @return String the generated uid.
     */
    public static function generateUid (int $bytes = 9): String
    {
        $bytes = openssl_random_pseudo_bytes($bytes);
        $base_64 = base64_encode($bytes);
        $slash_rep = substr(Codec::BASE62, rand(0, 61), 1);
        $plus_rep = substr(Codec::BASE62, rand(0, 61), 1);
        return str_replace("/", $slash_rep, str_replace("+", $plus_rep, $base_64));
    }

    /**
     * Check, whether the id complies to the uid format
     * @param String|null $id the id to check.
     * @return bool whether the id complies to the uid format.
     */
    public static function isUid (String|null $id): bool {
        if ((is_null($id)) || (strlen($id) !== 8))
            return false;
        for ($i = 0; $i < strlen($id); $i++)
            if (!str_contains(Codec::BASE62, substr($id, $i, 1)))
                return false;
        return true;
    }

    /**
     * create GUIDv4, see https://www.php.net/manual/de/function.com-create-guid.php.
     *
     * @return string Unique identifier
     */
    public static function generateUuid (): String
    {
        // OSX/Linux. Windows environments, see link above
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Check, whether the id complies to the UUID format, i.e. a sequence of digits equal to 128 bits in hexadecimal
     * digits grouped by hyphens into XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX.
     * @param String|null $id the id to check.
     * @return bool whether the id complies to the UUID format.
     */
    public static function isUuid (String|null $id): bool {
        if ((is_null($id)) || (strlen($id) != 36))
            return false;
        if ((substr($id, 8, 1) != "-") ||
            (substr($id, 13, 1) != "-") ||
            (substr($id, 18, 1) != "-") ||
            (substr($id, 23, 1) != "-"))
            return false;
        $hex = str_replace("-", "", $id);
        $hexChars = "0123456789abcdefABCDEF";
        for ($i = 0; $i < 31; $i++)
            if (!str_contains($hexChars, substr($hex, $i, 1)))
                return false;
        return true;
    }

    /**
     * Check, whether the id complies to the tfyh short UUID format, i.e. a sequence of digits equal to 36 bits in
     * hexadecimal digits grouped by hyphens into XXXXXXXX-XX, corresponding to the first 11 characters of a UUID. NB:
     * This provides approximately 69 billion different values (68,719,476,736)
     * @param String|null $id the id to check.
     * @return bool whether the id complies to the short UUID format.
     */
    public static function isShortUuid (String|null $id): bool {
        if ((is_null($id)) || (strlen($id) != 11))
            return false;
        if (substr($id, 8, 1) != "-")
            return false;
        $hex = str_replace("-", "", $id);
        $hexChars = "0123456789abcdefABCDEF";
        for ($i = 0; $i < 31; $i++)
            if (!str_contains($hexChars, substr($hex, $i, 1)))
                return false;
        return true;
    }
}
