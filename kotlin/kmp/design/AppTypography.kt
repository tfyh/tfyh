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

package org.tfyh.kmp.design

import androidx.compose.runtime.Composable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import androidx.compose.ui.unit.times
import org.tfyh.kmp.ui.Stage

data class AppTypography(
    val h1: TextStyle,
    val h2: TextStyle,
    val h3: TextStyle,
    val h4: TextStyle,
    val h5: TextStyle,
    val p: TextStyle,
)

// The provided typography must be wrapped into a
// composable function to be able to load the resources
@Composable
fun appTypography(
    fontFamily: FontFamily): AppTypography {

    // use the types as in the CSS of the web application.
    val rem = (14 * Stage.fontZoom()).sp
    return AppTypography(
        h1 = TextStyle(
            fontSize = 2.25 * rem,
            fontWeight = FontWeight.Normal,
            fontFamily = fontFamily,
            color = Theme.colors.color_text_h1_h3,
        ),
        h2 = TextStyle(
            fontSize = 1.875 * rem,
            fontWeight = FontWeight.Normal,
            fontFamily = fontFamily,
            color = Theme.colors.color_text_h1_h3,
        ),
        h3 = TextStyle(
            fontSize = 1.5 * rem,
            fontWeight = FontWeight.Normal,
            fontFamily = fontFamily,
            color = Theme.colors.color_text_h1_h3,
        ),
        h4 = TextStyle(
            fontSize = 1.25 * rem,
            fontWeight = FontWeight.Bold,
            fontFamily = fontFamily,
            color = Theme.colors.color_text_p_h4_h6_etc,
        ),
        h5 = TextStyle(
            fontSize = 1.125 * rem,
            fontWeight = FontWeight.Normal,
            fontFamily = fontFamily,
            color = Theme.colors.color_text_p_h4_h6_etc,
        ),
        p = TextStyle(
            fontSize = rem,
            fontWeight = FontWeight.Thin,
            fontFamily = fontFamily,
            color = Theme.colors.color_text_p_h4_h6_etc,
        ),
    )
}

val localAppAppTypography = staticCompositionLocalOf {
    AppTypography(
        h1 = TextStyle.Default,
        h2 = TextStyle.Default,
        h3 = TextStyle.Default,
        h4 = TextStyle.Default,
        h5 = TextStyle.Default,
        p = TextStyle.Default
    )
}
