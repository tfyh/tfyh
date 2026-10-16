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

class I18n private constructor() {

    companion object {
        private val i18n = I18n()
        fun getInstance() = i18n
    }

    // localization
    var map = mapOf<String, String>()
    private var loaded = false

    suspend fun loadResource(language: Language, resProvider: ResProviderIF) {
        val i18nURI = "files/i18n/${language.code}.lrf"
        val lrf = resProvider.readBytes(i18nURI).decodeToString()
        val lines = lrf.split("\n")
        var token = "-"
        var text = ""
        lines.forEachIndexed { _, line ->
            val pipeAt = line.indexOf("|")
            if (pipeAt == 6) {
                if (token.length == 6) // new language resource. Store current.
                    map = map.plus(Pair(token, text))
                token = line.substring(0, 6)
                text = line.substring(7)
            } else if (pipeAt == 0) { // continued multiline language resource text
                text += "\n" + line.substring(1)
            }
        }
        // add last entry
        if (token.isNotEmpty())
            map = map.plus(Pair(token, text))
        loaded = true
    }

    @Suppress("Unused")
    fun isValidI18nReference(toCheck: String): Boolean
    {
        return !((toCheck.length < 7) || (toCheck.substring(6, 7) != "|")) &&
                loaded && (map[toCheck.substring(0,6)] != null)
    }

    // translation and placeholder replacement
    fun t(vararg args: String): String {
        if (args.isEmpty())
            return ""
        val i18nResource = args[0]
        if ((i18nResource.length < 7) || (i18nResource.substring(6, 7) != "|"))
            return i18nResource
        val token = i18nResource.substring(0, 6)
        val text =
            if (!loaded)
                i18nResource.substring(7)
            else
                map[token] ?: i18nResource.substring(7)
        if (text.isEmpty())
            return i18nResource.substring(7)
        else {
            var replaced = text
            for (i: Int in 1 ..< args.size)
                replaced = replaced.replace("%$i", args[i])
            return replaced
        }
    }

}