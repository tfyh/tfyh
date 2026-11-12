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

import org.tfyh.data.Config
import org.tfyh.data.NoZoneDateTime

import javafx.scene.control.DatePicker
import javafx.scene.control.TextField
import javafx.scene.layout.HBox
import javafx.scene.layout.Pane
import javafx.scene.layout.Region
import javafx.scene.layout.VBox
import org.tfyh.data.Formatter
import org.tfyh.data.ParserName
import org.tfyh.jfx.design.AppDimensions

/**
 * In order to be able to use the standard DateTimePicker, the date-time-value is a localDateTime instead of the
 * NoZoneDateTime used in the tfyh-package.
 */
fun dateTimeInput(formField: FormField): Pane {

    // The form may be re-built. Reuse the entered value then as preset. DateTime inputs habe no property binding between entered and text properties,
    // because two input texts represent the single entered value
    if (formField.entered.value.isNotEmpty())
        formField.preset = formField.entered.value
    if (formField.preset.isEmpty())
        formField.presetWithString(NoZoneDateTime.nowAsString())
    val presetLocalDate = Formatter.format(NoZoneDateTime(formField.preset), ParserName.DATE, Config.getInstance().language())
    val presetLocalTime = formField.preset.substring(11,16)
    val fieldWidthPx = formField.parentForm.getFieldWidth(formField.columnsSpan)
    val dateWidthPx = fieldWidthPx.toDouble() / 2
    val timeWidthPx = dateWidthPx - AppDimensions.regularSpacing

    val isImmutable = ((formField.modifierChar == "!") || formField.modifierChar == "~")
    val dateInput = DatePicker()
    val dateInputTextField = if (isImmutable) TextField() else dateInput.editor
    val timeInput = TextField()

    formField.initialiseTextInputControl(dateInputTextField, false)
    dateInputTextField.text = presetLocalDate
    dateInput.onAction = {
        formField.entered.value = dateInput.value.toString() + " " + timeInput.text
    }
    dateInput.prefWidth = dateWidthPx
    formField.initialiseTextInputControl(timeInput, false)
    timeInput.text = presetLocalTime
    timeInput.focusedProperty().addListener { _, _, focused ->
        if (!focused)
            formField.entered.value = dateInput.value.toString() + " " + timeInput.text
    }
    timeInput.prefWidth = timeWidthPx

    val dateTimeInputControl = HBox(AppDimensions.regularSpacing,
        if (isImmutable) dateInputTextField else dateInput, timeInput)
    return VBox(appLabel(formField.label), dateTimeInputControl)
}
