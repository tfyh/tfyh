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

import androidx.compose.runtime.Composable

/**
 * The fyh framework provides support for the UI composition and handles the screen layout
 * within the Stage and Sector classes. The application can add content to the screen by
 * providing content to the Sector class which will be included in the composition.
 */
interface UIProviderIF {
    /**
     * The content composition which will be part of the composition tree
     */
    @Composable
    fun ComposeContent()
}