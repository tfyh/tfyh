/**
 * tools-for-your-hobby
 * https://www.tfyh.org
 * Copyright  2023-2025  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except
 * in compliance with the License. You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software distributed under the License
 * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 * or implied. See the License for the specific language governing permissions and limitations under
 * the License.
 */
/**
 * Handle the display of forms for configuration editing.
 */

class FormHandler {

	#recordItem
	#modificationsCsv
	#mode

	#listElementFrame =
		`<div class='w3-row listElementFrame' id='listElementBox-{index}'>
			<div class='listElementAction removeElement' id='listElementClose-{index}'> &#x1F5D1; </div>
			<div>{listElement}</div>
		</div>`;
	
	#listElementAdd = 
		`<div class='w3-row listElementFrame' id='listElementBox-add'>
			<div class='listElementAdd addElement' id='listElement-add'> + </div>
		</div>`;

	#validityFrame =
		`<div class='w3-row'>
			<div class='w3-col l2'><p>{validityNotice}</p></div>
			<div class='w3-col l2'><p><br><input type='date' name ='validity_date' value='{validityDate}' id='validity_date' class='formInput'></p></div>
		</div>`;

	onSubmit
	onButtonClick

	constructor(item) {
		this.#recordItem = item
	}

	/* ---------------------------------------------------------------- */
	/* ---------- CONFIGURATION ITEM EDITING -------------------------- */
	/* ---------------------------------------------------------------- */

	// some callbacks are just to be ignored
	ignore() {}

