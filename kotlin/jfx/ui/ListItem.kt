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
import org.tfyh.control.EventHandlerIF
import org.tfyh.data.Item

data class ListItem (
    val uid: String,
    val icon: DrawableResource,
    val text: String,
    val providedByItem: Item,
    val textAlign: TextAlign = TextAlign.Start,
    val evenRow: Boolean = false,
    var visible: Boolean = true
) {

    companion object {

        class ListItemViewModel(listItem: ListItem) : ViewModel() {
            // the visibility is static and depends on the screen orientation. Change in
            // screen coordinates will trigger a recomposition rather than a change of state.
            private val _visible : MutableStateFlow<Boolean> = MutableStateFlow(listItem.visible)
            val visible : StateFlow<Boolean> = _visible
        }
    }

    private val viewModel = ListItemViewModel(this)
    private val eventHandler = EventHandler.getInstance()

    fun Compose() {
        val visible : Boolean by viewModel.visible.collectAsStateWithLifecycle()
        if (visible) {
            val clickEvent = EventHandlerIF.Event(EventHandlerIF.EventType.UI_EVENT,"doubleClick", uid, providedByItem)
            var rowModifier = Modifier
                .fillMaxWidth()
                .padding(Theme.dimensions.smallSpacing)
                .combinedClickable(
                    onClick = { clickEvent.name = "click"; eventHandler.handleEvent(clickEvent) },
                    // do the same for a double click (desktop) and a long click (smartphone)
                    onLongClick = { eventHandler.handleEvent(clickEvent) },
                    onDoubleClick = { eventHandler.handleEvent(clickEvent) },
                )
            if (evenRow)
                rowModifier = rowModifier.background(Theme.colors.color_background_table_even_rows)
            Row(
                modifier = rowModifier
            ) {
                Image(
                    painterResource(icon),
                    contentDescription = "icon",
                    modifier = Modifier.height(24.dp)
                )
                Spacer(
                    modifier = Modifier.weight(0.02F)
                )
                AppLabel(
                    text = text,
                    textAlign = textAlign,
                    modifier = Modifier.weight(0.91F)
                )
            }
        }
    }
}



