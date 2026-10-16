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

package org.tfyh.kmp.design

import androidx.compose.material3.DatePickerColors
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.material3.TimePickerColors
import androidx.compose.runtime.Composable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import org.tfyh.data.Config

@Suppress("PropertyName")
data class AppColorScheme (
    // body & modal
    val color_text_body: Color,
    val color_background_body: Color,
    val color_background_modal: Color,
    val color_text_h1_h3: Color,
    val color_text_p_h4_h6_etc: Color,
    val color_text_link: Color,
    val color_text_link_hover_visited: Color,
    // menu
    val color_background_menubar: Color,
    val color_text_menuitem: Color,
    val color_background_menuitem: Color,
    val color_text_menuitem_hover: Color,
    val color_background_menuitem_hover: Color,
    // formButton
    val color_text_form_button: Color,
    val color_background_form_button: Color,
    val color_text_form_button_hover: Color,
    val color_background_form_button_hover: Color,
    // formInput
    val color_text_form_input: Color,
    val color_background_form_input: Color,
    val color_border_form_input: Color,
    val color_border_autocomplete_items: Color,
    val color_text_form_input_hover: Color,
    val color_background_form_input_hover: Color,
    val color_background_checkbox_radio_checked: Color,
    val color_all_form_input_invalid: Color,
    // table
    val color_text_table_header: Color,
    val color_background_table_header: Color,
    val color_background_table_even_rows: Color,
    val color_background_table_row_hover: Color,
    val color_border_table : Color) {

    @Composable
    fun textFieldColors() = TextFieldDefaults.colors(
        focusedTextColor = color_text_form_input,
        unfocusedTextColor = color_text_form_input,
        disabledTextColor = color_text_body,
        focusedContainerColor = color_background_form_input_hover,
        unfocusedContainerColor = color_background_form_input,
        focusedLabelColor = color_text_form_input,
        unfocusedLabelColor = color_text_form_input,
        focusedIndicatorColor = color_border_form_input,
        unfocusedIndicatorColor = color_border_form_input,
        errorIndicatorColor = color_all_form_input_invalid
    )

    @Composable
    fun textFieldInvalidColors() = TextFieldDefaults.colors(
        focusedTextColor = color_text_form_input,
        unfocusedTextColor = color_text_form_input,
        disabledTextColor = color_text_body,
        focusedContainerColor = color_background_form_input_hover,
        unfocusedContainerColor = color_background_form_input,
        focusedLabelColor = color_all_form_input_invalid,
        unfocusedLabelColor = color_all_form_input_invalid,
        focusedIndicatorColor = color_border_form_input,
        unfocusedIndicatorColor = color_border_form_input,
        errorIndicatorColor = color_all_form_input_invalid
    )

    @Composable
    fun textDisplayColors() = TextFieldDefaults.colors(
        focusedTextColor = color_text_form_input,
        unfocusedTextColor = color_text_form_input,
        disabledTextColor = color_text_form_input,
        focusedContainerColor = Color.Transparent,
        unfocusedContainerColor = Color.Transparent,
        focusedLabelColor = Color.Transparent,
        unfocusedLabelColor = Color.Transparent,
        focusedIndicatorColor = color_border_form_input,
        unfocusedIndicatorColor = color_border_form_input,
        errorIndicatorColor = color_all_form_input_invalid
    )


    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    fun datePickerColors() = DatePickerColors(
        containerColor = color_background_form_input,
        titleContentColor = color_border_form_input,
        headlineContentColor = color_border_form_input,
        weekdayContentColor = color_text_h1_h3,
        subheadContentColor = color_text_h1_h3,
        navigationContentColor = color_text_body,
        yearContentColor = color_text_body,
        disabledYearContentColor = color_text_p_h4_h6_etc,
        currentYearContentColor = color_text_body,
        selectedYearContentColor = color_text_form_button,
        disabledSelectedYearContentColor = color_text_p_h4_h6_etc,
        selectedYearContainerColor = color_background_form_button,
        disabledSelectedYearContainerColor = color_background_form_input,
        dayContentColor = color_text_body,
        disabledDayContentColor = color_text_p_h4_h6_etc,
        selectedDayContentColor = color_text_form_button,
        disabledSelectedDayContentColor = color_text_p_h4_h6_etc,
        selectedDayContainerColor = color_background_form_button,
        disabledSelectedDayContainerColor = color_background_form_input,
        todayContentColor = color_text_body,
        todayDateBorderColor = color_border_table,
        dayInSelectionRangeContainerColor = color_background_form_input,
        dayInSelectionRangeContentColor = color_text_p_h4_h6_etc,
        dividerColor = color_border_form_input,
        dateTextFieldColors = textFieldColors()
    )

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    fun timePickerColors() = TimePickerColors(
        clockDialColor = color_background_form_input,
        selectorColor = color_background_form_button,
        containerColor = color_background_modal,
        periodSelectorBorderColor = color_border_table,
        clockDialSelectedContentColor = color_text_h1_h3,
        clockDialUnselectedContentColor = color_text_p_h4_h6_etc,
        periodSelectorSelectedContainerColor = color_background_form_input,
        periodSelectorUnselectedContainerColor = color_background_modal,
        periodSelectorSelectedContentColor = color_text_h1_h3,
        periodSelectorUnselectedContentColor = color_text_p_h4_h6_etc,
        timeSelectorSelectedContainerColor = color_background_form_input,
        timeSelectorUnselectedContainerColor = color_background_modal,
        timeSelectorSelectedContentColor = color_text_h1_h3,
        timeSelectorUnselectedContentColor = color_text_p_h4_h6_etc,
    )
}

