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
 * A Dialogue "window" which is used for all dialogues superseding the modal. A sort of simple local class copy
 */

class Dialog {

	// reverencing the modal objects
	#dialog;
	#dialog_content = "";
	#responseButtons = { 
		3 : `<div class='w3-col l3' style='text-align:center;'><span class='formButton dialogButton' id='buttonLeft'>{0}</span></div>
			<div class='w3-col l3' style='text-align:center;'><span class='formButton dialogButton' id='buttonCenter'>{1}</span></div>
			<div class='w3-col l3' style='text-align:center;'><span class='formButton dialogButton' id='buttonRight'>{2}</span></div>`,
		2 : `<div class='w3-col l2' style='text-align:center;'><span class='formButton dialogButton' id='buttonLeft'>{0}</span></div>
			<div class='w3-col l2' style='text-align:center;'><span class='formButton dialogButton' id='buttonRight'>{1}</span></div>`,
		1 : `<div class='w3-col l1' style='text-align:center;'><span class='formButton dialogButton' id='buttonLeft'>{0}</span></div>`
	}

	constructor() {
		this.#dialog = document.getElementById('tfyhDialog');
		this.#dialog_content = document.getElementById('tfyhDialog-content');
	}

	/**
	 * bind an event to all modal buttons and tabs in a dialogue use case. Call the eventHandler
	 * with the button's id and the eventProperty provided.
	 */
	#updateDialogButtonsBind (eventHandler, eventProperty) {
		let formButtons = $('.dialogButton');
		let that = this;
		formButtons.click(function() {
			let id = $(this).attr("id");
			if (typeof eventHandler === 'function') 
				eventHandler(id, eventProperty);
			that.#dialog.style.display = "none";
		});
	}

	/**
	 * Display some html content within the modal. No buttons.
	 */
	showHtml (html, buttonTexts, eventHandler, eventProperty) {
		let buttonsHtml = this.#responseButtons[buttonTexts.length];
		for (let i = 0; i < buttonTexts.length; i++)
			buttonsHtml = buttonsHtml.replace("{" + i + "}", buttonTexts[i]);
		$(this.#dialog_content).html("<div class='w3-container'>" + html + "</div>" +
			"<div class='w3-container'><div class='w3-row'  style='line-height:2em'>" + buttonsHtml + "</div></div>");
		this.#updateDialogButtonsBind(eventHandler, eventProperty);
		this.#dialog.style.display = "block";
	}
}

