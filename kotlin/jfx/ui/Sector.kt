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

import javafx.geometry.Insets
import javafx.scene.layout.Background
import javafx.scene.layout.BackgroundFill
import javafx.scene.layout.Border
import javafx.scene.layout.BorderStroke
import javafx.scene.layout.BorderStrokeStyle
import javafx.scene.layout.BorderWidths
import javafx.scene.layout.CornerRadii
import javafx.scene.layout.HBox
import javafx.scene.layout.Pane
import javafx.scene.layout.Region
import javafx.scene.layout.VBox
import org.tfyh.control.EventHandlerIF
import org.tfyh.data.Config
import org.tfyh.data.Item
import org.tfyh.data.Table
import org.tfyh.jfx.design.AppColours
import org.tfyh.jfx.design.AppDimensions

/**
 * The Sector may be understood as empty container carrying UI content provided by a UIProvider.
 */
class Sector(val item: Item, private val uiProviderFactory: UIProviderFactoryIF, val parent: Sector?): Pane() {

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

    private val dimensions = ViewBuilder.Dimensions(400, 400)
    private var offsetX = x
    private var offsetY = y

    private lateinit var pane: Pane
    /**
     * The ui interface content provider for this sector. Will only be needed if the sector is a leaf.
     */
    private val uiProvider: UIProviderIF? = uiProviderFactory.getProvider(
        item.getChild("provider") ?: Config.getInstance().invalidItem, dimensions, this)

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

    private fun setInternalDimensions() {
        dimensions.widthPx = w
        dimensions.heightPx = h
        offsetX = x
        offsetY = y
    }

    fun setDimensions(w: Int, h: Int, x: Int = 0, y: Int = 0) {
        this.w = w
        this.h = h
        this.x = x
        this.y = y
        setInternalDimensions()
    }

    internal fun getHierarchy(): String {
        var hierarchy = "Layout hierarchy:\n"
        var currentSector: Sector? = this
        do {
            hierarchy += currentSector.toString() + "\n"
            currentSector = currentSector?.parent
        } while (currentSector != null)
        return hierarchy
    }

    /**
     * Recursive layout computation. Call by the ViewBuilder to compute all sizes and offsets. For
     * the composition only the leaf layer is drawn. Call it with stage width and height in dp, the offset
     * of the stage is always [0, 0]
     */
    internal fun buildSector(): List<Region> {
        var sumWeight = 0.0
        // compute sum of weight
        for (child in item.getChildren()) {
            if (itemIsSector(child)) {
                sumWeight += (child.getChild("weight")?.value() as Int? ?: 1.0).toDouble()
                val childSector = Sector(child, uiProviderFactory, this)
                // the list is used for size computation, the map for placement o stage
                childSectors.add(childSector)
            }
        }

        if (childSectors.isEmpty())
            return listOf(HBox(uiProvider?.build() ?: appLabel(this.toString())).apply {
                border = sectorBorder
                background = sectorBackground
                setPrefSize(w.toDouble(), h.toDouble())
            })

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
            sector.setInternalDimensions()
        }

        // drill down. Dimension of the child were already set.
        val panes = mutableListOf<Region>()
        for (sector in childSectors)
             panes.add(sector.build())
        return panes
    }

    /**
     * build the sector.
     */
    fun build(): Region {
        pane = if (horizontal) HBox(0.0, *buildSector().toTypedArray())
                   else VBox(0.0, *buildSector().toTypedArray()).apply {
            setPrefSize(w.toDouble(), h.toDouble())
            setMinSize(w.toDouble(), h.toDouble())
            setMaxSize(w.toDouble(), h.toDouble())
            if (childSectors.isEmpty())
                onMouseClicked = { e ->
                    if (e.isControlDown) ViewBuilder.getInstance().showDialogue(this@Sector.getHierarchy())
                }
            if (Config.getInstance().debug)
                border = AppColours.borderTest()
        }
        return pane
    }

    /**
     * Update the sector content by removing the single UI provider and adding a new one.
     */
    fun update() {
        if (childSectors.isEmpty()) {
            pane.children.clear()
            pane.children.addAll(*buildSector().toTypedArray())
        }
    }

    /**
     * get a readable String for debugging purposes
     */
    override fun toString(): String {
        return "Sector ${item.name()}@${item.path()} $w*$h ($x,$y)"
    }

    companion object {

        val sectorInsets = Insets(AppDimensions.smallSpacing)
        val sectorBackground = Background(BackgroundFill(
            AppColours.background_body, CornerRadii.EMPTY, sectorInsets
        ))
        val sectorBorder = Border(BorderStroke(
            AppColours.background_body, BorderStrokeStyle.SOLID,
            CornerRadii.EMPTY, BorderWidths(0.0), sectorInsets
        ))

        /**
         * Return true, if the item represents a sector configuration, ie the value type is "template"
         * and the reference is ".templates.sector".
         */
        fun itemIsSector(item: Item) = (item.valueType() == "template") &&
                (item.valueReference() == ".templates.sector")

        }
}