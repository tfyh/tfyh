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

import javafx.scene.input.KeyCode
import javafx.scene.layout.HBox
import javafx.scene.layout.Pane
import javafx.scene.layout.Region
import javafx.scene.layout.VBox
import javafx.scene.shape.Box
import org.tfyh.api.ApiHandler
import org.tfyh.api.Transaction
import org.tfyh.data.*
import org.tfyh.jfx.design.AppColours
import org.tfyh.jfx.design.AppDimensions
import org.tfyh.jfx.design.AppFonts
import org.tfyh.util.Language

class Form(
    private val _recordItem: Item,
    private val formDefinition: String = "",
    val dimensions: ViewBuilder.Dimensions,
    private val onSubmit: () -> Unit,
) : UIProviderIF, Pane() {

    internal var formErrors: String = "" // validation errors as String
    internal var formHeadline: String = "" // headline for the form, leave empty to use the record item label
    private var formRows: MutableList<HBox> = mutableListOf() // rows of the form which contain fields
    var inputFields: MutableMap<String, FormField> = mutableMapOf()   // used by the FormHandler class
    internal var previousErrors: String = "" // for display of entry errors on top of the form
    private var fsId = Ids.generateUid(4)
    private var formColumnsCount: Int = 0
    val widthPx = dimensions.widthPx
    private var formFieldFocused: FormField? = null

    /* ---------------------------------------------------------------------- */
    /* --------------------- INITIALIZATION --------------------------------- */
    /* ---------------------------------------------------------------------- */
    /**
     * Initialise the form. Separate function to keep aligned with the
     * JavaScript twin code, in which external initialisation of a form is used.
     */
    fun init() {
        fsId = Ids.generateUid(4)
        // hierarchy of form definitions: 1. explicitly provided, 2.
        // part of the record's properties, 3. auto-generated from the record's properties.
        val recordToEdit = Record(this._recordItem)
        var formDefinitionToUse =
            this.formDefinition.ifEmpty {
                this._recordItem.recordEditForm().ifEmpty {
                    recordToEdit.defaultEditForm()
                }
            }

        if (!formDefinitionToUse.startsWith("rowTag;names;labels"))
            formDefinitionToUse = "rowTag;names;labels\n$formDefinitionToUse"
        val definitionRows = Codec.csvToMap(formDefinitionToUse)
        this.inputFields = mutableMapOf()
        val config = Config.getInstance()

        // identify form columns count
        formColumnsCount = 0
        for (definitionRow in definitionRows) {
            val namesStr = definitionRow["names"]
            if (namesStr != null) {
                val namesPlus = Parser.parse(namesStr, ParserName.STRING_LIST, Language.CSV) as List<*>
                if (namesPlus.size > formColumnsCount) formColumnsCount = namesPlus.size
            }
        }

        // '*' = required, '.' = hidden, '!' = read-only, '~' = display value like bold label
        val modifierChars = listOf("*", ".", "!", "~", ">", "<")
        var i = 0 // index of form fields including th virtual fields which fill empty spaces in the grid
        val formFieldsBranchItem = config.getItem(".framework.form_fields")
        // iterate through form rows
        for (definitionRow in definitionRows) {
            val rowTag = definitionRow["rowTag"]
            val namesStr = definitionRow["names"]
            val labelsStr = definitionRow["labels"]
            if ((namesStr != null) && (labelsStr != null)) {
                val namesPlus = Parser.parse(namesStr, ParserName.STRING_LIST, Language.CSV)
                // namesPlus are the names including their modifier characters for this row
                val labels = Parser.parse(labelsStr, ParserName.STRING_LIST, Language.CSV)
                // labels overwrite the default label of the form field if not empty. They will not be checked in i18n parsing.
                var c = 0 // the column in the grid
                if ((namesPlus is List<*>) && (labels is List<*>)) {
                    for (namePlus in namesPlus) {
                        var inputFieldName = namePlus as String
                        val useRowTag = if (c == 0) (rowTag ?: "") else ""
                        val label = (labels.getOrNull(c) as String?) ?: ""
                        val modifierChar =
                            if (inputFieldName.isNotEmpty() && (modifierChars.indexOf(inputFieldName.substring(0, 1)) >= 0))
                                inputFieldName.substring(0, 1)
                            else ""
                        // the last form field in a row will always fill the remainder within that row.
                        val formColumnsSpan =
                            if (c == (namesPlus.size - 1)) formColumnsCount - c
                            else 1
                        inputFieldName = inputFieldName.substring(modifierChar.length)
                        var listPosition = 0
                        var dataFieldName = inputFieldName
                        if (inputFieldName.contains("#")) {
                            dataFieldName = inputFieldName.substring(0, inputFieldName.indexOf("#"))
                            try  {
                                listPosition = inputFieldName.substring(inputFieldName.indexOf("#") + 1).toInt()
                            } catch (_: Exception) {}
                        }
                        if (inputFieldName.isNotEmpty())
                            this.inputFields[inputFieldName] =
                                if (_recordItem.hasChild(dataFieldName))
                                    FormField(this,_recordItem, modifierChar,dataFieldName,
                                        label, useRowTag, i, listPosition, onSubmit, formColumnsSpan)
                                else
                                    FormField(this,formFieldsBranchItem, modifierChar, inputFieldName,
                                        label, useRowTag, i, 0, onSubmit, formColumnsSpan)
                        else {
                            val virtualName = "_$i"
                            this.inputFields[virtualName] =
                                FormField(this, config.invalidItem, modifierChar, virtualName,
                                    virtualName, useRowTag, i, 0,{ }, formColumnsSpan)
                        }
                        if ((listPosition > 0) && (_recordItem.hasChild(dataFieldName))
                                && (this.inputFields[dataFieldName] == null))
                            this.inputFields[dataFieldName] = FormField(
                                this, _recordItem, "", dataFieldName,
                                label, "", i, -1, onSubmit, formColumnsSpan
                            )
                        c++
                        i++
                    }
                }
            }
        }
    }

    /**
     * Get the width of a form field depending on its span.
     */
    fun getFieldWidth(columnsSpan: Int): Int {
        val unitWidth = (widthPx - 2 * AppDimensions.regularSpacing) / formColumnsCount - AppDimensions.regularSpacing
        val spanWidth = unitWidth * columnsSpan
        val additionalSpacing = AppDimensions.regularSpacing * (columnsSpan - 1)
        return (spanWidth + additionalSpacing).toInt()
    }

    /**
     * Convenience shortcut to check whether the form was initialised with a valid configuration
     */
    fun isValid() = this._recordItem.isValid()

    /**
     * Hide an input field
     */
    fun setVisible(fieldName: String, visible: Boolean) {
        inputFields[fieldName]?.setVisibility(visible)
    }
    /**
     * the form input field gathered focus. Notify the previous one about its focus losing.
     */
    fun moveFocus(toFormField: FormField) {
        this.formFieldFocused?.notifyOnFocusLost()
        this.formFieldFocused = toFormField
    }
    /**
     * Preset all values of the form with those of the provided row. Strings are shown in the form as is,
     * they must be formatted and ids resolved. Only dates are reformatted from the local format to the
     * browser expected YYYY-MM-DD.
     * @param asEntered Set to true to also change the entered value of the form field. The effect is, that
     * no change is detected for the value if the value is kept.
     */
    fun presetWithStrings(row: Map<String, String>, asEntered: Boolean = false) {
        for (fieldName in this.inputFields.keys) {
            val f = this.inputFields[fieldName]
            val fieldValue = row[fieldName]
            if ((f != null) && (fieldValue != null))
                f.presetWithString(fieldValue, asEntered)
        }
    }

    /**
     * compose the form based on the field set.
     */
    override fun build (): Region {
        // set headline and error message
        val headline = HBox(0.0,
            appLabel(formHeadline.ifEmpty { _recordItem.label() }, AppFonts.h3))
        formRows.clear()
        formRows.add(headline)
        if (previousErrors.isNotEmpty())
        formRows.add(HBox(appLabel(previousErrors,
            textColor = AppColours.all_form_input_invalid)))
        // build form
        val inputNames = inputFields.keys.toList()
        var index = 0
        // responsive layout. only one column of fields for small portrait screens
        val cCnt = formColumnsCount
        var fieldsInRow = mutableListOf<Region?>()
        var c = 0
        while (index < inputNames.size) {
            while ((index < inputNames.size) && (c < cCnt)) {
                val ff = inputFields[inputNames[index]]
                if ((ff?.listPosition ?: -1) >= 0) {
                    // add a spacer for a detached row
                    if (ff?.rowTag == "R")
                        formRows.add(HBox().apply {
                            prefHeight = AppDimensions.regularSpacing
                            // border = AppColours.borderSpacer()
                        })
                    val ffUI = ff?.build().apply {
                        prefWidth = getFieldWidth(ff?.columnsSpan ?: 1).toDouble()
                    }
                    fieldsInRow.add(ffUI ?: VBox())
                    c++
                }
                index++
                // close row, if either columns were filled up or the next form field opens a new row
                if ((index >= inputNames.size) ||
                    (inputFields[inputNames[index]]?.rowTag != "") || (c == cCnt)) {
                    if (fieldsInRow.isNotEmpty()) {
                        val row = HBox(AppDimensions.regularSpacing, *fieldsInRow.toTypedArray())
                        formRows.add(row)
                        fieldsInRow = mutableListOf()
                        c = 0
                    }
                }
            }
        }
        return VBox(AppDimensions.regularSpacing, *formRows.toTypedArray()).apply {
            onKeyPressed = {
                key -> if (key.code == KeyCode.ENTER)
                    onSubmit()
            }
            prefWidth = widthPx.toDouble()
        }
    }

    /**
     * read all values entered.
     */
    fun validate (): Boolean {
        this.formErrors = ""
        var anyChange = false
        for (fieldName in inputFields.keys) {
            val f = inputFields[fieldName]
            if (f != null) {
                anyChange = f.validate() || anyChange
                formErrors += f.findings
                // the change is propagated to the listField. List element fields shall never
                // record a change at all.
                if (f.listPosition > 0) f.changed = false
            }
        }
        return anyChange
    }

    /**
     * Submit the form to the API. This will insert the record. To update the record, set update
     * true. In that case set uid to ensure that the correct record is updated. For update == false
     * a new uid is generated.
     */
    fun submit(update: Boolean, uid: String = ""): Transaction {
        val record: MutableMap<String, String> = mutableMapOf()
        for (inputFieldName in inputFields.keys) {
            val inputField = inputFields[inputFieldName]
            if (inputField != null &&
                (inputField.listPosition <= 0) &&
                // -1 = list field which was split into single fields, 0 = no list field, >0 = list element field
                (inputField.changed || inputField.preset.isNotEmpty()))
                record[inputFieldName] = Formatter.format(inputField.validated, inputField.type.parser(),
                    Language.CSV)
        }
        if (! update)
            record["uid"] = Ids.generateUid(6)
        else
            record["uid"] = uid
        val apiHandler = ApiHandler.getInstance()
        return apiHandler.addNewTxToPending(
            txType = if (update) Transaction.TxType.UPDATE else Transaction.TxType.INSERT,
            tableName = _recordItem.name(),
            record = record
        )
    }

}