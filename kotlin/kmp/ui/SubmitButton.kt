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

import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import org.tfyh.kmp.design.Theme

@Composable
fun SubmitButton(
    text: String = "submit button",
    onClick: () -> Unit = {}
) {
    Button(
        onClick = onClick,
        colors = ButtonDefaults.buttonColors(
            containerColor = Theme.colors.color_background_form_button,
            contentColor = Theme.colors.color_text_form_button,
            disabledContainerColor = Theme.colors.color_background_form_button_hover,
            disabledContentColor = Theme.colors.color_text_form_button_hover
        ),
        shape = TextFieldDefaults.shape
    ) {
        Text(
            text = text,
            style = Theme.fonts.p,
        )
    }
}