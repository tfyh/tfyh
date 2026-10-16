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

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.update
import org.tfyh.control.EventHandlerIF
import org.tfyh.control.Logger
import org.tfyh.data.Config
import org.tfyh.data.DataBase

data class Dimensions(var widthPx: Int, var heightPx: Int)

/**
 * The Stage represents the screen in three layers: the main, the modal, and the dialogue. The
 * Application will use the main to display any information, the modal to entered data in forms
 * and the dialogue to call for user action e.g. in case of errors. The layout of the main is build
 * using Sectors hierarchically.
 */
object Stage: EventHandlerIF {

    private var density = 1.0F
    private var widthPx: Int = 400
    private var heightPx: Int = 400
    private var fontZoom: Float = 1.0F
    internal val logger = Logger("ui")

    private var layoutRoot: Sector? = null

    // modal content
    const val EMPTY_MODAL_CONTENT = "The Modal"
    var modalContent = EMPTY_MODAL_CONTENT

    // dialogue content
    private const val EMPTY_DIALOG_CONTENT = "The Dialog"
    var dialogContent = EMPTY_DIALOG_CONTENT

    fun getModalMaxDimensions() = Dimensions(
        if (widthPx < 500) (widthPx * 0.95F).toInt()
        else if (widthPx < 1200) (widthPx * 0.85F).toInt()
        else 1020,
        if (heightPx < 500) (heightPx * 0.95F).toInt()
        else if (heightPx < 1200) (heightPx * 0.85F).toInt()
        else if (heightPx < 1800) (heightPx * 0.75F).toInt()
        else 1350)
    fun getDialogMaxDimensions() = Dimensions(
        if (widthPx < 500) (widthPx * 0.9F).toInt()
        else if (widthPx < 1200) (widthPx * 0.8F).toInt()
        else 960 ,
        if (heightPx < 500) (heightPx * 0.9F).toInt()
        else if (heightPx < 1200) (heightPx * 0.8F).toInt()
        else if (heightPx < 1800) (heightPx * 0.7F).toInt()
        else 1260)

    fun isPortrait() = ((widthPx.toDouble() / heightPx) < 0.8)
    fun fontZoom() = fontZoom

    val viewModel = StageViewModel()

    /**
     * Show a text as dialogue. The text may have formatting information.
     */
    fun showDialogue(text: String) {
        // TODO
    }

    fun buildUi(uiProviderFactory: UIProviderFactoryIF) {
        // the main will always show up in the background
        val config = Config.getInstance()
        val layoutName =
            if (isPortrait())
                config.getItem(".ui.profiles.dilbo.portrait").valueCsv()
            else
                config.getItem(".ui.profiles.dilbo.landscape").valueCsv()
        val uiItem = config.getItem(".ui.layouts.$layoutName")
        layoutRoot = Sector(uiItem, isPortrait(), uiProviderFactory)
        layoutRoot?.BuildSector(widthPx, heightPx)
        // the modal
        if (viewModel.versionModal.value > 0) {
            // TODO
        }
        // the dialogue
        if (viewModel.versionDialog.value > 0) {
            // TODO
        }
    }

    override fun handleEvent(event: EventHandlerIF.Event): Boolean {
        if (event.type == EventHandlerIF.EventType.DATA_EVENT) {
            val table = DataBase.getInstance().getTableOrNull(event.sourceItem.name()) ?: return false
            layoutRoot?.notifyChange(table, event.uid, event.name) ?: return false
            return true
        }
        return false
    }

    class StageViewModel {

        private val _versionMain: MutableStateFlow<Int> = MutableStateFlow(0)
        internal val versionMain: StateFlow<Int> = _versionMain
        private val _versionModal: MutableStateFlow<Int> = MutableStateFlow(0)
        internal val versionModal: StateFlow<Int> = _versionModal
        private val _versionDialog: MutableStateFlow<Int> = MutableStateFlow(0)
        internal val versionDialog: StateFlow<Int> = _versionDialog
        fun setVisibleMain(visibility: Boolean) {
            _versionMain.update { if (visibility) _versionMain.value + 1 else 0 }
        }

        fun setVisibleModal(visibility: Boolean) {
            _versionModal.update { if (visibility) _versionModal.value + 1 else 0 }
        }

        fun setVisibleDialog(visibility: Boolean) {
            _versionDialog.update { if (visibility) _versionDialog.value + 1 else 0 }
        }
    }

}
