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

import javafx.beans.property.SimpleObjectProperty
import javafx.scene.control.DatePicker
import javafx.scene.input.KeyCode
import javafx.scene.input.KeyEvent
import javafx.util.StringConverter
import org.tfyh.data.Config
import org.tfyh.data.NoZoneDateTime
import java.time.LocalDate
import java.time.format.DateTimeFormatter

// See https://stackoverflow.com/questions/28493097/is-there-any-date-and-time-picker-available-for-javafx
// Source - https://stackoverflow.com/a/33953850
// Posted by Edvin Syse
// Retrieved 2026-10-15, Licence - CC BY-SA 3.0
/**
 * In order to be able to use the standard DateTimePicker, the date-time-value is a localDateTime instead of the
 * NoZoneDateTime used in the tfyh-package.
 */
class DatePickerNZDT(val formatter : DateTimeFormatter, formField : FormField) : DatePicker() {

    private var dateValue = SimpleObjectProperty(LocalDate.now())

    fun toLocalDate(noZoneDateTime : NoZoneDateTime): LocalDate {
        return LocalDate.of(
            noZoneDateTime.year(), noZoneDateTime.month(), noZoneDateTime.day())
    }

    fun toNoZoneDateTime(localDate: LocalDate): NoZoneDateTime {
        return NoZoneDateTime(localDate.format(formatter))
    }

    init {
        converter = object : StringConverter<LocalDate>() {

            override fun toString(value: LocalDate?): String {
                return if (dateValue.get() != null)
                    dateValue.get().format(formatter) else ""
            }

            override fun fromString(value: String?): LocalDate? {
                if (value == null) {
                    dateValue.set(null)
                    return null
                }
                dateValue.set(LocalDate.parse(value, formatter))
                return dateValue.value
            }
        }

        // Synchronise changes to the underlying date value back to the dateTimeValue
        valueProperty().addListener { _, _, new ->
            dateValue.set(new)
            formField.entered = new?.format(formatter) ?: ""
        }

        // Synchronise changes to dateTimeValue back to the underlying date value
        dateValue.addListener { _, _, new ->
            valueProperty().set(new)
            formField.entered = new?.format(formatter) ?: ""
        }

        // Persist changes onblur
        editor.focusedProperty().addListener { _, _, new ->
            if (!new)
                simulateEnterPressed()
        }

    }

    private fun simulateEnterPressed() =
        editor.fireEvent(KeyEvent(editor, editor, KeyEvent.KEY_PRESSED, null, null,
            KeyCode.ENTER, false, false, false, false))

    fun set(dateTime: NoZoneDateTime) {
        this.dateValue.set(toLocalDate(dateTime))
    }
    fun get(): NoZoneDateTime {
        return this.toNoZoneDateTime(dateValue.value)
    }
}

fun dateInput(formField: FormField): DatePickerNZDT {
    val lang = Config.getInstance().language()
    val formatter: DateTimeFormatter = DateTimeFormatter.ofPattern(
        lang.dateTemplate.replace("Y", "yyyy")
            .replace("M", "mm").replace("d", "dd"))
    return DatePickerNZDT(formatter, formField)
}
