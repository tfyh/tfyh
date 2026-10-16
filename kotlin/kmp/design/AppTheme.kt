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

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalWindowInfo
import androidx.compose.ui.platform.WindowInfo
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.unit.IntSize

// see https://www.youtube.com/watch?v=eTVVT9cX4Bw
// The structure has been changed in the way, that the design system is split into its parts.

@Composable
fun AppTheme(
    fontFamily: FontFamily,
    isDark: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val typography = appTypography(fontFamily)
    val colorScheme = if (isDark) appDarkColorScheme else appLightColorScheme
    val shapes = appShapes
    val dimensions = appDimensions
    // val localIndication = rememberRipple() this may be something not relevant in Compose Multiplatform
    CompositionLocalProvider(
        LocalAppColorScheme provides colorScheme,
        localAppAppTypography provides typography,
        localAppShape provides shapes,
        LocalAppAppDimensions provides dimensions,
        /// localIndication provides rippleIndication (see above)
        content = content
    )
}

object Theme {
    val colors: AppColorScheme
        @Composable get() = LocalAppColorScheme.current
    val fonts: AppTypography
        @Composable get() = localAppAppTypography.current
    @Suppress("Unused")
    val shapes: AppShapes
        @Composable get() = localAppShape.current
    val dimensions: AppDimensions
        @Composable get() = LocalAppAppDimensions.current
    val windowInfo: WindowInfo
        @Composable get() = LocalWindowInfo.current
}