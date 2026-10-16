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

import org.tfyh.control.EventHandlerIF
import org.tfyh.data.Config
import org.tfyh.data.Item
import org.tfyh.data.Table

/**
 * The Sector may be understood as empty container carrying UI content provided by a UIProvider.
 */
class Sector(val item: Item, val isPortraitLayout: Boolean,
             private val uiProviderFactory: UIProviderFactoryIF) {

    // read the configuration
    /**
     * The minimum size as Double value.
     */
    private val sizeMin = item.getChild("size_min")?.value() as Int? ?: 0

    /**
     * The relative weight of the Sector in its parent's layout
     */
    private val weight = (item.getChild("weight")?.value() as Int? ?: 1.0).toDouble()

    /**
     * The orientation of the sector's children. If true they are arranged as row else as column.
     */
    private val horizontal = item.getChild("horizontal")?.value() as Boolean? ?: false

    // the pixel size and offset values
    private var w = 0
    private var h = 0
    private var x = 0
    private var y = 0

    // mark whether the size is the min size and the sector has children
    private var isMinSize = false

    private val dimensions = Dimensions(400, 400)
    private var offsetX = x
    private var offsetY = y
    /**
     * The ui interface content provider for this sector. Will only be needed if the sector is a leaf.
     */
    private val uiProvider: UIProviderIF? = uiProviderFactory.getProvider(
        item.getChild("provider") ?: Config.getInstance().invalidItem, dimensions)

    private val childSectors = mutableListOf<Sector>()

    fun getLayoutHierarchy(): String {
        var branchAsString = this.toString() + "\n"
        for (childSector in childSectors)
            branchAsString += childSector.getLayoutHierarchy()
        return branchAsString
    }

    fun notifyChange(table: Table, uid: String, modification: String) {
        val event = EventHandlerIF.Event(EventHandlerIF.EventType.DATA_EVENT,
            modification, uid, table.record.item)
        if (uiProvider != null) {
            if (uiProvider is EventHandlerIF) {
                uiProvider.handleEvent(event)
            }
        }
        for (childSector in childSectors)
            childSector.notifyChange(table, uid, modification)
    }

    private fun size() = if (horizontal) w else h

    private fun setAllDimensions() {
        dimensions.widthPx = w
        dimensions.heightPx = h
        offsetX = x
        offsetY = y
    }

    /**
     * Return true if this Sector has no embedded sectors.
     */
    fun isLeaf(): Boolean {
        for (child in item.children)
            if (itemIsSector(child))
                return false
        return true
    }

    /**
     * Recursive layout computation. Call by the stage to compute all sizes and offsets. For
     * the composition only the leaf layer is drawn. Call it with stage width and height in dp, the offset
     * of the stage is always [0, 0]
     */
    internal fun buildSector(stageWidthPx: Int = 0, stageHeightPx: Int = 0) {
        w = if (stageWidthPx > 0) stageWidthPx else w
        h = if (stageHeightPx > 0) stageHeightPx else h
        var sumWeight = 0.0
        // compute sum of weight
        for (child in item.getChildren()) {
            if (itemIsSector(child)) {
                sumWeight += (child.getChild("weight")?.value() as Int? ?: 1.0).toDouble()
                val childSector = Sector(child, this.isPortraitLayout, uiProviderFactory)
                // the list is used for size computation, the map for placement o stage
                childSectors.add(childSector)
            }
        }

        composeSector()
        if (childSectors.isEmpty()) return

        // compute sizes of child sectors and trigger composition
        var sumMinSizes = 0.0
        var sumMinWeight = 0.0
        var sumSizes: Double
        var adjusted: Boolean
        do {
            // the computation must continue, until all size_min constraints are met.
            adjusted = false
            sumSizes = 0.0
            // the elements which get their minimum size do not take part at the distribution
            val pxPerWeight = (size() - sumMinSizes) / (sumWeight - sumMinWeight)
            for (sector in childSectors) {
                // compute size
                var sectorSize =
                    if (sector.isMinSize)
                        sector.sizeMin
                    else
                        (sector.weight * pxPerWeight).toInt()
                // adjust its size if it's below the min constraint and sufficient room is left.
                if ((sectorSize < sector.sizeMin)
                    && (sumSizes + sector.sizeMin < size())) {
                    sectorSize = sector.sizeMin
                    sumMinSizes += sectorSize
                    sumMinWeight += sector.weight
                    sector.isMinSize = true
                    adjusted = true
                }
                if (horizontal) sector.w = sectorSize else sector.h = sectorSize
                sumSizes += sectorSize
            }
        // if a child sector has been adjusted, the remaining space is shrunk and
        // needs redistribution
        } while (adjusted)

        // set dimensions and offset for the child sectors
        var offset = 0
        for (sector in childSectors) {
            if (horizontal) {
                sector.h = h
                sector.y = y
                sector.x = x + offset
                offset += sector.w
            }
            else {
                sector.w = w
                sector.x = x
                sector.y = y + offset
                offset += sector.h
            }
            sector.setAllDimensions()
        }

        // drill down. Dimension of the child were already set.
        for (sector in childSectors)
             sector.buildSector()
    }

    fun composeSector() {
        /** TODO
        Box(
            modifier = Modifier
                .offset(x = offsetX, y = offsetY)
                .width(dimensions.widthPx).height(dimensions.heightPx)
                .background(Theme.colors.color_background_body)
                .border(
                    BorderStroke(
                        width = 2.dp,
                        color = Theme.colors.color_background_menubar
                    )
                )
                .zIndex(1.0F)
        ) {
            if (isLeaf())
                uiProvider?.composeContent()
        }*/
    }

    /**
     * get a readable String for debugging purposes
     */
    override fun toString(): String {
        return "Sector ${item.name()}@${item.path()} $w*$h ($x,$y)"
    }

    companion object {

        /**
         * Return true, if the item represents a sector configuration, ie the value type is "template"
         * and the reference is ".templates.sector".
         */
        fun itemIsSector(item: Item) = (item.valueType() == "template") &&
                (item.valueReference() == ".templates.sector")

        }
}