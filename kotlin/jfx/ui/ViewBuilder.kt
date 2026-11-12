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

import javafx.application.Platform
import javafx.beans.property.SimpleStringProperty
import javafx.geometry.Insets
import javafx.scene.Scene
import javafx.scene.layout.HBox
import javafx.scene.layout.Pane
import javafx.scene.layout.Region
import javafx.scene.layout.VBox
import javafx.stage.Modality
import javafx.stage.Stage
import javafx.stage.Window
import javafx.util.Builder
import org.dilbo.core.EventHandler
import org.tfyh.control.EventHandlerIF
import org.tfyh.control.Logger
import org.tfyh.data.Config
import org.tfyh.data.DataBase
import org.tfyh.data.NoZoneDateTime
import org.tfyh.jfx.design.AppColours
import org.tfyh.jfx.design.AppDimensions
import org.tfyh.util.Language
import org.tfyh.util.Timer

/**
 * The ViewBuilder represents the screen in three layers: the main, the modal, and the dialogue. The
 * Application will use the main to display any information, the modal to entered data in forms
 * and the dialogue to call for user action e.g. in case of errors. The layout of the main is build
 * using Sectors hierarchically.
 */
class ViewBuilder private constructor(): Builder<Region?>, EventHandlerIF {

    companion object {

        val dialogSizing = arrayOf(0.8F, 0.7F, 0.6F)
        val modalSizing = arrayOf(0.9F, 0.8F, 0.7F)
        var fontZoom: Float = 1.2F

        private val instance = ViewBuilder()
        fun getInstance() = instance

    }

    data class Dimensions(var widthPx: Int, var heightPx: Int) {
        fun isPortrait() = widthPx < heightPx
    }

    private lateinit var eventHandler: EventHandler
    private lateinit var uiProviderFactory: UIProviderFactoryIF
    private var widthPx: Int = 400
    private var heightPx: Int = 400
    internal val logger = Logger("ui")

    private var layoutRoot: Sector? = null

    class DialogOrModalStage(isModal: Boolean, owner: Window) : Stage() {
        init {
            if (isModal) initModality(Modality.APPLICATION_MODAL)
            try {
                initOwner(owner)
            } catch (_: IllegalStateException) {}
        }
    }

    private val dialogInsets = Insets(30.0)
    private val modalInsets = Insets(20.0)

    private lateinit var dialog: DialogOrModalStage
    private lateinit var modal: DialogOrModalStage

    /**
     * Bringing the modal back to front in stagesToFron() does remove the selection popup of autocompletion fields.
     * Set modalPopupActive to true to disable the modal's toFront() call
     */
    var modalPopupActive = false
    private lateinit var main : Stage

    fun init(eventHandler: EventHandler, uiProviderFactory: UIProviderFactoryIF, primaryStage: Stage, width: Int, height: Int) {
        this.eventHandler = eventHandler
        this.uiProviderFactory = uiProviderFactory
        this.main = primaryStage
        this.modal = DialogOrModalStage(true, main)
        this.dialog = DialogOrModalStage(false, modal)
        this.widthPx = width
        this.heightPx = height
    }

    /**
     * Get the maximum size of a modal depending on the sizing constraints and the screen width
     */
    fun getModalMaxDimensions(sizing: Array<Float>) = Dimensions(
        if (widthPx < 500) (widthPx * sizing[0]).toInt()
        else if (widthPx < 1200) (widthPx * sizing[1]).toInt()
        else (1200 * sizing[1]).toInt(),
        if (heightPx < 500) (heightPx * sizing[0]).toInt()
        else if (heightPx < 1200) (heightPx * sizing[1]).toInt()
        else if (heightPx < 1800) (heightPx * sizing[2]).toInt()
        else (1800 * sizing[2]).toInt()
    )

    fun isPortrait() = ((widthPx.toDouble() / heightPx) < 0.8)

    /**
     * Show a text as dialogue. The text may have formatting information. Invoked using the Platform.runLater
     * thread shift mechanics.
     */
    fun showDialogue(text: String) {
        val formatted = StyledText.toAnnotatedString(text)
        Platform.runLater {
            dialog.scene = Scene(VBox(10.0, formatted).apply {
                padding = dialogInsets
                if (Config.getInstance().debug) border = AppColours.borderTest()
            })
            dialog.show()
        }
    }

    /**
     * Show the modal. Must be invoked from the UI Thread.
     */
    fun showModal(content: Region) {
        modal.scene = Scene(content.apply {
            padding = modalInsets
            if (Config.getInstance().debug) border = AppColours.borderTest()
        })
        modal.show()
    }

    /**
     * Hide the modal. Must be invoked from the UI Thread.
     */
    fun hideModal() {
        modal.hide()
        modalPopupActive = false
    }

    override fun handleEvent(event: EventHandlerIF.Event): Boolean {
        if (event.type == EventHandlerIF.EventType.DATA_EVENT) {
            val table = DataBase.getInstance().getTableOrNull(event.sourceItem.name()) ?: return false
            layoutRoot?.notifyChange(table, event.uid, event.name) ?: return false
            return true
        }
        return false
    }

    override fun build(): Region {
        // the main will always show up in the background
        val config = Config.getInstance()
        val layoutName =
            if (isPortrait())
                config.getItem(".ui.profiles.dilbo.portrait").valueCsv()
            else
                config.getItem(".ui.profiles.dilbo.landscape").valueCsv()
        val uiItem = config.getItem(".ui.layouts.$layoutName")
        val layoutRoot = Sector(uiItem, uiProviderFactory, null)
        layoutRoot.setDimensions(widthPx, heightPx)
        // the modal
            // TODO
        // the dialogue
            // TODO
        this.layoutRoot = layoutRoot
        return layoutRoot.build()
    }

}