val LocalAppColorScheme = staticCompositionLocalOf {
    AppColorScheme(
        // body & modal
        color_text_body = Color.Unspecified,
        color_background_body = Color.Unspecified,
        color_background_modal = Color.Unspecified,
        color_text_h1_h3 = Color.Unspecified,
        color_text_p_h4_h6_etc = Color.Unspecified,
        color_text_link = Color.Unspecified,
        color_text_link_hover_visited = Color.Unspecified,
        // menu
        color_background_menubar = Color.Unspecified,
        color_text_menuitem = Color.Unspecified,
        color_background_menuitem = Color.Unspecified,
        color_text_menuitem_hover = Color.Unspecified,
        color_background_menuitem_hover = Color.Unspecified,
        // formButton
        color_text_form_button = Color.Unspecified,
        color_background_form_button = Color.Unspecified,
        color_text_form_button_hover = Color.Unspecified,
        color_background_form_button_hover = Color.Unspecified,
        // formInput
        color_text_form_input = Color.Unspecified,
        color_background_form_input = Color.Unspecified,
        color_border_form_input = Color.Unspecified,
        color_border_autocomplete_items = Color.Unspecified,
        color_text_form_input_hover = Color.Unspecified,
        color_background_form_input_hover = Color.Unspecified,
        color_background_checkbox_radio_checked = Color.Unspecified,
        color_all_form_input_invalid = Color.Unspecified,
        // table
        color_text_table_header = Color.Unspecified,
        color_background_table_header = Color.Unspecified,
        color_background_table_even_rows = Color.Unspecified,
        color_background_table_row_hover = Color.Unspecified,
        color_border_table = Color.Unspecified
    )
}

/**
 * The dilbo default colour scheme. So far changing the colour scheme is not supported.
 */
fun defaultColor(name: String): Long {
    return when(name) {
// #main=
        "color_text_body" -> 0xff1c1d1f
        "color_background_body" -> 0xfff0f1f4
        "color_background_modal" -> 0xfff6f6f8
        "color_text_h1_h3" -> 0xff033161
        "color_text_p_h4_h6_etc" -> 0xff1c1d1f
        "color_text_link" -> 0xff033161
        "color_text_link_hover_visited" -> 0xff0063a0
// #menu=
        "color_background_menubar" -> 0xffffffff
        "color_text_menuitem" -> 0xff033161
        "color_background_menuitem" -> 0xffffffff
        "color_text_menuitem_hover" -> 0xffffffff
        "color_background_menuitem_hover" -> 0xff3a3b3f
// #formbutton=
        "color_text_form_button" -> 0xfff0f1f4
        "color_background_form_button" -> 0xff033161
        "color_text_form_button_hover" -> 0xfff0f1f4
        "color_background_form_button_hover" -> 0xff3a3b3f
// #formInput=
        "color_text_form_input" -> 0xff1c1d1f
        "color_background_form_input" -> 0xffffffff
        "color_border_form_input" -> 0xff2d7ebe
        "color_border_autocomplete_items" -> 0xff2d7ebe
        "color_text_form_input_hover" -> 0xff1c1d1f
        "color_background_form_input_hover" -> 0xffcce0f1
        "color_background_checkbox_radio_checked" -> 0xff033161
// #table=
        "color_text_table_header" -> 0xffeeeeee
        "color_background_table_header" -> 0xff033161
        "color_background_table_even_rows" -> 0xfff6f6f8
        "color_background_table_row_hover" -> 0xffffffff
        "color_border_table" -> 0xffcce0f1
        else -> 0xff008080
    }
}

