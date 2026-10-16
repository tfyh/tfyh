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

import org.tfyh.data.Item

/**
 * In order to provide a user interface the app must implement ui-provider classes. The classes become
 * instantiated using this factory.
 */
interface UIProviderFactoryIF {
    /**
     * Instantiate an ui-provider which provides content for a sector to be displayed
     * @param item The provider's configuration item
     * @param dimensions The window dimensions for the provider to compose its content
     */
    fun getProvider(item: Item, dimensions: Dimensions): UIProviderIF?
}