	/**
	 * Create a form definition based on the items' properties and children.
	 */
	#defaultItemForm(item, nameModifierChar) {
		let defaultForm = "rowTag;names;labels\n"
			+ "R;" + nameModifierChar + "_name,~_path;" + Codec.encodeCsvEntry(Formatter.format(
				[ i18n.t("eiIavr|Item Name"), i18n.t("VnA6V0|Path in Configuration") ], ParserName.STRING_LIST, Language.CSV)) + "\n"
			+ "r;actual_label;" + Codec.encodeCsvEntry(i18n.t("P3HxMO|Label")) + "\n"
			+ (((item.type().parser() !== ParserName.NONE) && (item.type().name() !== "template")) ?
				"r;actual_value;" + Codec.encodeCsvEntry(i18n.t("rqHqM3|Value")) + "\n" : "")
			+ "r;actual_description;" + Codec.encodeCsvEntry(i18n.t("A0ac1c|Description")) + "\n"
		let childrenValues = ""
		for (let child of item.getChildren())
			if (child.type().parser() !== ParserName.NONE)
				childrenValues += "r;" + child.name() + ";" + Codec.encodeCsvEntry(child.label()) + "\n"
		if (childrenValues.length > 0)
			defaultForm += "R;~;" + i18n.t("zbdLZ5|Set specific object valu...") + "\n" + childrenValues
		defaultForm += "R;submit;Submit"
		return defaultForm
	}

	editItem_do (item) {
		// now that the default template is prepared, initialise the form
		this.#recordItem = item
		form.init(item, (item.recordEditForm().length === 0) ?
			this.#defaultItemForm(item, "~") : "");
		// preset descriptor fields
		form.presetWithStrings({
			"_name": item.name(), "_path": item.path(), "actual_label": item.label(),
			"actual_description": item.description(), "actual_value": item.valueStr() });
		// preset children values
		let childrenValues = {}
		for (let child of item.getChildren())
			childrenValues[child.name()] = child.valueStr();
		form.presetWithStrings(childrenValues);
		// show form
		this.onSubmit = this.editItem_done
		this.onButtonClick = this.ignore
		modal.showForm(this, form);
	}

	/**
	 * parse the changes into memory and create a change list to post to the server
	 */
	collectChanges() {
		let csv = ""
		for (let fieldName in form.inputFields) {
			let f = form.inputFields[fieldName]
			if (f["changed"]) {
				if (f["isProperty"])
					f["item"].parseProperty(f["name"], f["entered"], config.language())
				else
					// for children only the actual value can be adjusted
					f["item"].parseProperty(PropertyName.ACTUAL_VALUE, f["entered"], config.language())
				csv += "\n" + f["name"] + ";" + f["entered"]
			}
		}
		return (csv.length > 0) ? "field;value" + csv : ""
	}

	editItem_done () {
		// post the change to the server.
		this.#modificationsCsv = this.collectChanges()
		this.#mode = 2
		cfgPanel.postModify(this.#mode, this.#recordItem.getPath(), this.#modificationsCsv, this.#onPostItemCallback);
	}

	createItem_do (item) {
		this.#recordItem = item
		let nodeAddableType = item.nodeAddableType();
		// identify the object to create
		if (nodeAddableType.length === 0) {
			modal.showHtml("<h3>" + i18n.t("mliI2K|No data type declared to...") + "</h3>");
			return;
		}
	    // get a new item with a forbidden name to ensure there will be no name
		// conflicts
		let newChild = item.addEmptyChild();
		// now that the template is prepared, initialise the form
		form.init(newChild, (newChild.recordEditForm().length === 0) ?
			this.#defaultItemForm(newChild, "*") : "");
		// preset path. All other properties are empty.
		form.presetWithStrings({ "_path": newChild.path() });
		// after form creation the child is no more used.
		item.removeChild(newChild)
		newChild.destroy()
		// show form
		this.onSubmit = this.createItem_done
		this.onButtonClick = this.ignore
		modal.showForm(this, form);
	}
	
	createItem_done () {
		// check for duplicate or forbidden name
		let childName = form.inputFields["_name"]["entered"];
		if (this.#isOkNames(this.#recordItem, childName, form.inputFields["actual_label"]["entered"])) {
			// post the change to the server.
			this.#modificationsCsv = this.collectChanges()
			// set value_type and value_reference for templates as hidden fields
			let isTemplate = this.#recordItem.nodeAddableType().startsWith(".")
			this.#modificationsCsv += "\npath;" + this.#recordItem.getPath();
			this.#modificationsCsv += "\nvalue_type;" + ((isTemplate) ? "template" :  this.#recordItem.nodeAddableType());
			this.#modificationsCsv += "\nvalue_reference;" + ((isTemplate) ? this.#recordItem.nodeAddableType() : "");
			this.#mode = 1
			cfgPanel.postModify(this.#mode, this.#recordItem.getPath(), this.#modificationsCsv, this.#onPostItemCallback);
		} else {
			let error =  i18n.t("IRf2pK|Invalid name or label.");
			modal.showForm(this, form, error);
		}
	}

	execute_confirmed() {
		let modificationsList = Codec.csvToMap(this.#modificationsCsv)
		let modifications = {}
		for (let modification of modificationsList)
			modifications[modification["field"]] = modification["value"]
		if (this.#mode === 1)
			// Add
			this.#recordItem.putChild(modifications)
		else if (this.#mode === 2) {
			// update
			for (let modification of modificationsList)
				if (PropertyName.valueOfOrInvalid(modification["field"]) !== Property.invalid)
					this.#recordItem.parseProperty(modification["field"], modification["value"], config.language())
				else if (this.#recordItem.hasChild(modification["field"]))
					this.#recordItem.getChild(modification["field"]).parseProperty("actual_value", modification["value"])
		}
		else if (this.#mode === 3) {
			// delete
			this.#recordItem.parent().removeChild(this.#recordItem)
			this.#recordItem.destroy()
		}
	}

	/**
	 * Do all name checks: name is a valid identifier, not reserved and not
	 * duplicate. Local name is not duplicate. If the name argument is not set,
	 * the respective check is skipped.
	 */
	#isOkNames(parent, nameToCheck, labelToCheck) {
		if (!nameToCheck)
			return false
		// check validity of name
		Validator.checkAgainstRule(nameToCheck, "identifier")
		if (Findings.countErrors() > 0) {
			modal.showForm(this, form, i18n.t("GWrjW8|Invalid child name %1", nameToCheck));
			return false;
		}
		if (parent.hasChild(nameToCheck)) {
			modal.showForm(this, form, i18n.t("uTPBBb|Duplicate child name %1", nameToCheck));
			return false;
		}
		if (PropertyName.valueOfOrInvalid(nameToCheck) !== PropertyName.INVALID) {
			modal.showForm(this, form, i18n.t("S81unZ|Forbidden to use reserve...", nameToCheck));
			return false;
		}
		// check for duplicate label. Empty labels may be duplicate as the only exception
		if (labelToCheck) {
			for (let child of parent.getChildren()) {
				if (labelToCheck.localeCompare(child.label()) === 0) {
					modal.showForm(this, form, i18n.t("2IFi0b|Duplicate local name %1", labelToCheck));
					return false;
				}
			}
		}
		// all was fine, return true.
		return true;
	}

	#onPostItemCallback(success, done) {
		let responseParts = done.split(";");
		if (success) // success = false at this point means that the HTTP succeeded
			success = ((parseInt(responseParts[0]) < 40) && (parseInt(responseParts[0]) > 0));
		if (success) { // success = false at this point means that the requested transaction succeeded
			formHandler.execute_confirmed()
			console.log("Configuration modification completed.")
			cfgPanel.refresh()
		}
		else {
			console.log("Configuration modification error " + responseParts[0] + " " + responseParts[1]);
			let formError = "<h4>" + i18n.t("AsOtjr|Error") + "</h4><p>" +
				i18n.t("YwUSws|the server responded wit...")  + ": " + responseParts[0] + " " + responseParts[1] + "</p>"
			modal.showHtml(formError)
		}
	}

	/* ---------------------------------------------------------------- */
	/* ---------- LIST ELEMENT EDITING IN THE DIALOG ------------------ */
	/* ---------------------------------------------------------------- */
	/**
	 * Bind the add element event. Because the event will trigger renewal of the
	 * add-Button, this needs to be separate.
	 */
	#bindAddElementEvent(fieldName, list) {
		let that = this;
		$('.addElement').click(function() {
			let boxes = $('div[id^="listElementBox-"]');
			// get the one before last, since last is the add box
			let lastInputBox = boxes[boxes.length - 2];
			let id = $(lastInputBox).attr("id");
			let next = parseInt(id.split("-")[1]) + 1;
			list.push("")
			let fieldHtml = form.getListElementField(fieldName, list, next);
			fieldHtml = that.#listElementFrame
				.replace(/\{index}/g, "" + next)
				.replace(/\{listElement}/g, fieldHtml);
			$('#listElement-add').remove();
			$(lastInputBox).parent().append(fieldHtml + that.#listElementAdd);
			// the added element has no remove-binding yet.
			that.#bindRemoveElementEvent()
		});
	}
	/**
	 * Bind the add element event. Because the event will trigger renewal of the
	 * add-Button, this needs to be separate.
	 */
	#bindRemoveElementEvent(fieldName) {
		$('.removeElement').unbind("click").bind('click', function() {
			// remove both the htnl-form input and the application form field.
			let id = $(this).attr("id");
			let i = parseInt(id.split("-")[1]);
			form.removeField(fieldName + "_" + i);
			$("#listElementBox-" + i).remove();
		});
	}

	/**
	 * Show the dialogue with the option to edit the list
	 */
	editList(form, fieldName) {
		let fieldListHtml = "";
		let f = form.inputFields[fieldName]
		let preset = f["preset"];
		let entered = f["entered"]
		let list = (entered) ? Codec.splitCsvRow(entered, ",")
			: ((preset) ? Codec.splitCsvRow(preset, ",") : [])
		if (list.length === 0) {
			let fieldHtml = form.getListElementField(fieldName, list, 0);
			fieldListHtml += this.#listElementFrame.replace(/\{index\}/g, "0")
				.replace(/\{listElement\}/g, fieldHtml);
		} else {
			for (let i = 1; i <= list.length; i++) {
				let fieldHtml = form.getListElementField(fieldName, list, i);
				fieldListHtml += this.#listElementFrame.replace(/\{index\}/g, i.toString())
					.replace(/\{listElement\}/g, fieldHtml);
			}
		}
		dialog.showHtml(fieldListHtml + this.#listElementAdd,
			[ i18n.t("BcbLd0|Done"),  i18n.t("6GkrQI|Cancel") ], this.editListDone, fieldName);
		// add lookup and replace uuids by names
		for (let i = 1; i <= list.length; i++) {
			let input = $("#" + form.inputFields[fieldName + "_" + i]["id"])[0];
			if (f["inputType"].startsWith("auto"))
				AutoComplete.set(input, inputValidator, f, this);
		}
		// add events
		this.#bindRemoveElementEvent(fieldName);
		this.#bindAddElementEvent(fieldName, list);
	}

	/**
	 * The list editing is completed, get the value.
	 */
	editListDone(button, fieldName) {
		if (button === "buttonRight")
			return;
		let inputs = $(`select[name^="${fieldName}_"], input[name^="${fieldName}_"]`);
		let list = ""
		inputs.each(function() {
			list += "," + Codec.encodeCsvEntry($(this).val(), ",");
			let id = $(this).attr("id")
			form.removeField(id.split(/-/g)[2]);
		});
		// set the JavaScript field and the page input element to the new value
		form.inputFields[fieldName]["entered"] = (list) ? list.substring(1) : "";
		let input = $("#" + form.inputFields[fieldName]["id"])
		$(input).val(form.inputFields[fieldName]["entered"])
	}

	/**
	 * Edit a micro time value. This is usually the invalid_from field
	 */
	editValidityPeriod(form, fieldName) {
		let f = form.inputFields[fieldName];
		if (!f)
			return;
		let validityNotice = (fieldName === "valid_from")
			? i18n.t("ezukBb|Change the °valid from° ...")
			: i18n.t("a1SFGQ|Set the °invalid from° d...")
		let dialogHtml = this.#validityFrame.replace(/\{validityDate\}/g, f["preset"])
			.replace(/\{validityNotice\}/g, validityNotice);
		dialog.showHtml(dialogHtml,
			[ i18n.t("UIhMz8|Done"),  i18n.t("5uSD5Z|Cancel") ], this.editValidityPeriodDone, fieldName);
	}

	/**
	 * The microtime editing is completed, get the value.
	 */
	editValidityPeriodDone(button, fieldName) {
		if (button === "buttonRight")
			return;
		let dateEntered = $('#validity_date').val()
		form.inputFields[fieldName]["entered"] = dateEntered;
		$("#" + form.inputFields[fieldName]["id"]).val(dateEntered)
		$("#inputBlock-" + form.fsId() +"-contentFields").hide()
		if (fieldName === "valid_from") {
			// create a new period. delete the period end entry
			$("#input-" + form.fsId() +"-uid").val("-new-")
			let createdBy = User.getInstance().fullName()
			$("#input-" + form.fsId() +"-created_by").val(createdBy)
			let createdOn = Formatter.format(new Date(), ParserName.DATETIME, config.language())
			$("#input-" + form.fsId() +"-created_on").val(createdOn)
			$("#input-" + form.fsId() +"-modified").val(createdOn)
			$("#" + form.inputFields["invalid_from"]["id"]).val("")
			$("#" + form.inputFields["submit"]["id"]).val("create new period")
		} else {
			// close the period. Reset the validFrom entry to the preset value.
			$("#" + form.inputFields["valid_from"]["id"]).val(form.inputFields["valid_from"]["preset"])
			$("#" + form.inputFields["submit"]["id"]).val((dateEntered) ? "close period" : "open period")
		}
	}

}
