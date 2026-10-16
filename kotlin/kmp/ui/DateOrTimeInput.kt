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
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.wrapContentSize
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowDropDown
import androidx.compose.material3.DatePicker
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.TextField
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TimePicker
import androidx.compose.material3.TimePickerLayoutType
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.material3.rememberTimePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.window.Popup
import kotlin.time.ExperimentalTime
import org.tfyh.kmp.design.Theme
import org.tfyh.data.Config
import org.tfyh.data.Formatter
import org.tfyh.data.NoZoneDateTime
import org.tfyh.data.Parser
import org.tfyh.data.ParserConstraints
import org.tfyh.data.ParserName

@OptIn(ExperimentalMaterial3Api::class, ExperimentalTime::class)
@Composable
fun DateOrTimeInput(formField: FormField, pickTime: Boolean) {
    val lang = Config.getInstance().language()
    val optionsIcon = Icons.Default.ArrowDropDown
    val parser = if (pickTime) ParserName.TIME else ParserName.DATE
    val preset = Parser.parse(formField.preset, parser, lang) as NoZoneDateTime
    var showPicker by remember { mutableStateOf(false) }
    val datePickerState = rememberDatePickerState(
        initialSelectedDateMillis = preset.millis())
    val dateTimeNow = NoZoneDateTime.now()
    val timePickerState = rememberTimePickerState(
        initialHour = dateTimeNow.hour(),
        initialMinute = dateTimeNow.minute()
    )
    val initiallyDisplayed = if (ParserConstraints.isEmpty(preset, formField.type.parser())) ""
                    else Formatter.format(preset, parser, lang)
    var selectedPick by remember { mutableStateOf(initiallyDisplayed) }

    Box(
        modifier = formField.boxModifier { showPicker = true }
    ) {
        TextField(
            modifier = Modifier
                .width(formField.width)
                .padding(top = Theme.dimensions.smallSpacing)
                .onFocusChanged { formField.onFocusChanged(it.isFocused) },
            colors = formField.viewModel.textFieldColors(),
            shape = TextFieldDefaults.shape,
            textStyle = Theme.fonts.p,
            singleLine = true,
            value = selectedPick,
            onValueChange = {
                showPicker = false
                selectedPick = it
                formField.viewModel.setEntered(it)
            },
            label = { Text(text = formField.label, style = Theme.fonts.p, color = formField.viewModel.labelColor()) },
            trailingIcon = {
                if (! formField.viewModel.isReadOnly())
                    IconButton(onClick = { showPicker = !showPicker }) {
                        Icon(
                            imageVector = optionsIcon,
                            contentDescription = "Select date"
                        )
                    }
            },
        )

        if (showPicker && !formField.viewModel.isReadOnly()) {
            Popup(
                onDismissRequest = { showPicker = false },
                alignment = Alignment.TopStart
            ) {
                Column(
                    modifier = Modifier
                        .wrapContentSize()
                        .offset(y = Theme.dimensions.textFieldHeight)
                        .shadow(elevation = Theme.dimensions.smallSpacing)
                        .background(Theme.colors.color_background_form_input)
                        .padding(Theme.dimensions.smallSpacing)
                ) {
                    Box (
                        modifier = Modifier.width(Theme.dimensions.pickerSize),
                        contentAlignment = Alignment.TopEnd
                    )  {
                        SubmitButton("Ok") {
                            selectedPick = if (pickTime)
                                Formatter.format(
                                    (timePickerState.hour * 3600 + timePickerState.minute * 60),
                                    ParserName.TIME, lang)
                                    .substring(0, 5)
                            else
                                Formatter.microTimeToString(
                                    (datePickerState.selectedDateMillis ?: 0L) / 1000.0, lang)
                                    .substring(0, 10)
                            formField.entered = selectedPick
                            showPicker = false
                        }
                        if (pickTime) {
                            TimePicker(
                                state = timePickerState,
                                modifier = Modifier
                                    .padding(Theme.dimensions.smallSpacing)
                                    .width(Theme.dimensions.pickerSize),
                                colors = Theme.colors.timePickerColors(),
                                layoutType = TimePickerLayoutType.Vertical
                            )
                        } else {
                            DatePicker(
                                state = datePickerState,
                                modifier = Modifier
                                    .padding(Theme.dimensions.smallSpacing)
                                    .width(Theme.dimensions.pickerSize),
                                colors = Theme.colors.datePickerColors(),
                                title = { AppLabel(formField.label) },
                                showModeToggle = false
                            )
                        }
                    }
                }
            }
        }
    }
}
