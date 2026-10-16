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

package org.tfyh.jfx.design

import javafx.scene.paint.Color
import org.tfyh.data.Config

@Suppress("PropertyName") // Suppress warning: Property name should not contain underscores
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

    companion object {
        fun alpha(colorLong: Long) : Double = ((colorLong and 0xFF000000) / 0x1000000) / 256.0
        fun red(colorLong: Long) : Double = ((colorLong and 0x00FF0000) / 0x10000) / 256.0
        fun green(colorLong: Long) : Double = ((colorLong and 0x0000FF00) / 0x100) / 256.0
        fun blue(colorLong: Long) : Double = (colorLong and 0x000000FF) / 256.0
        /**
         * The dilbo default colour scheme. So far changing the colour scheme is not supported.
         */
        fun defaultColorLong(name: String): Long {
            return when (name) {
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

        fun defaultColor(name: String) : Color {
            val defaultColorLong = defaultColorLong(name)
            return Color(red(defaultColorLong),green(defaultColorLong),
                blue(defaultColorLong), alpha(defaultColorLong))
        }

        fun color(colorPath: String): Color {
            val colorItem = Config.getInstance().getItem(".theme.colors.$colorPath")
            if (!colorItem.isValid())
                return defaultColor(colorPath)
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
            return Color(red(colorLong),green(colorLong),
                blue(colorLong), alpha(colorLong))
        }
    }
}

val appLightColorScheme = AppColorScheme(
// body & modal
    color_text_body =  AppColorScheme.color("main.text_body"),
    color_background_body =  AppColorScheme.color("main.background_body"),
    color_background_modal =  AppColorScheme.color("main.background_modal"),
    color_text_h1_h3 =  AppColorScheme.color("main.text_h1_h3"),
    color_text_p_h4_h6_etc =  AppColorScheme.color("main.text_p_h4_h6_etc"),
    color_text_link =  AppColorScheme.color("main.text_link"),
    color_text_link_hover_visited =  AppColorScheme.color("main.text_link_hover_visited"),
// menu
    color_background_menubar =  AppColorScheme.color("menu.background_menubar"),
    color_text_menuitem =  AppColorScheme.color("menu.text_menuitem"),
    color_background_menuitem =  AppColorScheme.color("menu.background_menuitem"),
    color_text_menuitem_hover =  AppColorScheme.color("menu.text_menuitem_hover"),
    color_background_menuitem_hover =  AppColorScheme.color("color_background_menuitem_hover."),
// formbutton
    color_text_form_button =  AppColorScheme.color("formbutton.text_form_button"),
    color_background_form_button =  AppColorScheme.color("formbutton.background_form_button"),
    color_text_form_button_hover =  AppColorScheme.color("formbutton.text_form_button_hover"),
    color_background_form_button_hover =  AppColorScheme.color("formbutton.background_form_button_hover"),
// formInput
    color_text_form_input =  AppColorScheme.color("formInput.text_form_input"),
    color_background_form_input =  AppColorScheme.color("formInput.background_form_input"),
    color_border_form_input =  AppColorScheme.color("formInput.border_form_input"),
    color_border_autocomplete_items =  AppColorScheme.color("formInput.border_autocomplete_items"),
    color_text_form_input_hover =  AppColorScheme.color("formInput.text_form_input_hover"),
    color_background_form_input_hover =  AppColorScheme.color("formInput.background_form_input_hover"),
    color_background_checkbox_radio_checked =  AppColorScheme.color("formInput.background_checkbox_radio_checked"),
    color_all_form_input_invalid =  AppColorScheme.color("formInput.all_form_input_invalid"),
// table
    color_text_table_header =  AppColorScheme.color("table.text_table_header"),
    color_background_table_header =  AppColorScheme.color("table.background_table_header"),
    color_background_table_even_rows =  AppColorScheme.color("table.background_table_even_rows"),
    color_background_table_row_hover =  AppColorScheme.color("table.background_table_row_hover"),
    color_border_table =  AppColorScheme.color("table.border_table")
)
