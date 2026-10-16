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
import androidx.compose.foundation.layout.Row
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.window.Popup
import org.tfyh.kmp.design.Theme
import org.tfyh.data.Config
import org.tfyh.data.Formatter
import org.tfyh.data.NoZoneDateTime
import org.tfyh.data.Parser
import org.tfyh.data.ParserConstraints
import org.tfyh.data.ParserName
import org.tfyh.util.I18n
import org.tfyh.util.Language
import kotlin.time.ExperimentalTime

@OptIn(ExperimentalMaterial3Api::class, ExperimentalTime::class)
@Composable
fun DateTimeInput(formField: FormField) {

    val lang = Config.getInstance().language()
    val optionsIcon = Icons.Default.ArrowDropDown

    // prepare date picker
    var showDatePicker by remember { mutableStateOf(false) }
    val preset = Parser.parse(formField.preset, ParserName.DATETIME, lang) as NoZoneDateTime
    val initialSelectedDateMillis =
        if (ParserConstraints.isEmpty(preset, ParserName.DATETIME)) null
        else preset.millis()
    val datePickerState = rememberDatePickerState(initialSelectedDateMillis = initialSelectedDateMillis)
    var selectedDate by remember { mutableStateOf(preset.toDateString(lang)) }

    // prepare time picker
    var showTimePicker by remember { mutableStateOf(false) }
    val timePickerState = rememberTimePickerState(
        initialHour = preset.hour(),
        initialMinute = preset.minute()
    )
    var selectedTime by remember {
        mutableStateOf(Formatter.format(timePickerState.hour * 3600 + timePickerState.minute * 60,
            ParserName.TIME, Language.CSV).substring(0, 5))
    }

    // build input UI as date input + time input
    Row (
        modifier = Modifier.wrapContentSize()
    ) {

        // Date input
        Box(
            modifier = formField.boxModifier { showDatePicker = true }
        ) {
            TextField(
                colors = formField.viewModel.textFieldColors(),
                shape = TextFieldDefaults.shape,
                textStyle = Theme.fonts.p,
                singleLine = true,
                value = selectedDate,
                onValueChange = {
                    showDatePicker = false
                    selectedDate = it
                },
                label = { Text(
                    text = formField.label,
                    style = Theme.fonts.p,
                    color = formField.viewModel.labelColor()) },
                trailingIcon = {
                    if (! formField.viewModel.isReadOnly())
                        IconButton(
                            onClick = { showDatePicker = !showDatePicker }
                        ) {
                            Icon(
                                imageVector = optionsIcon,
                                contentDescription = "Select date"
                            )
                        }
                },
                modifier = Modifier
                    .width(formField.width * 0.55F)
                    .padding(top = Theme.dimensions.smallSpacing),
            )

            if (showDatePicker && !formField.viewModel.isReadOnly()) {
                Popup(
                    onDismissRequest = { showDatePicker = false },
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
                                selectedDate = Formatter.microTimeToString(
                                    (datePickerState.selectedDateMillis ?: 0L) / 1000.0, lang)
                                    .substring(0, 10)
                                formField.entered = "$selectedDate $selectedTime"
                                showDatePicker = false
                            }
                        }
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

        // Time input
        Box(
            modifier = formField.boxModifier { showTimePicker = true }
        ) {
            TextField(
                modifier = Modifier
                    .width(formField.width * 0.45F)
                    .padding(top = Theme.dimensions.smallSpacing),
                colors = formField.viewModel.textFieldColors(),
                shape = TextFieldDefaults.shape,
                textStyle = Theme.fonts.p,
                singleLine = true,
                value = selectedTime,
                onValueChange = {
                    showTimePicker = false
                    selectedTime = it
                },
                label = {
                    Text(text = I18n.getInstance().t("ZFaJj0|time"),
                    style = Theme.fonts.p,
                    color = formField.viewModel.labelColor()) },
                trailingIcon = {
                    if (!formField.viewModel.isReadOnly())
                        IconButton(
                            onClick = { showTimePicker = !showTimePicker },
                            modifier = Modifier.background(Color(0xFF000000))
                        ) {
                            Icon(
                                imageVector = optionsIcon,
                                contentDescription = "Select time",
                            )
                        }
                },
            )

            if (showTimePicker && !formField.viewModel.isReadOnly()) {
                Popup(
                    onDismissRequest = { showTimePicker = false },
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
                                selectedTime = Formatter.format(
                                    (timePickerState.hour * 3600 + timePickerState.minute * 60),
                                    ParserName.TIME, lang)
                                    .substring(0, 5)
                                formField.entered = "$selectedDate $selectedTime"
                                showTimePicker = false
                            }
                        }
                        TimePicker(
                            state = timePickerState,
                            modifier = Modifier
                                .padding(Theme.dimensions.smallSpacing)
                                .width(Theme.dimensions.pickerSize),
                            colors = Theme.colors.timePickerColors(),
                            layoutType = TimePickerLayoutType.Vertical
                        )
                    }
                }
            }
        }
    }
}
