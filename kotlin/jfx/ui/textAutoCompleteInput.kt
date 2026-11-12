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

import javafx.collections.FXCollections
import javafx.scene.control.ComboBox
import javafx.scene.control.Tooltip
import javafx.scene.layout.Background
import javafx.scene.layout.Border
import javafx.scene.layout.Pane
import javafx.scene.layout.VBox
import javafx.util.StringConverter
import org.tfyh.data.Config
import org.tfyh.jfx.design.AppColours
import org.tfyh.jfx.design.AppFonts

fun textAutoCompleteInput(formField: FormField, allowFreeText: Boolean = true): Pane {
    val input =
        if (allowFreeText)
            AutocompletionTextField(formField.options.keys, formField).apply {
                if (formField.entered.value.isNotEmpty()) validateVersion()
            }
        else
            ComboBox<String>().apply {
            items = FXCollections.observableArrayList(formField.options.keys)
            // The converter will not be used, resolving of the technical value will be performed during form validation.
            value = formField.preset
            prefWidth = formField.parentForm.getFieldWidth(formField.columnsSpan).toDouble()
            border = Border(AppColours.textBorderStroke())
            background = Background(AppColours.textBackgroundFill())
            tooltip = Tooltip(formField.item.description().ifEmpty { formField.label })
            converter = object : StringConverter<String?>() {
                override fun toString(`object`: String?): String? { return `object` }
                override fun fromString(string: String?): String? { return string }
            }
            style = AppFonts.pStyle
        }
    if (formField.options.isEmpty())
        formField.readOptions()

    input.focusedProperty().addListener { _, _, focused ->
        if (!focused) {
            formField.notifyOnFocusLost()
        }
    }

    return VBox(appLabel(formField.label), input).apply {
        if (Config.getInstance().debug)
            background = AppColours.backgroundTest()
    }
}


