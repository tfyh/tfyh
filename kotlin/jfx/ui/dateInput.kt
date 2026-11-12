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

import javafx.scene.control.DatePicker
import javafx.scene.control.TextField
import javafx.scene.layout.Pane
import javafx.scene.layout.Region
import javafx.scene.layout.VBox
import org.tfyh.data.Config
import org.tfyh.data.Formatter
import org.tfyh.data.NoZoneDateTime
import org.tfyh.data.ParserName

/**
 * In order to be able to use the standard DateTimePicker, the date-time-value is a localDateTime instead of the
 * NoZoneDateTime used in the tfyh-package.
 */
fun dateInput(formField: FormField): Pane {

    if (formField.preset == "")
        formField.presetWithString(NoZoneDateTime.nowAsString().substring(0,10))
    val presetLocalDate = Formatter.format(NoZoneDateTime(formField.preset), ParserName.DATE, Config.getInstance().language())

    val isImmutable = ((formField.modifierChar == "!") || formField.modifierChar == "~")
    val dateInput = DatePicker()
    val dateInputTextField = if (isImmutable) TextField() else dateInput.editor

    formField.initialiseTextInputControl(dateInputTextField)
    dateInputTextField.text = presetLocalDate
    dateInput.onAction = {
        formField.entered.value = dateInput.value.toString()
    }

    return VBox(appLabel(formField.label), dateInput)
}
