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

import javafx.beans.property.SimpleObjectProperty
import javafx.scene.control.DatePicker
import javafx.scene.input.KeyCode
import javafx.scene.input.KeyEvent
import javafx.util.StringConverter
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.LocalTime
import java.time.format.DateTimeFormatter

// See https://stackoverflow.com/questions/28493097/is-there-any-date-and-time-picker-available-for-javafx
// Source - https://stackoverflow.com/a/33953850
// Posted by Edvin Syse
// Retrieved 2026-10-15, Licence - CC BY-SA 3.0
/**
 * In order to be able to use the standard DateTimePicker, the date-time-value is a localDateTime instead of the
 * NoZoneDateTime used in the tfyh-package.
 */
class DateTimePicker(val formatter : DateTimeFormatter, formField : FormField) : DatePicker() {

    private var dateTimeValue = SimpleObjectProperty(LocalDateTime.now())

    fun toLocalDateTime(noZoneDateTime : NoZoneDateTime): LocalDateTime {
        return LocalDateTime.of(
            noZoneDateTime.year(), noZoneDateTime.month(), noZoneDateTime.day(),
            noZoneDateTime.hour(), noZoneDateTime.minute(), noZoneDateTime.second())
    }

    fun toNoZoneDateTime(localDateTime: LocalDateTime): NoZoneDateTime {
        return NoZoneDateTime(localDateTime.format(formatter))
    }

    init {
        converter = object : StringConverter<LocalDate>() {

            override fun toString(value: LocalDate?): String {
                return if (dateTimeValue.get() != null)
                    dateTimeValue.get().format(formatter) else ""
            }

            override fun fromString(value: String?): LocalDate? {
                if (value == null) {
                    dateTimeValue.set(null)
                    return null
                }
                dateTimeValue.set(LocalDateTime.parse(value, formatter))
                return dateTimeValue.value.toLocalDate()
            }
        }

        // Synchronise changes to the underlying date value back to the dateTimeValue
        valueProperty().addListener { _, _, new ->
            if (new == null) {
                dateTimeValue.set(null)
            } else {
                if (dateTimeValue.get() == null) {
                    dateTimeValue.set(LocalDateTime.of(new, LocalTime.now()))
                } else {
                    val time = dateTimeValue.get().toLocalTime()
                    dateTimeValue.set(LocalDateTime.of(new, time))
                }
            }
            formField.entered = dateTimeValue.get()?.format(formatter) ?: ""
        }

        // Synchronise changes to dateTimeValue back to the underlying date value
        dateTimeValue.addListener { _, _, new ->
            valueProperty().set(new?.toLocalDate())
            formField.entered = dateTimeValue.get()?.format(formatter) ?: ""
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
        this.dateTimeValue.set(toLocalDateTime(dateTime))
    }
    fun get(): NoZoneDateTime {
        return this.toNoZoneDateTime(dateTimeValue.value)
    }
}

fun dateTimeInput(formField: FormField): DateTimePicker {
    val lang = Config.getInstance().language()
    val formatter: DateTimeFormatter = DateTimeFormatter.ofPattern(
        lang.dateTemplate.replace("Y", "yyyy")
            .replace("M", "mm").replace("d", "dd") + " HH:mm")
    return DateTimePicker(formatter, formField)

}
