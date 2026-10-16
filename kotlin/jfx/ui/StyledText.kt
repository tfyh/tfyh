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

package org.tfyh.jfx.ui

import javafx.scene.Node
import javafx.scene.text.Text
import javafx.scene.text.TextFlow

/**
 * Translates the shortcut text styles ~* ~_ ~/ to bold, underline and italic as well as ~\ to the new line character.
 */
object StyledText {

    /**
     * Parse the template on formatting: ~* = &lt;b&gt;; *~ = &lt;/b&gt;; ~_ =
     * &lt;u&gt;; _~ = &lt;/u&gt;; ~/ = &lt;i&gt;; /~ = &lt;/i&gt;; ~\ =
     * &lt;br&gt;;
     */
    private val styles: Map<String, String> = mapOf(
        "~*" to "tfyh-bold",
        "~_" to "tfyh-underline",
        "~/" to "tfyh-italic"
    )

    /**
     * In order to style the text apprOpriately you MUST add StyledText.css to the
     * view-builders style sheets
     */
    const val css = ".tfyh-bold { fx-font-weight:bold; }\n" +
                " .tfyh-underline { -fx-text-decoration: underline; }\n" +
                " .tfyh-italic { -fx-font-style: italic; }"

    private var remainder = ""
    private var nextOpener = ""
    private val styledSnippets : MutableList<Node> = mutableListOf()

    private fun nextSnippet() {
        nextOpener = ""
        var nextOpenerPosition = Int.MAX_VALUE
        for (opener in styles.keys) {
            if (remainder.contains(opener) && (remainder.indexOf(opener) < nextOpenerPosition)) {
                nextOpener = opener
                nextOpenerPosition = remainder.indexOf(opener)
            }
        }
        if (nextOpener.isNotEmpty()) {
            if (nextOpenerPosition > 0)
                styledSnippets.add(Text(remainder.substring(0, nextOpenerPosition)))
            remainder = remainder.substring(nextOpenerPosition + 2)
            val closer = nextOpener.substring(1) + "~"
            val spanText =
                if (remainder.indexOf(closer) < 0) remainder
                else remainder.substring(0, remainder.indexOf(closer))
            val spanStyle = styles[nextOpener]
            if (spanStyle != null) {
                val styledSnippet = Text(spanText)
                styledSnippet.styleClass.add(spanStyle)
                styledSnippets.add(styledSnippet)
            }
            remainder = if (remainder.length > (spanText.length + 2))
                            remainder.substring(spanText.length + 2)
                        else ""
        }
    }

    fun toAnnotatedString(text: String): TextFlow {
        remainder = text.replace("~\\", "\n")
        nextOpener = "?"
        while (nextOpener.isNotEmpty() && remainder.isNotEmpty())
            nextSnippet()
        if (remainder.isNotEmpty())
            styledSnippets.add(Text(remainder))
        val styledTexts = styledSnippets.map { it }.toTypedArray()
        return TextFlow(*styledTexts)
    }
}