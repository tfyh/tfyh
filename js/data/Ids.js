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

/**
 * A utility class to load all application configuration.
 */
class Ids {

    static NIL_UUID = "00000000-0000-0000-0000-000000000000"
    static base62 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    static base64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";

    /*
         * ID GENERATION AND RECOGNITION
        */
    /**
     * create a random uid value
     */
    static generateUid (countBytes = 9) {

        let bytes = new Uint8Array(countBytes);
        window.crypto.getRandomValues(bytes);
        let base64 = btoa(String.fromCharCode.apply(null, bytes));

        let pos = Math.random() * 61;
        let slashRep = Ids.base62.substring(pos, pos + 1);
        pos = Math.random() * 61;
        let plusRep = Ids.base62.substring(pos, pos + 1);
        return base64.replace(/\//g, slashRep).replace(/\+/g, plusRep);
    }

    /**
     * Check, whether the id complies to the uid format
     */
    static isUid (id) {
        if (! id)
            return false;
        if (id.length !== 8)
            return false;
        for (let i = 0; i < 8; i++)
            if (Ids.base62.indexOf(id.substring(i, i + 1)) < 0)
                return false;
        return true;
    }

    /**
     * Generate a new UUID, see
     * https://stackoverflow.com/questions/105034/how-do-i-create-a-guid-uuid
     */
    static generateUUID () {
        return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
            (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
        );
    }

    /**
     * Check, whether the id complies to the UUID format, i.e. a sequence of digits equal to 128 bits in hexadecimal
     * digits grouped by hyphens into XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX.
     */
    static isUuid (id) {
        if (!id || id.length !== 36)
            return false;
        if ((id.charAt(8) !== "-") || (id.charAt(13) !== "-") || (id.charAt(18) !== "-") || (id.charAt(23) !== "-"))
            return false;
        let hex = id.replace(/-/g, "");
        if (isNaN(parseInt(hex.substring(0, 16), 16)))
            return false;
        return !isNaN(parseInt(hex.substring(16), 16));
    }

    /**
     * Check, whether the id complies to the tfyh short UUID format, i.e. a sequence of digits equal to 36 bits in
     * hexadecimal digits grouped by hyphens into XXXXXXXX-XX, corresponding to the first 11 characters of a UUID. NB:
     * This provides appr. 69 billion different values (68,719,476,736)
     */
    static isShortUuid (id) {
        if (!id || id.length !== 11)
            return false;
        if (id.charAt(8) !== "-")
            return false;
        let hex = id.replace(/-/g, "");
        return (! isNaN(parseInt(hex, 16)))
    }


}