fun color(colorPath: String): Color {
    val colorItem = Config.getInstance().getItem(".theme.colors.$colorPath")
    if (!colorItem.isValid())
        return Color(defaultColor(colorPath))
    val colorLong = try {
        var colorString = colorItem.valueStr().substring(1)
        if (colorString.length == 3)
            colorString = colorString.substring(0, 1) + colorString.substring(0, 1) +
                    colorString.substring(1, 2) + colorString.substring(1, 2) +
                    colorString.substring(2, 3) + colorString.substring(2, 3)
        "ff$colorString".toLong(radix = 16)
    } catch (_: Exception) {
        0xffff0000
    }
    return Color(colorLong)
}

// This is currently the very same colour scheme than for the light mode.
val appDarkColorScheme = AppColorScheme(
// body & modal
    color_text_body = color("main.text_body"),
    color_background_body = color("main.background_body"),
    color_background_modal = color("main.background_modal"),
    color_text_h1_h3 = color("main.text_h1_h3"),
    color_text_p_h4_h6_etc = color("main.text_p_h4_h6_etc"),
    color_text_link = color("main.text_link"),
    color_text_link_hover_visited = color("main.text_link_hover_visited"),
// menu
    color_background_menubar = color("menu.background_menubar"),
    color_text_menuitem = color("menu.text_menuitem"),
    color_background_menuitem = color("menu.background_menuitem"),
    color_text_menuitem_hover = color("menu.text_menuitem_hover"),
    color_background_menuitem_hover = color("color_background_menuitem_hover"),
// formbutton
    color_text_form_button = color("formbutton.text_form_button"),
    color_background_form_button = color("formbutton.background_form_button"),
    color_text_form_button_hover = color("formbutton.text_form_button_hover"),
    color_background_form_button_hover = color("formbutton.background_form_button_hover"),
// formInput
    color_text_form_input = color("formInput.text_form_input"),
    color_background_form_input = color("formInput.background_form_input"),
    color_border_form_input = color("formInput.border_form_input"),
    color_border_autocomplete_items = color("formInput.border_autocomplete_items"),
    color_text_form_input_hover = color("formInput.text_form_input_hover"),
    color_background_form_input_hover = color("formInput.background_form_input_hover"),
    color_background_checkbox_radio_checked = color("formInput.background_checkbox_radio_checked"),
    color_all_form_input_invalid = color("formInput.all_form_input_invalid"),
// table
    color_text_table_header = color("table.text_table_header"),
    color_background_table_header = color("table.background_table_header"),
    color_background_table_even_rows = color("table.background_table_even_rows"),
    color_background_table_row_hover = color("table.background_table_row_hover"),
    color_border_table = color("table.border_table")
)

val appLightColorScheme = AppColorScheme(
// body & modal
    color_text_body = color("main.text_body"),
    color_background_body = color("main.background_body"),
    color_background_modal = color("main.background_modal"),
    color_text_h1_h3 = color("main.text_h1_h3"),
    color_text_p_h4_h6_etc = color("main.text_p_h4_h6_etc"),
    color_text_link = color("main.text_link"),
    color_text_link_hover_visited = color("main.text_link_hover_visited"),
// menu
    color_background_menubar = color("menu.background_menubar"),
    color_text_menuitem = color("menu.text_menuitem"),
    color_background_menuitem = color("menu.background_menuitem"),
    color_text_menuitem_hover = color("menu.text_menuitem_hover"),
    color_background_menuitem_hover = color("color_background_menuitem_hover."),
// formbutton
    color_text_form_button = color("formbutton.text_form_button"),
    color_background_form_button = color("formbutton.background_form_button"),
    color_text_form_button_hover = color("formbutton.text_form_button_hover"),
    color_background_form_button_hover = color("formbutton.background_form_button_hover"),
// formInput
    color_text_form_input = color("formInput.text_form_input"),
    color_background_form_input = color("formInput.background_form_input"),
    color_border_form_input = color("formInput.border_form_input"),
    color_border_autocomplete_items = color("formInput.border_autocomplete_items"),
    color_text_form_input_hover = color("formInput.text_form_input_hover"),
    color_background_form_input_hover = color("formInput.background_form_input_hover"),
    color_background_checkbox_radio_checked = color("formInput.background_checkbox_radio_checked"),
    color_all_form_input_invalid = color("formInput.all_form_input_invalid"),
// table
    color_text_table_header = color("table.text_table_header"),
    color_background_table_header = color("table.background_table_header"),
    color_background_table_even_rows = color("table.background_table_even_rows"),
    color_background_table_row_hover = color("table.background_table_row_hover"),
    color_border_table = color("table.border_table")
)



