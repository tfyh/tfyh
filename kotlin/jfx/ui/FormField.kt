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

import javafx.beans.property.SimpleStringProperty
import javafx.scene.control.TextInputControl
import javafx.scene.control.Tooltip
import javafx.scene.layout.Background
import javafx.scene.layout.Border
import javafx.scene.layout.Pane
import javafx.scene.layout.VBox
import org.dilbo.core.FormHandler
import org.tfyh.control.LoggerSeverity
import org.tfyh.data.*
import org.tfyh.jfx.design.AppColours
import org.tfyh.jfx.design.AppFonts
import org.tfyh.util.I18n
import org.tfyh.util.Language

data class FormField (
    val parentForm: Form, // the form to which this field belongs
    val parentItem: Item,  // the parent of the item which defines the data properties of this field.
    // That is either a table's record item or the framework form fields branch item

    val modifierChar: String, // the way the field shall be displayed and validated
    // * = required, . = hidden, ! = read-only, ~ = display value like bold label, § = display headline
    // > = validity period start, < = validity period end

    val nameDataField: String, // the name of the data field. For lists this may be different from
    // the input field name, e.g. crew#2 for the second crew member
    val labelSetter: String, // the label for the data field. default is the item's label
    val rowTag: String, // "R" for new rows with spacer, "r" for new rows, "" for a next field in the row
    val index: Int, // a consecutive index on all fields of this form.
    val listPosition : Int = 0, // the position of a list element field in the list, starts with 1.
    // For data items which are not a list, but a singular value, this is 0
    val onSubmit: () -> Unit = {}, // the function to trigger, if the from field is clicked
    val columnsSpan: Int = 1 // the number of columns this form field shall span in the form
) {
    val name: String // the name of the field. For list fields this is without the list index
    val item: Item // the item which holds the definition of the form fields data
    val label: String // the label to display
    val type: Type // the type of data of this field
    private var isVisibleField: Boolean = true // visibility changes require a form rebuild
    private var parentInput: Pane? = null

    val options: MutableMap<String, String> = mutableMapOf() // the set of options for select and autocomplete fields
    var preset: String = "" // the preset value as String

    var entered: SimpleStringProperty = SimpleStringProperty("") // the entered value as String
    var parsed: Any = "" // the parsed value as native
    var isValid: Boolean = true // the validation result
    internal var validated: Any = "" // the validated value as native, either a single value or an ArrayList for list types
    var versionValidity: MutableMap<String, Record.VersionValidity> = mutableMapOf() // always a map of uuid(_or_name) -> version validity
    var changed: Boolean = false // an indicator if the value was entered that differs from the preset
    var findings: String = "" // any findings during parsing and validation

    private val isProperty: Boolean // tru, if the field input represents a property value rather than a data value
    val property: Property // the property which is represented by the field, if so.

    private val inputType: FormFieldType
    private val listeners: MutableList<FormFieldListener> = mutableListOf()

    init {
        val propertyName = PropertyName.valueOfOrInvalid(nameDataField)
        isProperty = (propertyName !== PropertyName.INVALID)
        // the item to be modified is either a property, a child of the
        // config item of this form or a generic form field
        val recordItem = parentItem.getChild(nameDataField) ?: Config.getInstance().invalidItem
        val formFieldsItem = Config.getInstance().getItem(".framework.form_fields")

        item = if (isProperty) recordItem else {
            if (parentItem.hasChild(nameDataField))
                parentItem.getChild(nameDataField) ?: Config.getInstance().invalidItem
            else
                formFieldsItem.getChild(nameDataField) ?: Config.getInstance().invalidItem
        }
        name =
            if (((modifierChar == "~")) && ! isProperty && ! item.isValid())
                nameDataField + "_" + index // read only data can get always the same name. This makes then unique.
            else
                nameDataField

        label = labelSetter.ifEmpty { if (listPosition == 0) item.label() else item.label() + "#" + listPosition }

        readOptions()
        type = item.type()
        property = Property.descriptor[propertyName] ?: Property.invalid

        // set the field dimensions for the form.
        inputType =
            if (item.isValid()) FormFieldType.valueOfOrText(item.inputType())
            else FormFieldType.NONE
    }

    /**
     * Initialise styles, preset and input to form field property binding. For dateTime inputs two inputs
     * represent the form field. Set bindProperties to false and provide the binding before initialisation.
     */
    fun initialiseTextInputControl(input: TextInputControl, bindProperties: Boolean = true) {
        val disableEdit = (modifierChar == "!")
        val boldface = (modifierChar == "~")
        val hidden = (modifierChar == ".")
        input.apply {
            if (bindProperties) textProperty().bindBidirectional(entered)
            text = entered.value.ifEmpty { preset }
            font = if (boldface) AppFonts.b else AppFonts.p
            border = Border(if (boldface) AppColours.textNoBorderStroke() else AppColours.textBorderStroke())
            background = Background(AppColours.textBackgroundFill())
            tooltip = Tooltip(item.description().ifEmpty { label })
            if (disableEdit || boldface)
                disableProperty().set(true)
            if (hidden)
                visibleProperty().set(false)
        }
    }

    /**
     * Read the options into the input field as they are provided by the respective value_reference property.
     */
    fun readOptions() {

        val config = Config.getInstance()
        val valueReference = item.valueReference()
        if (valueReference.isEmpty())
            return

        options.clear()
        if (valueReference.startsWith("[")) {
            // a predefined configured list of ids or id=label pairs. The array is options[label] = id
            val parsedOptions = Parser.parse(valueReference, ParserName.STRING_LIST, Language.CSV)
            if (parsedOptions is List<*>) {
                for (option in parsedOptions) {
                    if (option is String) {
                        if (option.contains("="))
                            options[option.split("=")[0]] = option.split("=")[1]
                        else
                            options[option] = option
                    }
                }
            }
        } else if (valueReference.startsWith(".")) {
            // an item catalogue. The array is options[label] = id
            val headItem = config.getItem(valueReference)
            for (child in headItem.getChildren())
                options[child.label()] = child.name()
        } else {
            // a table. The array is options[name] = uuid
            val tableName = valueReference.split(".")[0]
            val indices = Indices.getInstance()
            indices.buildIndexOfNames(tableName)
            val names = indices.getNames(tableName)
            for (name in names.keys)
                options[name] = if (names[name]?.isEmpty() != false) "" else names[name]?.getOrNull(0) ?: ""
        }
    }

    /**
     * Returns true, if the fragment is contained in one of the options
     * or one of te options starts with it. Not case-sensitive.
      */
    fun isMatchingFragment(fragment: String,  contained: Boolean) : Boolean {
        val fragmentToLower = fragment.lowercase()
        for (option in options.keys)
            if ((contained && option.lowercase().contains(fragmentToLower))
                || option.lowercase().startsWith(fragmentToLower))
                return true
        return false
    }

    /**
     * Filter all options for an auto-selection or autocompletion field. Not case-sensitive. Is not implemented here,
     * but part of the AutocompletionTextField class
     */
    @Suppress("Unused")
    fun filterOptions(filtered: MutableList<String>, filter: String, contained: Boolean, maxCount: Int) {}

    /**
     * preset the value with a formatted and resolved value. Only dates are reformatted from
     * the local format to the browser expected YYYY-MM-DD.
     */
    internal fun presetWithString (formattedValue: String, asEntered: Boolean = false) {
        // reformat Date and DateTime to iso compatible
        val parser = type.parser()
        val isDateOrDateTime = (parser === ParserName.DATE) || (parser === ParserName.DATETIME)
        val isMissingNotice = (inputType == FormFieldType.AUTOSELECT) &&
                (formattedValue == Indices.getInstance().missingNotice)
        val language = if (isDateOrDateTime) Language.CSV else Config.getInstance().language()
        preset =
            if (isDateOrDateTime)
                Formatter.format(Parser.parse(formattedValue, parser, language), parser, Language.CSV)
            else if (isMissingNotice) ""
            else formattedValue
        if (asEntered) {
            entered.value = preset
        }
    }


    /* ---------------------------------------------------------------------- */
    /* --------------- EVALUATION OF FORM ENTRIES --------------------------- */
    /* ---------------------------------------------------------------------- */

    /**
     * Resolve the entered String or String List as name into an id as defined in the value
     * reference. Returns the id on success and the original value on failure. toResolve may be
     * either a String or a List of String according to the items type.
     */
    private fun resolve(toResolve: Any): Any {
        if (!item.isValid())
            return entered.value.ifEmpty { preset }
        if (toResolve is ArrayList<*>) {
            val resolved = mutableListOf<Any>()
            for (item in toResolve)
                resolved.add(options[item] ?: (item ?: ""))
            return resolved
        }
        // for uuid_or_name type fields a reference may validly not be resolvable
        return options[toResolve] ?: toResolve
    }

    /**
     * Validate entered against preset, constraints and rules.
     */
    internal fun validate(): Boolean {
        changed = false
        if (isProperty || item.isValid()) {
            // only parse data for which a field exists
            findings = ""
            changed = (preset != entered.value)
                    && (modifierChar != "!") && (modifierChar != "~")
            // do not validate data, if mandatory and empty.
            if ((modifierChar == "*") && entered.value.isEmpty()) {
                findings += I18n.getInstance().t("anPDZr|Please enter a value in ...", label) + ","
                entered.value = "?"
                isValid = false
            }
            else {
                val toValidate = entered.value.ifEmpty { preset }
                // parse (syntactical check)
                Findings.clearFindings()
                parsed = Parser.parse(toValidate, type.parser(), Config.getInstance().language())
                // validate: limits and reference resolving
                validated = if (item.valueReference().isNotEmpty())
                                resolve(parsed)
                            else
                                Validator.adjustToLimits(parsed, type, item.valueMin(),
                                item.valueMax(), item.valueSize())
                if ((type == Type["uuid"]) || (type == Type["uuid_or_name"])) {
                    versionValidity.clear()
                    versionValidity[validated.toString()] = periodCheck(validated.toString())
                } else if ((type == Type["uuid_list"]) || (type == Type["uuid_or_name_list"])) {
                    versionValidity.clear()
                    for (validatedSingle in validated as ArrayList<*>) {
                        versionValidity[validatedSingle.toString()] = periodCheck(validatedSingle.toString())
                    }
                }

                // validate: rule check
                Validator.checkAgainstRule(validated, item.validationRules())
                findings = Findings.getFindings(false)
                if (findings.isNotEmpty()) {
                    entered.value = ""
                    isValid = false
                }
                else
                    isValid = true
            }
        }
        if (changed && Config.getInstance().debug)
            FormHandler.getInstance().logger.log(
                LoggerSeverity.DEBUG, "formField.validate()", this.name
                    + " changed to " + (if (type == Type["password"]) "********" else this.entered) +
                        ", i.e. " + (if (type == Type["password"]) "********" else this.validated.toString()) +
                        " with findings: '$findings'.")

        return changed
    }

    /**
     * Checks the period of the youngest record for the object with the given uuid.
     * @return Int = 0 if the object's table is not versioned; 1 if the object is valid; 2 if the object is now invalid; 3 if the uuid is not matched in any table.
     */
    fun periodCheck(uuid: String): Record.VersionValidity {
        val indices = Indices.getInstance()
        val tableName = indices.getTableForUuid(uuid)
        if (tableName == Indices.getInstance().missingNotice)
            return Record.VersionValidity.NOT_FOUND
        val records = DataBase.getInstance().selectByMatch(tableName, mutableMapOf("uuid" to uuid))
        if (records.isEmpty())
            return Record.VersionValidity.NOT_FOUND

        var maxInvalidFrom = ParserConstraints.min(ParserName.DATE) as NoZoneDateTime
        for (record in records) {
            if (record["invalid_from"] == null)
                return Record.VersionValidity.NOT_VERSIONED
            if (record["invalid_from"] == "1753-01-01")
                return Record.VersionValidity.VALID
            val thisInvalidFrom = Parser.parse(record["invalid_from"] ?: "",
                                                ParserName.DATE, Language.CSV) as NoZoneDateTime
            if (thisInvalidFrom > maxInvalidFrom)
                maxInvalidFrom = thisInvalidFrom
        }
        return if (NoZoneDateTime.now() <= maxInvalidFrom) Record.VersionValidity.VALID
            else Record.VersionValidity.OUTDATED
    }

    // before submitting a form any change can be used programmatically by implementing listeners
    fun addListener(listener: FormFieldListener) { listeners.add(listener) }
    fun onFocusChanged(isFocused: Boolean) { if (isFocused) parentForm.moveFocus(this) }
    fun notifyOnFocusLost() {
        val entered = this.entered.value.ifEmpty { this.preset }
        for (listener in listeners)
            listener.onFocusLost(entered)
    }

    fun setVisibility(visible: Boolean) {
        if (this.isVisibleField == visible) return
        this.isVisibleField = visible
        parentInput?.isVisible = visible
    }

    fun width() = parentForm.getFieldWidth(columnsSpan).toDouble()
    fun build(): Pane? {
        val formFieldWidth = width()
        parentInput = when (inputType) {
            FormFieldType.TEXT ->
                when (type.parser()) {
                    ParserName.DATE -> dateInput(this)
                    ParserName.DATETIME -> dateTimeInput(this)
                    else -> textAnyInput(this)
                }
            FormFieldType.DATE -> dateInput(this)
            FormFieldType.DATETIME -> dateTimeInput(this)
            FormFieldType.TEXTAREA -> textAreaInput(this)
            FormFieldType.SELECT -> textAutoCompleteInput(this, false)
            FormFieldType.AUTOSELECT -> textAutoCompleteInput(this)
            FormFieldType.SUBMIT ->  submitButton(label, onSubmit)
            FormFieldType.NONE -> Pane()
            FormFieldType.CHECKBOX -> VBox(appLabel("TODO: checkbox"))
            FormFieldType.TIME -> VBox(appLabel("TODO: time"))
            FormFieldType.RADIO -> VBox(appLabel("TODO: radio button"))
        }.apply {
            prefWidth = formFieldWidth
            minWidth = formFieldWidth
            visibleProperty().set(isVisibleField)
        }
        return parentInput
    }

    override fun toString(): String {
        return "$name:$label($columnsSpan)"
    }

}