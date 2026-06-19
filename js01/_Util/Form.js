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
 * This class provides a form segment for a web file. <p>The definition must be a CSV-file, all entries
 * without line breaks, with the first line being always
 * "tags;modifier;name;value;label;type;class;size;maxlength" and the following lines the respective values.
 * The usage has a lot of options and parameters, please see the tfyh-PHP framework description for
 * details.</p>
 */
class Form {

	static formErrorsToHtml(formErrors, centered = false) {
		if (formErrors.length === 0)
			return "";
		else
			if (centered)
				return '<p><span style="color:#A22;"><b>' + i18n.t("NLNSFH|Error:") +
					" </b> " + formErrors + "</span></p>";
			else
				return '<p style="text-align:center"><span style="color:#A22;"><b>' + i18n.t("NLNSFH|Error:") +
					" </b> " + formErrors + "</span></p>";
	}

    formErrors; // validation errors as String
	inputFields = {}   // used by the FormHandler class
	#recordItem
	#fsId
	#blockCloseTag = "";

	/* -------------------------------------------------------------- */
	/* ---- INITIALIZATION ------------------------------------------ */
    /* -------------------------------------------------------------- */
    
    /**
	 * Empty constructor. This is done but once. The same form is always reused.
	 */
    constructor (fsId) {
		// In the PHP twin code the script initialisation creates this random id.
		this.#fsId = fsId ?? Ids.generateUid(3)
		this.serverForm = (fsId && (fsId.length > 0))
	}

