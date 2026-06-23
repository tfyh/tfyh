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
 * A Modal "window" which is used for all dialogues of the application.
 */

class Modal {

	// reverencing the modal objects
	#modal;
	#modal_tabs = "";
	#modal_content = "";
	#modal_close = '<span class="closeModal"> &times; </span>';
	#modal_previous = '<span class="previousModal">	&#x25C2; </span>';
	#modal_tabRow = '<div class="w3-row">{tabs}</div>';
	#modal_tab = '<div class="w3-col l{count} formTab" id="{id}">{tab}</div>';
	#modal_activeTab = '<div class="w3-col l{count} formTab formTab-active" id="{id}">{tab}</div>';
	#shiftDown = false;
	#ctrlDown = false;
	#altDown = false;

	#progressURL = "";
	#chunk = 100;

	#goBack;
	
	constructor() {
		this.#modal = document.getElementById('tfyhModal');
		this.#modal_content = document.getElementById('tfyhModal-content');
	}

	/**
	 * after modal content has changed, events need to be rebound to buttons
	 * etc.
	 */
	#updateModalCloseBind () {
		let that = this;
		$('.closeModal').bind('click', function() {
			if (that.#goBack) 
				that.#goBack(true);
			that.#modal.style.display = "none";
			that.setTabs(); // clear tabs if this was not a tab clicked.
		});
		$('.previousModal').bind('click', function() {
			if (that.#goBack) 
				that.#goBack(false);
		});
	}

	/**
	 * submit the form. This will be triggered by click or enter.
	 */
	#submitForm(formHandler, form) {
		let anyChange = form.validate()
		if (! anyChange)
			this.showForm(formHandler, form, i18n.t("S6HlK8|Nothing changed. Abort w..."));
		// values will never end up unquoted in SQL statements
		else if (form.formErrors && form.formErrors.length > 1)
 			this.showForm(formHandler, form, form.formErrors);
		else {
			this.#modal.style.display = "none";
			this.setTabs(); // clear tabs if this was not a tab clicked.
			// select the handling function by using the form name.
			formHandler.onSubmit();
		}
	}
	
