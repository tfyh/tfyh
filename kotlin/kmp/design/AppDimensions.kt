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

import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp

data class AppDimensions(
    val smallSpacing: Dp,
    val regularSpacing: Dp,
    val largeSpacing: Dp,
    val textFieldHeight: Dp = TextFieldDefaults.MinHeight,
    val pickerSize: Dp = TextFieldDefaults.MinHeight * 10
)

val appDimensions = AppDimensions(
    smallSpacing = 3.dp,
    regularSpacing = 12.dp,
    largeSpacing = 20.dp
)

val LocalAppAppDimensions = staticCompositionLocalOf {
    AppDimensions(
        smallSpacing = 0.dp,
        regularSpacing = 0.dp,
        largeSpacing = 0.dp
    )
}
