package org.tfyh.jfx.ui

import javafx.event.EventHandler
import javafx.geometry.Side
import javafx.scene.control.ContextMenu
import javafx.scene.control.CustomMenuItem
import javafx.scene.control.Label
import javafx.scene.control.TextField
import javafx.scene.input.KeyCode
import javafx.scene.text.Text
import javafx.scene.text.TextFlow
import org.tfyh.jfx.design.AppColours
import org.tfyh.jfx.design.AppFonts
import org.tfyh.data.Record
import java.util.*
import java.util.stream.Collectors
import kotlin.math.min


// Source - https://stackoverflow.com/a/40369435; modified
// Posted by Ruslan  Gabbazov, modified by community. See post 'Timeline' for change history
// Retrieved 2026-10-26, Licence - CC BY-SA 3.0
class AutocompletionTextField(val options: MutableSet<String>, val formField: FormField) : TextField() {

    val entries: SortedSet<String?> = TreeSet()
    // popup UI
    private val entriesPopup: ContextMenu = ContextMenu().apply {
        font = AppFonts.p
    }
    private val menuItems: MutableList<CustomMenuItem?> = LinkedList()

    fun validateVersion() {
        formField.validate() // the validation resolves names into uuids
        // the validated uuid is a single value (type == uuid) or an ArrayList with a single entry
        // (type == uuid_list, split field), or an ArrayList with a multiple entries (type == uuid_list,
        // list field). Create an immutable List in all cases
        val versionValidity = Record.combinedVersionValidity(formField.versionValidity.values)
        border = AppColours.borderPeriodCheck(versionValidity)
    }

    init {
        entries.addAll(options)
        setListener()
        // exit with first option selected on TAB or ENTER key
        onKeyPressed = EventHandler { keyEvent ->
            if ((keyEvent?.code == KeyCode.TAB) || (keyEvent?.code == KeyCode.ENTER)) {
                val filteredEntries = filterPopupEntries(text)
                if (filteredEntries.isNotEmpty()) text = filteredEntries.first()
                text = text.trim()
                hidePopup()
                validateVersion()
                keyEvent.consume()
            }
            else if ((keyEvent?.code == KeyCode.DOWN) || (keyEvent?.code == KeyCode.UP)) {
                hidePopup()
                showPopup()
            }
        }
        formField.initialiseTextInputControl(this)
        // border = AppColours.borderPeriod(periodCheckColours["uuid-not-checked"] ?: "220000")
    }

    private fun showPopup() {
        if (!entriesPopup.isShowing) { //optional
            try {
                entriesPopup.show(
                    this@AutocompletionTextField,
                    Side.BOTTOM, 0.0, 0.0
                ) //position of popup
                entriesPopup.skin.node.lookup(".menu-item").requestFocus()
            } catch (e: Exception) {
                println(e.message)
            }
        }
    }

    private fun hidePopup() {
        if (entriesPopup.isShowing) {
            entriesPopup.hide()
            formField.notifyOnFocusLost()
        }
    }

    /**
     * The popup filter function.
     */
    private fun popupFilterFunction(e: String?, enteredText: String): Boolean {
        return e?.lowercase(Locale.getDefault())?.startsWith(enteredText.lowercase(Locale.getDefault())) ?: false
    }

    /**
     * Filter all entry options by using the enteredText
     */
    private fun filterPopupEntries(enteredText: String): MutableList<String> {
        return entries.stream()
            .filter { e: String? -> popupFilterFunction(e, enteredText) }
            .collect(Collectors.toList())
    }

    /**
     * "Suggestion" specific listeners
     */
    private fun setListener() {
        // Add "suggestions" by changing text
        textProperty().addListener { _, _, _ ->
            val enteredText: String? = text
            // always hide suggestion if nothing has been entered
            if (enteredText.isNullOrEmpty()) {
                hidePopup()
            } else {
                // filter all possible suggestions depends on "Text", case-insensitive
                val filteredEntries = filterPopupEntries(enteredText)
                // some suggestions are found
                if (filteredEntries.isNotEmpty()) {
                    // build popup - list of "CustomMenuItem"
                    populatePopup(filteredEntries, enteredText)
                    showPopup()
                    // no suggestions -> hide
                } else {
                    hidePopup()
                }
            }
        }

        //Hide always by focus-in (optional) and out
        focusedProperty().addListener { _, _, focused ->
            hidePopup()
            if (!focused) validateVersion()
        }
    }


    /**
     * Populate the entry set with the given search results. Display is limited to 10 entries, for performance.
     *
     * @param searchResult The set of matching strings.
     */
    private fun populatePopup(searchResult: MutableList<String>, searchRequest: String?) {
        //List of "suggestions"
        menuItems.clear()
        //List size - 10 or founded suggestions count
        val maxEntries = 10
        val count = min(searchResult.size, maxEntries)
        //Build list as set of labels
        for (i in 0..<count) {
            val result = searchResult[i]
            //label with graphic (text flow) to highlight founded subtext in suggestions
            val entryLabel = Label()
            entryLabel.graphic = buildTextFlow(result, searchRequest ?: "")
            entryLabel.prefHeight = 16.0 //don't sure why it's changed with "graphic"
            val item = CustomMenuItem(entryLabel, true)
            item.text = result // needed to get the text in the selection event
            menuItems.add(item)

            //if any suggestion is select set it into text and close popup
            item.onAction = EventHandler {
                text = result
                positionCaret(result.length)
                hidePopup()
                validateVersion()
            }
        }

        //"Refresh" context menu
        entriesPopup.items.clear()
        entriesPopup.items.addAll(menuItems)
    }

    /**
     * Build TextFlow with selected text. Return "case" dependent.
     *
     * @param text - string with text
     * @param filter - string to select in text
     * @return - TextFlow
     */
    fun buildTextFlow(text: String, filter: String): TextFlow {
        val filterIndex = text.lowercase(Locale.getDefault()).indexOf(filter.lowercase(Locale.getDefault()))
        val textBefore = Text(text.substring(0, filterIndex)).apply { font = AppFonts.p}
        val textAfter = Text(text.substring(filterIndex + filter.length)).apply { font = AppFonts.p }
        val textFilter = Text(
            text.substring(
                filterIndex,
                filterIndex + filter.length
            )
        ) //instead of "filter" to keep all "case-sensitive"
        textFilter.fill = AppColours.text_h1_h3
        textFilter.font = AppFonts.i
        return TextFlow(textBefore, textFilter, textAfter)
    }

}

