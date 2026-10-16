package org.tfyh.kmp.ui

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