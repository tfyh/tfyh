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
 * A generic translation helper
 */
class I18n {
		
	// localization
	static #instance = new I18n();
	#i18nURI = "../../i18n/#.lrf";
	#map = [];
	loaded = false;

	static getInstance() { return this.#instance }

	isValidI18nReference(toCheck)
	{
		if ((toCheck.length < 7) || (toCheck.substring(6, 7) !== "|"))
			return false
		else if (!this.loaded)
			return false
		else
			return (typeof this.#map[toCheck.substring(0, 6)] !== 'undefined');
	}

	parseLrf(lrf) {
		let lines = lrf.split(/\n/g);
		let token = false;
		let text = "";
		for (let line of lines) {
			if (line.indexOf("|") !== 6)
				text += "\n" + line;
			else {
				if (token)
					this.#map[token] = text;
				token = line.substring(0, 6);  
				text = line.substring(7);
			}
		}
	}
	
	/**
	 * load the i18n data. This is asynchronous. Use the callback function to continue.
	 */
	loadResource (localeToUse, callback) {
		this.#i18nURI = this.#i18nURI.replace("#", localeToUse)
		// prepare the post-request.
		let getRequest = new XMLHttpRequest();
		getRequest.timeout = 10000; // milliseconds
		let that = this
		// provide the callback for a response received
		getRequest.onload = function() {
			that.parseLrf(getRequest.response);
			that.loaded = true;
			callback();
		};
		// provide the callback for any error
		getRequest.onerror = function() {
			alert("Fatal error loading application texts for internationalization. Texts will be empty");
			that.loaded = true;
			callback();
		};
		// provide the callback for timeout
		getRequest.ontimeout = function() {
			alert("Fatal error loading application texts for internationalization. Texts will be empty");
			that.loaded = true;
			callback();
		};
		// send the post-request
		getRequest.open('GET', this.#i18nURI);
		getRequest.send();
	}

	/**
	 * Call this function to get the proper translation of your texts. Up to 5 non-translatable arguments can be used
	 * within the text.
	 */
	t(key, ...args) {
		if (!key || (key.length === 0))
			return "";
		let token = key.substring(0, 6);
		let text = this.#map[token];
		if (!text) {
			if (!this.loaded)
				text = key + '(...)';
			else
				text = key;
		}
		if (typeof text !== 'string')
			// if the key is a valid identifier of an own property of the Array
			// prototype, it cannot be resolved but will return the Array prototype
			// function or whatever.
			return "[" + key + "]";
		if (! args) return text;
		if (!text)
			alert("Severe i18n error - no text after token");
		if (args.length > 0)
			text = text.replace(/%1/g, args[0]);
		if (args.length > 1)
			text = text.replace(/%2/g, args[1]);
		if (args.length > 2)
			text = text.replace(/%3/g, args[2]);
		if (args.length > 3)
			text = text.replace(/%4/g, args[3]);
		if (args.length > 4)
			text = text.replace(/%5/g, args[4]);
		return text;
	}
}