	/**
	 * Return the form's random id. This is used by the Modal to assign ids to
	 * the form input fields.
	 */
	fsId() { return this.#fsId }

	/**
	 * Return the form's record item.
	 */
	recordItem() { return this.#recordItem }

	/* ---------------------------------------------------------------------- */
	/* --------------------- INITIALIZATION --------------------------------- */
	/* ---------------------------------------------------------------------- */
	/**
	 * Initialise the form. Separate function to keep aligned with the
	 *  twin code, in which external initialisation of a form is used.
	 */
    init(recordItem, formDefinition = "") {
		// hierarchy of form definitions: 1. explicitly provided, 2.
		// part of the record's properties, 3. auto-generated from the record's properties.
		if (formDefinition.length === 0) {
			formDefinition = recordItem.recordEditForm();
			if (formDefinition.length === 0) {
				let recordToEdit = new Record(recordItem)
				formDefinition = recordToEdit.defaultEditForm()
			}
		} else if (!formDefinition.startsWith("rowTag;names;labels"))
			formDefinition = "rowTag;names;labels\n" + formDefinition
		let definitionRows = Codec.csvToMap(formDefinition);
		this.#recordItem = recordItem
		this.inputFields = {}
		// * = required, . = hidden, ! = read-only, ~ = display value like a bold label
		let modifiers = ["*", ".", "!", "~", ">", "<"]
		let i = 0
		let formFieldsItem = config.getItem(".framework.form_fields");
		for (let definitionRow of definitionRows) {
			let rowTag = definitionRow["rowTag"].replace("r", "<div class='w3-row'>")
				.replace("R", "<div class='w3-row' style='margin-top:0.6em'>")
			let names = Parser.parse(definitionRow["names"], ParserName.STRING_LIST, Language.CSV)
			let labels = Parser.parse(definitionRow["labels"], ParserName.STRING_LIST, Language.CSV)
			let columnTag = "<div class='w3-col l" + names.length + "'>"
			let c = 0
			for (let name of names) {
				if (name.length > 0) {
					let modifier = name.substring(0, 1)
					let inputName = name
					if (modifiers.indexOf(modifier) >= 0)
						inputName = name.substring(1)
					else
						modifier = ""

					let propertyName = PropertyName.valueOfOrInvalid(inputName)
					let isProperty = propertyName !== PropertyName.INVALID
					let property = Property.descriptor[propertyName] ?? Property.invalid
					let isActualValue = (propertyName === PropertyName.ACTUAL_VALUE)
					// the item to be modified is either a property, a child of the
					// config item of this form or a generic form field
					let item = (isProperty) ? recordItem : ((recordItem.hasChild(inputName)) ?
						recordItem.getChild(inputName) : formFieldsItem.getChild(inputName));
					let itemType = (item) ? item.type() : Type.get("none")
					if (((modifier === "~")) && ! isProperty && ! item)
						inputName = inputName + "_" + i // read-only data can get always the same name. This makes then unique.
					let defaultLabel = (isProperty) ? property.label() : ((item) ? item.label() : "")

					// The input field array holds all information, even across multiple steps
					let id = "input-" + this.#fsId + "-" + inputName
					this.inputFields[inputName] = {
						openTag: (c === 0) ? rowTag  + columnTag : columnTag,
						closeTag: (c === (names.length - 1)) ? "</div></div>" : "</div>",
						html: "",
						modifier: modifier,
						name: inputName,
						label: (labels[c] && (labels[c].length > 0))
							? labels[c] : defaultLabel,
						type: (isProperty) ? ((Property.isValue(inputName))
							? itemType : Type.get("string")) : itemType,
						inputType: (isProperty && !isActualValue)
							? "text" : ((item) ? item.inputType() : "text"),
						id: id,
						size: "95%",
						options: {},
						preset: "",
						entered: "",
						parsed: ParserConstraints.empty(itemType.parser()),
						findings: "",
						isProperty: isProperty,
						property: property,
						item: item
					}
					// the JavaScript clone for server side forms shall read the options from data only for
					// configuration editing and for data editing parse the options provided by the server.
					if (recordItem.parent !== config.getItem(".tables"))
						this.#readOptions(this.inputFields[inputName])

				} else {
					// empty names create empty block in the form
					let inputName = "_" + i
					let id = "input-" + this.#fsId + "-" + inputName
					this.inputFields[inputName] = {
						openTag: (c === 0) ? rowTag + columnTag : columnTag,
						closeTag: (c === (names.length - 1)) ? "</div></div>" : "</div>",
						html: "", modifier: "~", name: inputName,
						label: "", type: Type.get("string"), inputType: "text", id: id,
						options: {}, preset: "", entered: "", parsed: "", findings: "",
						isProperty: false, property: Property.invalid,
						item: config.invalidItem
					}
				}
				c++
				i++
			}
		}
    }

	/**
	 * For forms which are not created by this code, but rather provided by the server side, read the options,
	 * and the preset and link the form input to the field.
	 */
	parseProvided() {
		for (let inputName in this.inputFields) {
			let f = this.inputFields[inputName]
			let providedOptions = $("#" + f["id"] + "-options").html()
			let options = {}
			if (providedOptions)
				for (let option of providedOptions.split("\n"))
					options[option.split("=")[0]] = option.split("=")[1]
			f["options"] = options
			f["input"] = $("#" + f["id"])[0]
			f["preset"] = $(f["input"]).val()
		}
	}

	/**
	 * Set all autocomplete triggers
	 */
	setAutocomplete() {
		for (let inputName in this.inputFields) {
			let f = form.inputFields[inputName]
			if (f["inputType"].startsWith("auto"))
				AutoComplete.set(f["input"], inputValidator, f, formHandler)
		}
	}

	/**
	 * Set all special input triggers
	 */
	setSpecialInputTrigger() {
		$(".listInputField").click(function() {
			let id = $(this).attr("id")
			let fieldName = id.split(/-/g)[2]
			formHandler.editList(form, fieldName)
		})
		$(".validityPeriodInputField").click(function() {
			let id = $(this).attr("id")
			let fieldName = id.split(/-/g)[2]
			formHandler.editValidityPeriod(form, fieldName)
		})
		$(".formDivHeadline").click(function() {
			let id = $(this).attr("id")
			let blockName = id.split(/-/g)[3]
			$("#inputBlock-" + form.fsId() + "-" + blockName).toggle()
		})
		$("[id^=inputBlock]").each(function() {
			let id = $(this).attr("id")
			if (id.indexOf("contentFields") < 0) {
				$(this).hide()
			}
		})
	}

	/**
	 * Read the options into the input field as they are provided by the respective value_reference property.
	 */
    #readOptions(inputField) {

		let valueReference = (inputField["item"]) ? inputField["item"].valueReference() : ""
		if (valueReference.length === 0)
			return

		let inputOptions = {}
		if (valueReference.startsWith("[")) {
			// a predefined configured list
			let options = Parser.parse(valueReference, ParserName.STRING_LIST, Language.CSV)
			for (let option of options)  {
				if (option.indexOf("=") >= 0)
					inputOptions[option.split("=")[0]] = option.split("=")[1]
				else
					inputOptions[option] = option
			}
		} else if (valueReference.startsWith(".")) {
			// an item catalogue
			let headItem = config.getItem(valueReference)
			for (let child of headItem.getChildren())
				inputOptions[child.name()] = child.label()
		} else {
			// a table
			let tableName = valueReference.split(".")[0]
			let indices = Indices.getInstance()
			indices.buildIndexOfNames(tableName)
			inputOptions = indices.getNames(tableName)
		}
		inputField["options"] = inputOptions;
    }

	/**
	 * Resolve the entered value as name into an id as defined in the value reference. Returns the id on success and
	 * the original value on failure.
	 */
	resolve(inputField) {
		let valueReference = (inputField["item"]) ? inputField["item"].valueReference() : "";
		// TODO, currently item catalogs use no auto-completion
		let toResolve = inputField["entered"];
		if ((valueReference.length > 0) && ! valueReference.startsWith(".")) {
			// a table
			let tableName = valueReference.split(".")[0];
			let indices = Indices.getInstance();
			let referenceField = valueReference.split(".")[1];
			// a table as reference
			let resolved = "";
			if (referenceField === "uuid") {
				// resolve a name to an uuid
				let values = (ParserName.isList(inputField["type"].parser())) ? inputField["parsed"] : [ toResolve ];
				for (let value of values) {
					let uuid = indices.getUuid(tableName, value);
					resolved += ", " + ((uuid.length === 0) ? value : uuid);
				}
				if (resolved.length > 2)
					resolved = resolved.substring(2);
			}
			return resolved;
		}
		// for uuid_or_name type fields, a reference may validly not resolvable
		return toResolve;
	}

	/* ---------------------------------------------------------------------- */
	/* --------------------- PRESET VALUES ---------------------------------- */
	/* ---------------------------------------------------------------------- */
	/**
	 * Preset all values of the form with those of the provided row. Strings are shown in the form as is,
	 * they must be formatted, and ids resolved. Only dates are reformatted from the local format to the
	 * browser-expected YYYY-MM-DD.
	 */
	presetWithStrings(row) {
		for (let fieldName in this.inputFields) {
			if (row.hasOwnProperty(fieldName))
				this.#presetWithString(fieldName, row[fieldName]);
		}
	}
	/**
	 * preset a single value of the form with a formatted and resolved value. Only dates are reformatted from
	 * the local format to the browser-expected YYYY-MM-DD.
	 */
	#presetWithString (fieldName, formattedValue) {
		if (this.inputFields.hasOwnProperty(fieldName)) {
			// reformat Date and DateTime to iso compatible
			let parser = this.inputFields[fieldName]["type"].parser();
			let isDateOrDateTime = (parser === ParserName.DATE) || (parser === ParserName.DATETIME);
			let language = (isDateOrDateTime) ? Language.CSV : config.language();
			this.inputFields[fieldName]["preset"] = (isDateOrDateTime)
				? Formatter.format(Parser.parse(formattedValue, parser, language), parser, Language.CSV)
				: formattedValue;
		}
	}