	/**
	 * bind an event to all modal buttons and tabs in a dialogue use case.
	 */
	#updateModalButtonsBind () {
		let formButtons = $('.formButton');
		formButtons.click(function() {
			let id = $(this).attr("id");
			if (!id)
				return;
			formHandler.onButtonClick(id)
		});
	}

	/**
	 * Bind the submit function to the #cFormInput-submit button and unbind any
	 * previous binding which applied to this button.
	 */
	#updateModalSubmitBind(formHandler, form) {
		let submit = $("#input-" + form.fsId() + "-submit")
		let that = this;
		submit.unbind('click').bind('click', function() {
			that.#submitForm(formHandler, form);
		});
	}

	/**
	 * add the action to exit the form when enter is pressed.
	 */
	#updateModalSpecialKeyBind (formHandler, form) {
		let formInputs = $("input[id^=cFormInput]")
		let that = this;
		formInputs.on("keyup", function(event) {
			let input = event.currentTarget;
			if (event.which === 16)
				that.#shiftDown = false;
			else if (event.which === 17)
				that.#ctrlDown = false;
			else if (event.which === 18)
				that.#altDown = false;
			if ((event.which === 13) && !that.#shiftDown 
					&& !that.#ctrlDown && !that.#altDown
					&& !$(input).hasClass("no-submit-on-enter")) {
				that.#submitForm(formHandler, form);
			}
		});
		formInputs.on("keydown", function(event) {
			if (event.which === 16)
				that.#shiftDown = true;
			else if (event.which === 17)
				that.#ctrlDown = true;
			else if (event.which === 18)
				that.#altDown = true;
		});
	}

	/**
	 * Display an exception
	 */
	showException (e) {
		let stacktrace = e.stack;
		let stacktraceHtml = stacktrace.replace(/\n/g, "<br>");
		this.showHtml(i18n.t("F3yUPA|Oops, unfortunately the ...") + "<br>" + e.toString() + "<br><br>"
				+ i18n.t("GgOUUE|If this occurs again, yo...")
				+ "<br><br><b>" + i18n.t("CGKrQ9|Stacktrace") + ":</b><br>" + stacktraceHtml);
	}
	
	/**
	 * Display a form within the modal
	 */
	showForm (formHandler, form, previousErrors) {
		let formHtml = this.#modal_close + this.#modal_tabs + form.getHtml(previousErrors);
		$(this.#modal_content).html(formHtml);
		// the modal content may now contain a new button, which needs binding.
		this.#updateModalButtonsBind ();
		this.#updateModalSubmitBind(formHandler, form);
		this.#updateModalSpecialKeyBind(formHandler, form);
		this.#updateModalCloseBind();
		this.#modal.style.display = "block";
	}
	
	/**
	 * set tabs for the modal. Up to four tabs are allowed.
	 */
	setTabs (tabs, active) {
		if (!tabs)
			this.#modal_tabs = "";
		else {
			let tabsHtml = "";
			for (let i = 0; i < tabs.length; i++) {
				let template = (i === (active - 1)) ? this.#modal_activeTab : this.#modal_tab;
				tabsHtml += template.replace("{tab}", tabs[i].text).replace("{id}", tabs[i].id).replace("{count}", tabs.length);
			}
			this.#modal_tabs = this.#modal_tabRow.replace("{tabs}", tabsHtml);
		}
	}
	
	/**
	 * hide the modal
	 */
	hide() {
		this.#modal.style.display = "none";
	}

	/**
	 * Display some html content within the modal. No buttons.
	 */
	showHtml (html, goBack) {
		this.#goBack = goBack; // a callback to a function, if previous is clicked.
		$(this.#modal_content).html(this.#modal_close + ((goBack) ? this.#modal_previous : "") + this.#modal_tabs + html);
		// the modal content may now contain a new button, which needs binding.
		this.#updateModalButtonsBind();
		this.#updateModalCloseBind();
		this.#modal.style.display = "block";
	}

	setProgressParameters (url, chunk = 500) {
		this.#progressURL = url;
		this.#chunk = chunk;
	}

	/**
	 * Display some html content which reflects a processing progress and
	 * trigger the next step.
	 */
	showProgress (url = modal.#progressURL, doStep = 1, chunk = modal.#chunk, from = 0) {

		let that = modal; // Instead of "this", because it won't work in the progress display context.
		let urlPlus = url + "&doStep=" + doStep;
		if (chunk > 0)
			urlPlus += "&chunk=" + chunk + "&from=" + from;
		$.get(urlPlus, function(data) {
			let parts = data.split(";", 2);
			// split is different to Java.String.split. The last element is only
			// returned up to the first separator hit within.
			let doneStep = parseInt(parts[0]);
			let completed = parseInt(parts[1]);
			let progressText = data.substring(parts[0].length + parts[1].length + 2);
			if (isNaN(doneStep) || isNaN(completed)) {
				$(that.#modal_content).html(that.#modal_close + data);
				that.#modal.style.display = "block";
			} else if (progressText.startsWith("idle")) {
				that.#modal.style.display = "none";
				window.location.href = url + "&doStep=0";
			} else {
				$(that.#modal_content).html(that.#modal_close + progressText);
				that.#updateModalCloseBind();
				that.#modal.style.display = "block";
				that.#modal.scrollTop = that.#modal.scrollHeight
				doStep = (completed === 0) ? doneStep + 1 : doneStep;
				if (completed === 0) {
					doStep = doneStep + 1;
					from = 0;
				} else {
					doStep = doneStep;
					from += chunk;
				} 
				that.showProgress (url, doStep, chunk, from);
			}
		})
		.fail(function(data) { 
			that.showHtml("<h3>" + I18n.getInstance().t("kffirf|Server error") + "</h3>" + data.responseText);
		});
	}

}

