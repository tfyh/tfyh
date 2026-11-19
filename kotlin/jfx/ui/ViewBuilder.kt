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
import javafx.geometry.Insets
import javafx.scene.Node
import javafx.scene.Scene
import javafx.scene.layout.Pane
import javafx.scene.layout.VBox
import javafx.stage.Modality
import javafx.stage.Stage
import javafx.stage.Window
import javafx.util.Builder
import org.tfyh.control.EventHandlerIF
import org.tfyh.control.EventHandlerIF.EventType
import org.tfyh.control.Logger
import org.tfyh.control.LoggerSeverity
import org.tfyh.data.Config
import org.tfyh.data.DataBase
import org.tfyh.jfx.design.AppColours

/**
 * The ViewBuilder represents the screen in three layers: the main, the modal, and the dialogue. The
 * Application will use the main to display any information, the modal to entered data in forms
 * and the dialogue to call for user action e.g. in case of errors. The layout of the main is build
 * using Sectors hierarchically.
 */
class ViewBuilder private constructor(): Builder<Pane?>, EventHandlerIF {

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

    private lateinit var eventHandler: EventHandlerIF
    private lateinit var uiProviderFactory: UIProviderFactoryIF
    private var widthPx: Int = 400
    private var heightPx: Int = 400
    internal val logger = Logger("ui")

    private var layoutRoot: Sector? = null
    private val registeredHandlers: MutableMap<EventHandlerIF, MutableList<EventType>> = mutableMapOf()

    class DialogOrModalStage(isModal: Boolean, owner: Window) : Stage() {
        init {
            if (isModal) initModality(Modality.APPLICATION_MODAL)
            try {
                initOwner(owner)
            } catch (_: IllegalStateException) {}
            onCloseRequest = {
                clearOnClose()
                val sceneRoot = scene.root
                if (sceneRoot is Pane)
                    getInstance().removeHandlerByPane(sceneRoot)
            }
        }

        fun removeContent(pane: Pane) {
            val children = mutableListOf<Node>()
            // copy the children to avoid a concurrent modification exception
            pane.children.toCollection(children)
            for (child in children) {
                if (child is Pane)
                    removeContent(child)
                else pane.children.remove(child)
            }
        }
        fun clearOnClose() {
            val rootNode = scene.root
            if (rootNode is Pane)
                removeContent(rootNode)
        }
    }

    private val dialogInsets = Insets(30.0)
    private val modalInsets = Insets(20.0)

    private lateinit var editDialogue: DialogOrModalStage
    private lateinit var dialogue: DialogOrModalStage
    private lateinit var modal: DialogOrModalStage

    /**
     * Bringing the modal back to front in stagesToFron() does remove the selection popup of autocompletion fields.
     * Set modalPopupActive to true to disable the modal's toFront() call
     */
    var modalPopupActive = false
    private lateinit var main : Stage

    fun init(eventHandler: EventHandlerIF, uiProviderFactory: UIProviderFactoryIF, primaryStage: Stage, width: Int, height: Int) {
        this.eventHandler = eventHandler
        this.uiProviderFactory = uiProviderFactory
        this.main = primaryStage
        this.modal = DialogOrModalStage(true, main).apply {
            onCloseRequest = {  }
        }
        this.dialogue = DialogOrModalStage(false, modal)
        this.editDialogue = DialogOrModalStage(false, modal)
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
     * Print the hierarchy of the content pane to the console
     * For debugging purposes.
     */
    private fun printViewHierarchy(content: Pane, indent: String = "-> ") {
        println("$indent$content")
        val childIndent = "$indent  "
        for (childContent in content.children)
            if (childContent is Pane)
                printViewHierarchy(childContent, childIndent)
            else
                println("$indent$childContent @ ${childContent.scene}")
    }

    /**
     * Remove the event handlers which are displayed as UI-provider on a pane from the EventHandler registration.
     */
    private fun removeEventHandlers(pane: Pane) {
        removeHandlerByPane(pane)
        for (childPane in pane.children)
            if (childPane is Pane)
                removeEventHandlers(childPane)
    }

    /**
     * Show a text as dialogue. The text may have formatting information. Invoked using the Platform.runLater
     * thread shift mechanics.
     */
    fun showDialogue(text: String) {
        val formatted = StyledText.toAnnotatedString(text)
        Platform.runLater {
            dialogue.scene = Scene(VBox(10.0, formatted).apply {
                padding = dialogInsets
                if (Config.getInstance().debug)
                    border = AppColours.borderTest()
            })
            dialogue.show()
        }
    }

    /**
     * Show the translation suggestion edit dialogue. Invoked using the Platform.runLater thread shift mechanics.
     */
    fun showEditDialogue(content: Pane) {
        editDialogue.scene = Scene(content.apply {
            padding = modalInsets
            if (Config.getInstance().debug)
                border = AppColours.borderTest()
        })
        editDialogue.show()
    }

    /**
     * Show the modal. Must be invoked from the UI Thread.
     */
    fun showModal(content: Pane) {
        modal.scene = Scene(content.apply {
            padding = modalInsets
            if (Config.getInstance().debug)
                border = AppColours.borderTest()
        })
        modal.show()
        // for debug purposes only
        // printViewHierarchy(content)
    }

    /**
     * Hide the edit dialogue. Must be invoked from the UI Thread.
     */
    fun hideEditDialogue() {
        editDialogue.hide()
    }

    /**
     * Hide the modal. Must be invoked from the UI Thread.
     */
    fun hideModal() {
        modal.hide()
        modalPopupActive = false
    }

    override var isDisplayedOn: Pane? = null
        get() = field // Access the backing field
        set(value) { field = value } // Modify the backing field

    override fun handleEvent(event: EventHandlerIF.Event): Boolean {

        var isHandled = false
        for (registeredHandler in registeredHandlers.keys)
            if (registeredHandlers[registeredHandler]?.contains(event.type) ?: false)
                isHandled = (registeredHandler.handleEvent(event) or isHandled)

        if (event.type == EventType.DATA_EVENT) {
            val table = DataBase.getInstance().getTableOrNull(event.sourceItem.name()) ?: return false
            layoutRoot?.notifyChange(table, event.uid, event.name) ?: return false
            return true
        }
        return isHandled
    }

    override fun build(): Pane {
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
        this.layoutRoot = layoutRoot
        val main = layoutRoot.build()
        // for debugging purposes only
        // printViewHierarchy(main)
        return main
    }

    fun registerHandler(eventHandler: EventHandlerIF, eventType: EventType) {
        val registration = registeredHandlers[eventHandler] ?: mutableListOf()
        registration.add(eventType)
        registeredHandlers[eventHandler] = registration
        Config.getInstance().logger.log(LoggerSeverity.DEBUG,"ViewBuilder.registerHandler()",
            "Registered ${eventHandler}. All handlers size = " + registeredHandlers.size)
    }

    fun removeHandler(eventHandler: EventHandlerIF) {
        registeredHandlers.remove(eventHandler)
        Config.getInstance().logger.log(LoggerSeverity.DEBUG,"ViewBuilder.removeHandler()",
            "Removed $eventHandler. " + registeredHandlers.size + " are left.")
    }

    fun removeHandlerByPane(isDisplayedOn: Pane?) {
        if (isDisplayedOn == null)
            return
        // Collect all to remove and remove this collection to avoid concurrency violations
        val toRemove = mutableListOf<EventHandlerIF>()
        for (handler in registeredHandlers.keys)
            if (handler.isDisplayedOn == isDisplayedOn)
                toRemove.add(handler)
        for (handler in toRemove)
            removeHandler(handler)
    }

}