	/* ---------------------------------------------------------------------- */
	/* --------------------- DISPLAY FORM AS HTML --------------------------- */
	/* ---------------------------------------------------------------------- */
	/**
	 * Return an HTML code of this form based on its definition. Error noticing and
	 * step repetition are different in JavaScript than in PHP. Posting a
	 * form ends the http transaction in PHP so that values are kept in the PHP
	 * super-global $_SESSION variable and the error display must be handled there
	 * by the caller.
	 */
	getHtml (previousErrors) {
		let formErrors = "";
		// put the values of the previous attempt back into the form. Erroneous ones will not be used.
		if ((typeof previousErrors !== 'undefined') && (previousErrors.length > 0)) {
			formErrors = previousErrors;
			this.presetWithStrings(this.#entered());
		}
		if (Object.keys(this.inputFields).length === 0)
			return "<p>Empty form template.</p>"; // no i18n needed, programming error indication
		// start the form. The form has no action in itself and shall not reload
		// when submitting. Therefore, it is implemented as "div" rather than
		// "form"
		let form = '		<div>\n';
		if (formErrors)
			form += "<p style=\"color:#A22;\">" + Codec.htmlSpecialChars(formErrors) + "<p>";
		this.blockCloseTag = "";
		for (let fieldName in this.inputFields)
			form += this.getFieldHtml(this.inputFields[fieldName])
		form += this.blockCloseTag;
		form += "	</div>\n";
		return form;
	}

    /**
	 * Get the html representation of a single field.
	 */
    getFieldHtml(f) {

		// start the input field with the label
    	let mandatoryStr = (f["modifier"] === "*") ? "*" : "";
    	let isInlineLabel = (f["inputType"] === "radio") ||
			(f["inputType"] === "checkbox") || (f["inputType"] === "input") ||
			(!f["label"] || (f["label"].length === 0));
		let isList = (f["type"] && ParserName.isList(f["type"].parser()));
		let isDateTime = (f["type"]) && (f["type"].parser() === ParserName.DATETIME);
		let isValidFrom = (f["modifier"] === ">");
		let isInvalidFrom = (f["modifier"] === "<");
		let isHeadline = (f["modifier"] === "§");
		let isTextArea = (f["inputType"].indexOf("textarea") >= 0)

    	// provide border and label styling. Include the case of invalid input.
    	let inputErrorStyleStr = "";
    	let labelSpanErrorOpen = "";
    	let labelSpanErrorClose = "";
    	if (f["findings"].length !== 0) {
			inputErrorStyleStr = ";order:1px solid #A22;border-radius: 0px;";
			labelSpanErrorOpen = "<span style=\"color:#A22;\">";
			labelSpanErrorClose = "</span>";
		}
		// add size styling
		let isSubmit = (f["name"].indexOf("submit") >= 0);
		let overflowVisible = (isSubmit) ? "overflow:visible;" : "";
        let sizeStyleStr = (f["size"].length > 0) ? "width:" + f["size"] + ';' + overflowVisible : "";
		let styleStr = inputErrorStyleStr + sizeStyleStr
		styleStr = (styleStr.length > 0) ? "style='" + styleStr + "' " : ""

    	// start with tags and show label for input
    	let inputOuterDivOpen = "<div id='div-"  + f["id"] +
			"' style='word-wrap: break-word;overflow:hidden;padding:2px;'>";
		let labelForOpen = (isHeadline || isTextArea) ? "" : "<label for='" + f["id"] + "'>";
		let labelForClose = (isHeadline || isTextArea) ? "" : "</label>";
		let labelStr = (isInlineLabel || isSubmit) ? "" : labelSpanErrorOpen + labelForOpen + mandatoryStr
				+ (Formatter.styleToHtml(f["label"]) ?? "") + labelSpanErrorClose + labelForClose + "<br>\n";

    	// predefine values for name, style, id and class attributes.
		let nameStr = (f["name"].length > 0) ? 'name="' + f["name"] + '" ' : "";
		let typeStr = (f["inputType"].length > 0) ? 'type="' + f["inputType"] + '" ' : "";
    	let idStr = ' id="' + f["id"] + '" ';
        let classStr = (f["modifier"] === "~") ? "display-bold"
				: ((f["inputType"].startsWith("auto")) ? "formInput autocomplete"
					: ((f["inputType"] === "select") ? "formSelector"
						: ((isSubmit) ? "formButton"
							: "formInput")));
		if (isList)
			classStr = "listInputField " + classStr
		else if (isValidFrom || isInvalidFrom)
			classStr = "validityPeriodInputField " + classStr;
		classStr = "class='" + classStr + "' "
        let disabledStr = ((f["modifier"] === "!") || (f["modifier"] === "~") || isList) ? "disabled " : "";

		let inputHtml = ""
		// if a value was previously entered, use it instead of the preset. This happens if a form returns with an
		// error message on some (other) erroneous field.
		if (f["entered"] && (f["entered"].length > 0))
			f["preset"] = f["entered"];
		if (isHeadline) {
			labelStr = "<span class='formHeadline'>" + labelStr + "</span>";
			inputOuterDivOpen = "<div id='div-" + f["id"] + "' class='formDivHeadline'>";
			// compile input element
		} else if (f["inputType"].indexOf("auto") >= 0) {
			// special case: autocompletion field. Autocompletion is a JavaScript function.
			// use default input type.
			inputHtml += "<input " + typeStr + nameStr + styleStr + classStr;
			if (f["preset"])
				inputHtml += 'value="' + f["preset"] + '" ';
			inputHtml += idStr + disabledStr + ">\n";
			// add options
			inputHtml += "<span style='display:none;' id=" + f["id"] + "-options>";
			for (let option in f["options"]) {
				let label = f["options"][option]
				inputHtml += option + "=" + Codec.htmlSpecialChars(label) + "\n";
			}
			inputHtml += "</span>\n";
		}
    	else if (f["inputType"].indexOf("select") >= 0) {
			// special case: select field.
			inputHtml += "<select " + nameStr + styleStr + classStr + idStr + disabledStr + ">\n";
    		// code all options as defined
    		for (let option in f["options"]) {
				let label = f["options"][option]
    			let selected = (label === f["preset"]) ? "selected " : "";
				inputHtml += '<option ' + selected + 'value="' + option + '">'
			        	+ label + "</option>\n";
            }
			inputHtml += "</select>\n";

    	} else if (f["inputType"] && f["inputType"].indexOf("radio") >= 0) {
    		// code all options as defined
    		for (let option in f["options"]) {
    			// wrap into radiobutton frame first.
				let label = f["options"][option]
    			let checked = (label === f["preset"]) ? "checked " : "";
				inputHtml += "<label class='cb-container'>" + f["label"] + "\n";
                // no class definitions allowed for radio selections
				inputHtml += "<input " + typeStr + nameStr + styleStr + classStr + " value='" + option
                     	+ checked + idStr + disabledStr + '>' + label + "<br><br>\n";
				inputHtml += '<span class="cb-radio"></span></label>';
            }

        } else if (f["inputType"] && f["inputType"].indexOf("checkbox") >= 0) {
			inputHtml += '<label class="cb-container"  style="margin-top:0.5em">' + f["label"] + "\n";
            // no class definitions allowed for checkboxes
			inputHtml += '<input ' + typeStr + nameStr + styleStr;
            // In case of a checkbox, set checked for not-empty other than "false".
            if (f["preset"] && (f["preset"].length > 0))
				inputHtml += "checked ";
			inputHtml += idStr + disabledStr + "><span class='cb-checkmark'></span></label>";
        
        } else if (isTextArea) {
			inputHtml += '<textarea ' + nameStr + styleStr + classStr + idStr + disabledStr + '>'
            	+ f["preset"] + '</textarea><br>' + "\n";

		} else if (isDateTime) {
			let presetDate = f["preset"].split(" ")[0];
			let presetTime = f["preset"].split(" ")[1].substring(0, 5);
			inputHtml += "<input type='date' name ='" + f["name"] + "_d' value='" + presetDate + "' id='" +
				f["id"] + "_d' " + classStr + ">";
			inputHtml += "&nbsp;<input type='time' name ='" + f["name"] + "_t' value='" + presetTime + "' id='" +
				f["id"] + "_t' " + classStr + ">";

		} else if (isSubmit) {
			inputHtml += "<input " + "type='submit' " + nameStr + "value='" + f["label"] + "' " + idStr + classStr + ">";

		} else if (f["preset"] || (f["modifier"] !== "~")) {
            // default input type. (For empty values in display-only mode, skip this.)
			inputHtml += "<input " + typeStr + nameStr + styleStr + classStr;
            // set value.
            if (f["preset"].length > 0)
				inputHtml += 'value="' + f["preset"] + '" ';
			inputHtml += idStr + disabledStr + ">\n";
            // add the inline label.
            if (isInlineLabel)
				inputHtml += "&nbsp;" + labelSpanErrorOpen + mandatoryStr + (f["label"] ?? "") +
                        labelSpanErrorClose + "\n";
        } else
			labelStr = "<b>" + labelStr + "</b>"

		// compile the input field
		let fieldHtml = f["openTag"] + inputOuterDivOpen + labelStr + inputHtml +
			"</div>" + f["closeTag"];

		// add the block information
		if (isSubmit) {
			// The Submit button always closes a block
			fieldHtml = this.blockCloseTag + fieldHtml;
			this.#blockCloseTag = "";
		} else if (isHeadline) {
			// a new headline closes the previous block and opens a new one
			fieldHtml = this.#blockCloseTag + fieldHtml + "<div id='inputBlock-" + this.fsId + "-" + f["name"] + "'>";
			this.#blockCloseTag = "</div>";
		}

		return fieldHtml;
    }

	#entered(includeUnchanged = true) {
		let entered = {}
		for (let fieldName in this.inputFields) {
			let f = this.inputFields[fieldName]
			let matters = !fieldName.startsWith("_") && (fieldName !== "submit");
			if (matters && (includeUnchanged || f["changed"]))
				entered[fieldName] = (f["validated"]) ? f["validated"]
					: ((f["entered"]) ? f["entered"]
						: ((f["preset"]) ? f["preset"] : ""));
		}
		return entered;
	}

