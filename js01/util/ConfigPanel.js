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

class ConfigPanel {

	#treePosition = 0;
	#nodeTemplateHTML =
		`<div class='w3-row w3-show' id="item-{path}">
<div class="cfg-nav" id="appcfg-nav">
	<span class="cfg-button" style="padding-left:{level}em;" id="tfyhCfgPanel_{action}_{path}">{caret}</span>
</div>
<div class="cfg-nav">
	<span class="cfg-item" id="tfyhCfgPanel_show_{path}"><b>{label}</b> <small>{value}</small></span>
</div>
<div class="cfg-nav" id="appcfg-nav">
	{add}
	{delete}
	{edit}
	{moveUp}
	{moveDown}
</div>
</div>
`;

	#config = null;
	#modal = null;
	#top = null;
	#mode = "show";
	#lastAction = ""
	#lastPath = ""

    /**
	 * Construct a configuration item. It must always have a parent, except the
	 * root. For the roor set parent = null.
	 */
    constructor (config, modal, mode, top)
    {
    	this.#config = config;
    	this.#modal = modal;
    	this.#top = config.getItem(top);
    	this.#mode = mode;
    }

	// control any action on a specific item triggered by the UI
	#changeItem(action, path) {
		this.#lastAction = action
		this.#lastPath = path
		let item = config.getItem(path);
		if (action.localeCompare("expand") === 0) {
			if (item.state > 0)
				item.state = 2;
		} else if (action.localeCompare("collapse") === 0) {
			if (item.state > 0)
				item.state = 1;
		} else if (action.localeCompare("add") === 0) {
			formHandler.createItem_do(item);
		} else if (action.localeCompare("edit") === 0) {
			formHandler.editItem_do(item);
		} else if (action.localeCompare("delete") === 0) {
			let parent = item.parent();
			if (parent)
				this.postModify(3, item.getPath(), "", this.onPostCallback);
		} else if ((action.localeCompare("moveUp") === 0) || (action.localeCompare("moveDown") === 0)) {
			let parent = item.parent();
			let isMoveUp = (action.localeCompare("moveUp") === 0);
			if (parent)
				this.postModify((isMoveUp) ? 4 : 5, item.getPath(), "", this.onPostCallback);
		} else if (action.localeCompare("show") === 0) {
			modal.showHtml(item.toHtmlTable() + item.childrenToTableHtml());
		}
	}

	onPostCallback(success, done) {
		let responseParts = done.split(";");
		if (success) // success = false at this point means that the HTTP succeeded
			success = ((parseInt(responseParts[0]) < 40) && (parseInt(responseParts[0]) > 0));
		if (success) { // success = false at this point means that the requested transaction succeeded
			// complete action
			let action = this.#lastAction
			let path = this.#lastPath
			let item = config.getItem(path);
			if (action === "delete") {
				item.parent().removeChild(item)
				item.destroy()
				cfgPanel.refresh()
			} else if ((action === "moveUp") || (action === "moveDown")) {
				item.parent().moveChild(item, ((action === "moveUp")) ? -1 : 1);
				cfgPanel.refresh()
			}
		}
		else {
			console.log("Configuration modification error " + responseParts[0] + " " + responseParts[1]);
			let formError = "<h4>" + i18n.t("1Uo8a4|Error") + "</h4><p>" +
				i18n.t("WlaoZG|the server responded wit...")  + ": " + responseParts[0] + " " + responseParts[1] + "</p>"
			modal.showHtml(formError)
		}
	}

	// will bind all panel events triggered for .cfg-item, .cfg-button with a
	// providerId of "tfyhCfgPanel"
	#bindEvents () {
		// for debugging: do not inline statement.
		let cfgElements = $('.cfg-item, .cfg-button');
		cfgElements.unbind();
		let that = this;
		cfgElements
				.click(function(e) {
					// for debugging: do not inline statement.
					let thisElement = $(this);
					let id = thisElement.attr("id");
					if (!id)
						return;
					let source = id.substring(0, id.indexOf("_"));
					if (source !== 'tfyhCfgPanel')
						return;
					let action = id.substring(source.length + 1, id.indexOf("_", source.length + 1))
					let path = id.substring(source.length + action.length + 2)
					if (action === 'refresh') {
						LocalCache.getInstance().clear()
						window.location.reload()
					}
					let item = that.#config.getItem(path);
					if (!item)
						return;
					if (e.shiftKey) {
						// TODO: no special functions for shift
					} else if (e.ctrlKey) {
						// TODO: no special functions for ctrl
					} else {
						that.#changeItem(action, path)
						that.refresh();
					}
				});
	}
	
	/**
	 * refresh the configuration manager panel.
	 */
	refresh() {
		let headline = (this.#mode === "edit") ?
			i18n.t("QES6P2|Configuration editor") : i18n.t("2S8Lmx|Configuration browser")
		$('#tfyhCfg-header').html("<h4>" + headline + "<h4>");
		let itemsTree = this.#getConfigBranchHtml(this.#top, "", -1);
		$('#tfyhCfg-branch').html(itemsTree);
		this.#bindEvents();
	}

	// refresh the configuration manager panel.
	displayError(errorMessageHTML) {
		this.#modal.showHtml(errorMessageHTML); 	
	}

	/* ----------------------------------------------------------------- */
	/* ------ MODIFY TO SERVER, ONLY JAVASCRIPT, NO PHP ---------------- */
	/* ----------------------------------------------------------------- */

	/**
	 * post a modification transaction to the server. mode is 1, 2, 3, 4, 5 for
	 * insert, update, delete, moveUp, moveDown. The csvDefinition may contain a
	 * single item or a complex branch. It is ignored for move and delete. After
	 * completion callback is called with the response text as an argument. Use the
	 * first three digits to get the result code. Use callback and
	 * cachedItem to return after execution.
	 */
	postModify(mode, itemPath, csvData, callback) {
		// Assign handlers immediately after making the request
		// and remember the jqxhr object for this request
		let that = this;
		let postData = {
			mode : mode,
			path : itemPath,
			csv : csvData,
		}
		console.log("Configuration modify post: mode = " + mode + ", itemPath = " + itemPath);
		$.post( "../../tfyh/forms/jsPost.php", postData)
			.done(function(done) {
				that.#onPostDone(done, callback);
			})
			.fail(function(fail) {
				that.#onPostFail(fail, callback);
			});
	}

	/**
	 * called after successful completion.
	 */
	#onPostDone(done, callback) { if (typeof callback == 'function') callback(true, done); }

	/**
	 * called after failed completion.
	 */
	#onPostFail(fail, callback) {
		console.log("Configuration modify failed. Error " + fail.responseText);
		if (typeof callback == 'function')
			callback(false, fail.responseText);
	}

	// get the HTML code for a single item
	#getBranchItemHTML (item, levelOffset, childPosition) {

		const iconAdd = "<span class='material-icons'>&#xe145;</span>";
		const iconEdit = "<span class='material-icons'>&#xe3c9;</span>";
		const iconDelete = "<span class='material-icons'>&#xe872;</span>";
		const iconMoveUp = "<span class='material-icons'>&#xe5d8;</span>";
		const iconMoveDown = "<span class='material-icons'>&#xe5db;</span>";

		// prepare function
		if ((item.state === 0) && (item.getChildren().length > 0))
			item.state = 1;
		let edit = this.#mode === "edit"
		let inspect = this.#mode === "inspect"
		const caret = { 0 : "▫", 1 : "▸", 2 : "▾" };
		const action = { 0 : "show", 1 : "expand", 2 : "collapse" };
		let html = this.#nodeTemplateHTML;
		let path = item.getPath()
		let itemTopBranch = path.split(".")[1]

		// prepare all values and conditions
		// thisAddable is true if items can be added to this as children
		let forbiddenBranches = [ "access", "framework", "tables", "templates" ]
		let branchIsEditable = (edit || inspect) && (forbiddenBranches.indexOf(itemTopBranch) < 0) && (path !== ".")
		if (edit && !branchIsEditable && (item !== config.rootItem))
			return ""
		let itemIsEditable = (item.getChildren().length > 0)
		let thisAddable = branchIsEditable && ((item.nodeAddableType().length > 0));
		// thisIsParentAddable is true if this item is addable to the parent - and therefore movable and deletable
		let thisIsParentAddable = branchIsEditable && (item.parent().isOfAddableType(item))

		// add icons for the possible actions: add, edit, delete ...
		let editOption = (branchIsEditable && itemIsEditable) ? '<span class="cfg-button" id="tfyhCfgPanel_edit_' + path + '">' + iconEdit + '</span>' : "";
		let addOption = (thisAddable) ? '<span class="cfg-button" id="tfyhCfgPanel_add_' + path + '">' + iconAdd + '</span>' : "";
		let deleteOption = (thisIsParentAddable) ? '<span class="cfg-button" id="tfyhCfgPanel_delete_' + path + '">' + iconDelete + '</span>' : "";
		html = html.replace(/\{path}/g, path).replace(/\{edit}/g, editOption)
			.replace(/\{add}/g, addOption).replace(/\{delete}/g, deleteOption)
		// ... move up and down
		let moveUpOption = (thisIsParentAddable && (childPosition > 0) && (this.#mode !== "show"))
			? '<span class="cfg-button" id="tfyhCfgPanel_moveUp_' + path + '">' + iconMoveUp + '</span>' : "";
		let moveDownOption = (thisIsParentAddable && (childPosition < (item.parent().getChildren().length - 1)) && (this.#mode !== "show"))
			? '<span class="cfg-button" id="tfyhCfgPanel_moveDown_' + path + '">' + iconMoveDown + '</span>' : "";
		html = html.replace(/\{moveUp}/g, moveUpOption).replace(/\{moveDown}/g, moveDownOption)

		// add value and position information
		let valueToShow = item.valueStr()
		if (item.inputType() === "select") {
			if (item.valueReference().startsWith(".")) {
				let selected = config.getItem(item.valueReference() + "." + valueToShow)
				if (selected.isValid()) valueToShow = selected.label()
			} else {
				// TODO
			}
		}
		html = html.replace(/\{level}/g, "" + (item.getLevel() - levelOffset))
			.replace(/\{label}/g, item.label())
		html = html.replace(/\{value}/g, Formatter.escapeHtml(valueToShow))
		html = html.replace(/\{caret}/g, caret[item.state])
			.replace(/\{action}/g, action[item.state])
		return html;
	}

	/**
	 * get a branch as HTML for management.
	 */
	#getConfigBranchHtml (item = null, html, levelOffset = -1, childPosition = 0) {
		if (item == null) item = config.rootItem;
		if (item === config.rootItem) {
			// load all branches
			for (let topBranchName of Config.allSettingsFiles)
				config.loadBranch(topBranchName)
			Item.sortTopLevel()
		}
		if (levelOffset < 0) {
			this.#treePosition = 0;
			item.state = (item.getChildren().length > 0) ? 2 : 0;
			levelOffset = item.getLevel();
		} else
			this.#treePosition++;
		html += this.#getBranchItemHTML(item, levelOffset, childPosition);
		item.position = this.#treePosition;
		if (item.state === 2) {
			item.sortChildren(99, true)
			for (let i in item.getChildren())
				html = this.#getConfigBranchHtml(item.getChildren()[i], html, levelOffset, parseInt(i));
		}
		return html;
	}

}
