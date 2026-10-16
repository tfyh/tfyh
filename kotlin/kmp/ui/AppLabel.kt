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

import androidx.compose.foundation.layout.wrapContentSize
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.style.TextAlign
import org.tfyh.kmp.design.Theme

@Composable
fun AppLabel(
    text: String,
    modifier: Modifier = Modifier,
    large: Boolean = false,
    color: Color = Theme.colors.color_text_body,
    textAlign: TextAlign = TextAlign.Start
) {
    modifier.wrapContentSize()
    Text(
        StyledText.toAnnotatedString(text),
        color = color,
        style = if (large) Theme.fonts.h4 else Theme.fonts.h5,
        textAlign = textAlign,
        modifier = modifier
    )
}