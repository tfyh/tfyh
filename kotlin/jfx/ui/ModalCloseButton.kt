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

fun modalCloseButton() {
    Box (
        modifier = Modifier
            .width(Theme.dimensions.textFieldHeight)
            .height(Theme.dimensions.textFieldHeight)
    ) {
        Text(
            text = "⨯",
            color = Theme.colors.color_text_h1_h3,
            style = Theme.fonts.h3,
            modifier = Modifier
                .padding(Theme.dimensions.regularSpacing)
                .clickable {
                    Stage.viewModel.setVisibleModal(false)
                }
        )
    }
}