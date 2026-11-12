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
import javafx.scene.Cursor
import javafx.scene.image.Image
import javafx.scene.image.ImageView
import javafx.scene.input.MouseButton
import javafx.scene.layout.Background
import javafx.scene.layout.BackgroundFill
import javafx.scene.layout.CornerRadii
import javafx.scene.layout.HBox
import javafx.scene.layout.Region
import javafx.scene.layout.StackPane
import javafx.scene.paint.Paint
import org.dilbo.core.EventHandler
import org.tfyh.control.EventHandlerIF
import org.tfyh.data.Item
import org.tfyh.jfx.design.AppColours
import org.tfyh.jfx.design.AppDimensions
import org.tfyh.jfx.design.AppFonts

data class ListItem (
    val uid: String,
    val icon: Image,
    val text: String,
    val providedByItem: Item,
    val evenRow: Boolean = false,
    val headerRow: Boolean = false,
    var visible: Boolean = true
) {

    private val eventHandler = EventHandler.getSingleton()

    fun build(): Region {
        val backgroundDefault =
            if (headerRow) Background(
                    BackgroundFill(
                        Paint.valueOf(AppColours.hex(AppColours.background_form_input_hover)),
                        CornerRadii(3.0), null
                    )
                )
            else if (evenRow)
                Background(BackgroundFill(Paint.valueOf(AppColours.hex(AppColours.background_table_even_rows)),
                    CornerRadii(3.0), null))
            else
                AppColours.backgroundTransparent()
        val border = if (headerRow) AppColours.borderSolid(AppDimensions.smallSpacing, "FFFFFFFF") else AppColours.borderNone()
        if (visible) {
            val clickEvent = EventHandlerIF.Event(EventHandlerIF.EventType.UI_EVENT, "doubleClick", uid, providedByItem)
            val imageView = ImageView(icon).apply {
                fitWidth = 24.0 * ViewBuilder.fontZoom
                isPreserveRatio = true
            }
            val appLabel = if (headerRow) appLabel(text, AppFonts.h3, AppColours.text_h1_h3)
                            else appLabel(text, AppFonts.p)
            val listItem = HBox(AppDimensions.regularSpacing, imageView, appLabel).apply {
                background = backgroundDefault
                this.border = border
                onMouseEntered = { background = AppColours.background(AppColours.background_form_input_hover) }
                onMouseExited = { background = backgroundDefault }
                if (headerRow)
                    onMouseClicked = { event ->
                        if (event.button.equals(MouseButton.PRIMARY)) {
                            clickEvent.name = "collapse"
                            eventHandler.handleEvent(clickEvent)
                        }
                    }
                else
                    onMouseClicked = { event ->
                        if (event.button.equals(MouseButton.PRIMARY)) {
                            if (event.clickCount == 1)
                                clickEvent.name = "click"
                            if (event.clickCount == 2)
                                clickEvent.name = "doubleClick"
                        } else if (event.button.equals(MouseButton.SECONDARY))
                            clickEvent.name = "rightClick"
                        eventHandler.handleEvent(clickEvent)
                    }
                cursor = Cursor.HAND
                padding =
                    Insets(AppDimensions.smallSpacing, AppDimensions.smallSpacing, 0.0, AppDimensions.smallSpacing)
            }
            return listItem
        }
        return Region()
    }
}