	/* ---------------------------------------------------------------------- */
	/* --------------- EVALUATION OF FORM ENTRIES --------------------------- */
	/* ---------------------------------------------------------------------- */
	#validateField(fieldName) {
		let f = this.inputFields[fieldName];
		f["changed"] = false
		if (f["isProperty"] || f["item"]) {
			// only parse data for which a field exists
			f["entered"] = this.#readField(f)
			f["findings"] = ""
			f["changed"] = (f["preset"] !== f["entered"])
				&& (f["modifier"] !== "!") && (f["modifier"] !== "~");
			if (f["changed"]) {
				// only validate data if changed.
				if ((f["modifier"] === "*") && (f["entered"].length === 0)) {
					f["findings"] += i18n.t("b33lk9|Please enter a value in ...", f["label"]) + ","
				} else {
					// parse (syntactical check)
					Findings.clearFindings()
					f["parsed"] = Parser.parse(f["entered"], f["type"].parser())
					// validate: limits and reference resolving
					let item = f["item"]
					if (item.valueReference().length > 0)
						f["validated"] = this.resolve(f);
					else
						f["validated"] = Validator.adjustToLimits(f["parsed"], f["type"], item.valueMin(),
							item.valueMax(), item.valueSize());
					// validate: rule check
					Validator.checkAgainstRule(f["validated"], item.validationRules())
					f["findings"] = Findings.getFindings(false)
				}
				this.formErrors += f["findings"]
			}
		}
		return f["changed"]
	}

	/**
	 * read all values entered.
	 */
	validate () {
		this.formErrors = ""
		let anyChange = false
		for (let fieldName in this.inputFields)
			anyChange = this.#validateField(fieldName) || anyChange
		return anyChange
	}

	/**
	 * Read a single field as it was entered. Note: this differs from the PHP implementation because it does not
	 * need super-global caching but a specific way of checkbox handling instead.
	 */
	#readField(f) {
    	if (!f)
    		return null;
		let input = $('#' + f["id"])[0];
		if (! input)
			return null;
		let value = input.value;
		if ((f["type"]).parser() === ParserName.DATETIME) {
			let inputDate = $("#" + f["id"] + "_d").val()
			let inputTime = $("#" + f["id"] + "_t").val()
			let time = (inputTime ?? "00:00");
			if (time.length > 0)
				time += ":00"
			f["entered"] = (inputDate.length > 0) ? inputDate + " " + time : "";
		}
		// special case: checkboxes are "on" or "", to be compatible with the server side PHP implementation and to
		// detect the change.
		if (f["inputType"] && f["inputType"].localeCompare("checkbox") === 0)
			value = ($(input).is(':checked')) ? "on" : "";
		// special case: selected options have a different way of getting at.
		if (f["inputType"] && f["inputType"].toLowerCase().startsWith("select"))
			value = $(input).find(":selected").val();
		// replace "undefined" to circumvent execution errors
		if (!value) 
			value = "";
		return value;
    }

	/* ---------------------------------------------------------------------- */
	/* --------------- LIST ELEMENT EDITING SUPPORT ------------------------- */
	/* ---------------------------------------------------------------------- */

	/**
	 * Get a single field of a list value. This adds a field to the form
	 * definitions. It is used to create inputs for entering a list value one by
	 * one.
	 * JavaScript only.
	 */
	getListElementField(fieldName, list, i) {
		let elementFieldName = fieldName + "_" + i;
		let rowTag = "<div class='w3-row'>"
		let columnTag = "<div class='w3-col l1'>"
		let mother = this.inputFields[fieldName]
		this.#readOptions(mother)
		let type = (mother["type"].name() === "string_list") ? Type.get("string") : Type.get("int")
		this.inputFields[elementFieldName] = {
			openTag: rowTag  + columnTag,
			closeTag: "</div></div>",
			html: "",
			modifier: "",
			name: elementFieldName,
			label: mother["label"] + " #" + i,
			type: type,
			inputType: mother["inputType"],
			id: "input-" + this.#fsId + "-" + elementFieldName,
			size: "95%",
			options: mother["options"],
			preset: list[i - 1],
			entered: "",
			parsed: "",
			findings: "",
			isProperty: false,
			property: null,
			item: null
		}
		// now create the HTML and return it.
		return this.getFieldHtml(this.inputFields[elementFieldName], true);
	}

	/**
	 * Remove a single field, used for those which have been added as list
	 * element fields.
	 */
	removeField(fieldName) {
		delete this.inputFields[fieldName];
	}


}
