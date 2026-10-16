/**
 * tools-for-your-hobby
 * https://www.tfyh.org
 * Copyright  2023-2025  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except
 * in compliance with the License. You may obtain a copy of the License at
 * https://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software distributed under the License
 * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 * or implied. See the License for the specific language governing permissions and limitations under
 * the License.
 */

package org.tfyh.data

import kotlin.random.Random
import kotlin.time.Clock
import kotlin.uuid.Uuid

object Ids {

    const val NIL_UUID = "00000000-0000-0000-0000-000000000000"

    /**
     * Create a random uid value. Note that countBytes will be int-divided by 3 to create a result String
     * with (countBytes / 3) * 4 characters
     */
    fun generateUid (countBytes: Int = 9): String {

        // cryptographically secure randoms library avoided, for the
        // purpose a more or less random seed shall be enough
        val rand = Random(Clock.System.now().toEpochMilliseconds()
                + Clock.System.now().nanosecondsOfSecond)
        var uid = ""
        val x = rand.nextInt(0, 61)
        val slashRep = Codec.BASE62.substring(x, x + 1)
        val y = rand.nextInt(0, 61)
        val plusRep = Codec.BASE62.substring(y, y + 1)
        (0 ..< (countBytes / 3)).forEach { _ ->
            var ni = rand.nextInt(0, 16777215)
            (0 .. 3).forEach { _ ->
                val k = ni % 64
                uid += when {
                    (k == 62) -> plusRep
                    (k == 63) -> slashRep
                    else -> Codec.BASE62.substring(k, k + 1)
                }
                ni /= 64
            }
        }
        return uid
    }

    /**
     * Check, whether the id complies to the uid format
     */
    fun isUid (id: String?): Boolean {
        if ((id == null) || (id.length < 4) || (id.length > 12))
            return false
        for (i in id.indices)
            if (Codec.BASE62.indexOf(id.substring(i, i + 1)) < 0)
                return false
        return true
    }

    /**
     * Generate a new UUID, see
     */
    @Suppress("Unused")
    fun generateUUID () = Uuid.random()

    /**
     * Check, whether the id complies to the UUID format, i.e. a sequence of digits equal to 128 bits in hexadecimal
     * digits grouped by hyphens into XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX.
     */
    fun isUuid (id: String?):Boolean {
        if (id == null)
            return false
        if (id.length != 36)
            return false
        if ((id.substring(8, 9) != "-") || (id.substring(13, 14) != "-") ||
            (id.substring(18, 19) != "-") || (id.substring(23, 24) != "-"))
            return false
        val hex = id.replace("-", "")
        for (i in 0 .. 31)
            if ("0123456789abcdefABCDEF".indexOf(hex.substring(i, i + 1)) < 0)
                return false
        return true
    }

    /**
     * Check, whether the id complies to the tfyh short UUID format, i.e. a sequence of digits equal to 36 bits in
     * hexadecimal digits grouped by hyphens into XXXXXXXX-XX, corresponding to the first 11 characters of a UUID. NB:
     * This provides approximately 69 billion different values (68,719,476,736)
     */
    fun isShortUuid (id: String?):Boolean {
        if (id == null)
            return false
        if (id.length != 11)
            return false
        if (id.substring(8, 9) != "-")
            return false
        val hex = id.replace("-", "")
        for (i in 0 .. 9)
            if ("0123456789abcdefABCDEF".indexOf(hex.substring(i, i + 1)) < 0)
                return false
        return true
    }

}