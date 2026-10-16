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

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.wrapContentSize
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.zIndex
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.update
import org.tfyh.control.EventHandlerIF
import org.tfyh.control.Logger
import org.tfyh.data.Config
import org.tfyh.data.DataBase
import org.tfyh.kmp.design.AppTheme
import org.tfyh.kmp.design.Theme

data class Dimensions(var widthDp: Dp, var heightDp: Dp) {
    fun widthDp() = widthDp
    fun heightDp() = heightDp
    fun isPortrait() = heightDp > widthDp
}

/**
 * The Stage represents the screen in three layers: the main, the modal, and the dialogue. The
 * Application will use the main to display any information, the modal to entered data in forms
 * and the dialogue to call for user action e.g. in case of errors. The layout of the main is build
 * using Sectors hierarchically.
 */
object Stage: EventHandlerIF {

    private var density = 1.0F
    private var widthDp: Dp = 400.dp
    private var heightDp: Dp = 400.dp
    private var sizePx: Int = 160000
    private var fontZoom: Float = 1.0F
    internal val logger = Logger("ui")

    private var layoutRoot: Sector? = null

    // modal content
    val emptyModalContent: @Composable () -> Unit = {
        Text(text = "The Modal", textAlign = TextAlign.Center,style = Theme.fonts.h2)
    }
    var modalContent = emptyModalContent

    // dialogue content
    private val emptyDialogContent: @Composable () -> Unit = {
        Text(text = "The Dialog", textAlign = TextAlign.Center,style = Theme.fonts.h2)
    }
    var dialogContent = emptyDialogContent

    fun setDimensions(width: Int, height: Int, stageDensity: Float) {
        density = stageDensity
        widthDp = pxToDp(width.toDouble())
        heightDp = pxToDp(height.toDouble())
        sizePx = (width * height)
        fontZoom = if (width < height) widthDp.value / 500 else heightDp.value / 500
    }

    fun getModalMaxDimensions() = Dimensions(
        if (widthDp < 500.dp) widthDp * 0.9F
        else if (widthDp < 1200.dp) widthDp * 0.8F
        else 960.dp,
        if (heightDp < 500.dp) heightDp * 0.9F
        else if (heightDp < 1200.dp) heightDp * 0.8F
        else if (heightDp < 1800.dp) heightDp * 0.7F
        else 1260.dp)
    fun getDialogMaxDimensions() = Dimensions(
        if (widthDp < 500.dp) widthDp * 0.9F
        else if (widthDp < 1200.dp) widthDp * 0.8F
        else 960.dp,
        if (heightDp < 500.dp) heightDp * 0.9F
        else if (heightDp < 1200.dp) heightDp * 0.8F
        else if (heightDp < 1800.dp) heightDp * 0.7F
        else 1260.dp)

    internal fun pxToDp(px: Double): Dp = (px / density).dp
    internal fun dpToPx(dp: Dp): Double = (dp * density).value.toDouble()
    fun isPortrait() = (widthDp / heightDp < 0.8)
    fun fontZoom() = fontZoom

    val viewModel = StageViewModel()

    /**
     * Show a text as dialogue. The text may have formatting information.
     */
    fun showDialogue(text: String) {
        dialogContent = {
            Column(modifier = Modifier.fillMaxWidth().padding(Theme.dimensions.largeSpacing),
                horizontalAlignment = Alignment.CenterHorizontally) {
                AppLabel(text)
                SubmitButton(text = "Ok", onClick = { viewModel.setVisibleDialog(false) } )
            }
        }
        viewModel.setVisibleDialog(true)
    }

    @Composable
    fun BuildUi(fontFamily: FontFamily, uiProviderFactory: UIProviderFactoryIF) {
        val showMainState by remember { mutableStateOf(viewModel.versionMain) }
        val showMain = showMainState.collectAsStateWithLifecycle().value
        val config = Config.getInstance()
        if (config.ready() && (showMain > 0)) {
            AppTheme(fontFamily) {
                // the main will always show up in the background
                Box(
                    // fill the screen with a single BoxWithConstraints to get the dimensions
                    modifier = Modifier.wrapContentSize().zIndex(1.0F)
                ) {
                    val config = Config.getInstance()
                    val layoutName =
                        if (isPortrait())
                            config.getItem(".ui.profiles.dilbo.portrait").valueCsv()
                        else
                            config.getItem(".ui.profiles.dilbo.landscape").valueCsv()
                    val uiItem = config.getItem(".ui.layouts.$layoutName")
                    layoutRoot = Sector(uiItem, isPortrait(), uiProviderFactory)
                    layoutRoot?.BuildSector(widthDp, heightDp)
                }
                // the modal
                val showModal = viewModel.versionModal.collectAsStateWithLifecycle()
                if (showModal.value > 0) {
                    Box(
                        contentAlignment = Alignment.Center,
                        modifier = Modifier.fillMaxSize().zIndex(2.0F)
                    ) {
                        Box(
                            contentAlignment = Alignment.Center,
                            modifier = Modifier
                                .wrapContentSize()
                                .background(Theme.colors.color_background_modal)
                                .shadow(elevation = Theme.dimensions.smallSpacing)
                        ) {
                            // repeat the check for the modal version here
                            // to ensure that the modal content is recomposed
                            // e.g. because of a form change within the modal
                            if (showModal.value > 0) {
                                modalContent()
                            }
                        }
                    }
                }

                // the dialogue
                val showDialog = viewModel.versionDialog.collectAsStateWithLifecycle()
                if (showDialog.value > 0) {
                    Box(
                        contentAlignment = Alignment.Center,
                        modifier = Modifier.fillMaxSize().zIndex(3.0F)
                    ) {
                        Box(
                            contentAlignment = Alignment.Center,
                            modifier = Modifier
                                .wrapContentSize()
                                .background(Theme.colors.color_background_form_input)
                                .shadow(elevation = Theme.dimensions.smallSpacing)
                        ) {
                            dialogContent()
                        }
                    }
                }
            }
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
