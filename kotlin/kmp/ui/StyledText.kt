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

package org.tfyh.kmp.ui

import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.withStyle

object StyledText {

    /**
     * Parse the template on formatting: ~* = &lt;b&gt;; *~ = &lt;/b&gt;; ~_ =
     * &lt;u&gt;; _~ = &lt;/u&gt;; ~/ = &lt;i&gt;; /~ = &lt;/i&gt;; ~\ =
     * &lt;br&gt;;
     */
    private val styles: Map<String, SpanStyle> = mapOf(
            "~*" to SpanStyle(fontWeight = FontWeight.Bold),
            "~_" to SpanStyle(textDecoration = TextDecoration.Underline),
            "~/" to SpanStyle(fontStyle = FontStyle.Italic)
    )

    fun toAnnotatedString(text: String): AnnotatedString {
        var remainder = text.replace("~\\", "\n")
        val annotatedString = buildAnnotatedString {
            var nextOpener = "?"
            while (nextOpener.isNotEmpty() && remainder.isNotEmpty()) {
                nextOpener = ""
                var nextOpenerPosition = Int.MAX_VALUE
                for (opener in styles.keys) {
                    if (remainder.contains(opener) && (remainder.indexOf(opener) < nextOpenerPosition)) {
                        nextOpener = opener
                        nextOpenerPosition = remainder.indexOf(opener)
                    }
                }
                if (nextOpener.isNotEmpty()) {
                    append(remainder.substring(0, nextOpenerPosition))
                    remainder = remainder.substring(nextOpenerPosition + 2)
                    val closer = nextOpener.substring(1) + "~"
                    val spanText =
                        if (remainder.indexOf(closer) < 0) remainder
                        else remainder.substring(0, remainder.indexOf(closer))
                    val spanStyle = styles[nextOpener]
                    if (spanStyle != null) {
                        withStyle(style = spanStyle) {
                            append(spanText)
                        }
                    }
                    remainder = remainder.substring(spanText.length + 2)
                }
            }
            if (remainder.isNotEmpty())
                append(remainder)
        }
        return annotatedString
    }
}