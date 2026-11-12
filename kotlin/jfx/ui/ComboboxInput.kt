package org.tfyh.jfx.ui

import javafx.collections.FXCollections
import javafx.scene.control.ComboBox
import javafx.util.StringConverter

class ComboboxInput(options: MutableMap<String, String>, formField: FormField): ComboBox<String>() {

    init {
        items = FXCollections.observableArrayList(options.keys)
        // The converter will not be used, resolving of the technical value will be performed during form validation.
        converter = object : StringConverter<String?>() {
            // Convert the selectable object, i.e. the options key (a user facing label), into a value String
            override fun toString(label: String?): String? {
                return label
            }
            // Convert the value String, i.e. the options value, back to the selectable object
            override fun fromString(value: String?): String? {
                return value
            }
        }
        value = formField.preset
        editor.prefWidth = formField.parentForm.getFieldWidth(formField.columnsSpan).toDouble()
        formField.initialiseTextInputControl(this.editor)
    }

}