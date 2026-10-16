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

package org.tfyh.util

enum class Language(
    val code: String,
    val dateTemplate: String,
    val decimalPoint: Boolean,
    // note: for PHP there is also writeNullAsNULL, only at SQL-Interface, not needed here
) {
    // real languages
    EN(
        code = "en",
        dateTemplate = "d-m-Y",
        decimalPoint = true,
    ),
    DE(
        code = "de",
        dateTemplate = "d.m.Y",
        decimalPoint = false,
    ),
    NL(
        code = "nl",
        dateTemplate = "d-m-Y",
        decimalPoint = false,
    ),
    FR(
        code = "fr",
        decimalPoint = true,
        dateTemplate = "d/m/Y",
    ),
    IT(
        code = "it",
        dateTemplate = "d/m/Y",
        decimalPoint = true,
    ),
    CSV(
        code = "csv",
        dateTemplate = "Y-m-d",
        decimalPoint = true,
    ),
    SQL(
        code = "sql",
        dateTemplate = "Y-m-d",
        decimalPoint = true,
    );

    companion object {
        fun valueOfOrDefault(languageCode: String): Language {
            return try { Language.valueOf(languageCode.uppercase()) }
            catch (_: Exception) { DE }
        }
    }

}