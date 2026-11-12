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

import javafx.geometry.Insets
import javafx.scene.layout.Background
import javafx.scene.layout.BackgroundFill
import javafx.scene.layout.Border
import javafx.scene.layout.BorderStroke
import javafx.scene.layout.BorderStrokeStyle
import javafx.scene.layout.BorderWidths
import javafx.scene.layout.CornerRadii
import javafx.scene.paint.Color
import javafx.scene.paint.Paint
import org.tfyh.data.Config
import org.tfyh.data.Record

object AppColours {
    
    // body & modal
    var text_body: Color = defaultColor("color_text_body")
    var background_body: Color = defaultColor("color_background_body")
    var background_modal: Color = defaultColor("color_background_modal")
    var text_h1_h3: Color = defaultColor("color_text_h1_h3")
    var text_p_h4_h6_etc: Color = defaultColor("color_text_p_h4_h6_etc")
    var text_link: Color = defaultColor("color_text_link")
    var text_link_hover_visited: Color = defaultColor("color_text_link_hover_visited")
    // menu
    var background_menubar: Color = defaultColor("color_background_menubar")
    var text_menuitem: Color = defaultColor("color_text_menuitem")
    var background_menuitem: Color = defaultColor("color_background_menuitem")
    var text_menuitem_hover: Color = defaultColor("color_text_menuitem_hover")
    var background_menuitem_hover: Color = defaultColor("color_background_menuitem_hover")
    // formButton
    var text_form_button: Color = defaultColor("color_text_form_button")
    var background_form_button: Color = defaultColor("color_background_form_button")
    var text_form_button_hover: Color = defaultColor("color_text_form_button_hover")
    var background_form_button_hover: Color = defaultColor("color_background_form_button_hover")
    // formInput
    var text_form_input: Color = defaultColor("color_text_form_input")
    var background_form_input: Color = defaultColor("color_background_form_input")
    var border_form_input: Color = defaultColor("color_border_form_input")
    var border_autocomplete_items: Color = defaultColor("color_border_autocomplete_items")
    var text_form_input_hover: Color = defaultColor("color_text_form_input_hover")
    var background_form_input_hover: Color = defaultColor("color_background_form_input_hover")
    var background_checkbox_radio_checked: Color = defaultColor("color_background_checkbox_radio_checked")
    var all_form_input_invalid: Color = defaultColor("color_all_form_input_invalid")
    // table
    var text_table_header: Color = defaultColor("color_text_table_header")
    var background_table_header: Color = defaultColor("color_background_table_header")
    var background_table_even_rows: Color = defaultColor("color_background_table_even_rows")
    var background_table_row_hover: Color = defaultColor("color_background_table_row_hover")
    var border_table : Color = defaultColor("color_border_table")

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
            "color_all_form_input_invalid" -> 0xffa00000
// #table=
            "color_text_table_header" -> 0xffeeeeee
            "color_background_table_header" -> 0xff033161
            "color_background_table_even_rows" -> 0xfff6f6f8
            "color_background_table_row_hover" -> 0xffffffff
            "color_border_table" -> 0xffcce0f1
            else -> 0xff008080
        }
    }

    fun textBorderStroke(): BorderStroke = BorderStroke(Paint.valueOf(AppColours.hex(AppColours.border_form_input)),
        BorderStrokeStyle(null, null, null, 10.0, 0.0, null),
        CornerRadii.EMPTY,
        BorderWidths(0.0,0.0,2.0, 0.0))

    fun textNoBorderStroke(): BorderStroke = BorderStroke(Paint.valueOf(AppColours.hex(AppColours.border_form_input)),
        BorderStrokeStyle(null, null, null, 10.0, 0.0, null),
        CornerRadii.EMPTY,
        BorderWidths(0.0,0.0,0.0, 0.0))

    /**
     * A transparent border of 0px stroke width all around
     */
    fun borderNone(): Border {
        return borderSolid(0.0, "00000000")
    }

    /**
     * A border to indicate the period check result of a autocompletion field
     */
    fun borderPeriodCheck(versionCheck: Record.VersionValidity): Border {
        val paintNone = Paint.valueOf("000000")
        val paintBottom = Paint.valueOf(hex(border_form_input))
        val paintRight = Paint.valueOf(versionCheck.colour)
        val borderStroke = BorderStroke(paintNone, paintRight, paintBottom, paintNone,
            BorderStrokeStyle.NONE, BorderStrokeStyle.SOLID, BorderStrokeStyle.SOLID, BorderStrokeStyle.NONE,
            CornerRadii.EMPTY, BorderWidths(0.0, 12.0, 2.0, 0.0), Insets.EMPTY)
        return Border(borderStroke)
    }

    /**
     * A transparent border of width px stroke width all around
     */
    fun borderSolid(width: Double, colourString: String): Border {
        val borderStroke = BorderStroke(Paint.valueOf(colourString),
            BorderStrokeStyle(null, null, null, 10.0, 0.0, null),
            CornerRadii.EMPTY,
            BorderWidths(width, width, width, width))
        return Border(borderStroke)
    }

    fun borderTest(): Border {
        val borderStroke = BorderStroke(Paint.valueOf(hex(border_form_input)),
            BorderStrokeStyle(null, null, null, 10.0, 0.0, null),
            CornerRadii.EMPTY,
            BorderWidths(1.0,1.0,1.0, 1.0))
        return Border(borderStroke)
    }

    fun textBackgroundFill(): BackgroundFill = BackgroundFill(
        Paint.valueOf(AppColours.hex(AppColours.background_form_input)),
        CornerRadii(3.0), null)

    fun backgroundTest(): Background {
        return Background(BackgroundFill(Paint.valueOf(hex(background_form_input)), null, null))
    }

    fun backgroundTransparent(): Background {
        return Background(BackgroundFill(Paint.valueOf("00000000"), null, null))
    }

    fun background(colour: Color): Background {
        return Background(BackgroundFill(Paint.valueOf(hex(colour)), null, null))
    }

    fun defaultColor(name: String) : Color {
        val defaultColorLong = defaultColorLong(name)
        return Color(red(defaultColorLong),green(defaultColorLong),
            blue(defaultColorLong), alpha(defaultColorLong))
    }

    fun getColour(colorPath: String): Color {
        val config = Config.getInstance()
        val colorItem = config.getItem(".theme.colors.$colorPath")
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

    fun two(hex:String) = if (hex.length == 1) "0$hex" else hex
    /**
     * get the hex String for a colour, without prefixes like 0x or #, eg FFFF0000 for full red.
     */
    fun hex(colour: Color, withOpacity: Boolean = false) : String {
        val red = two((colour.red * 255.9999).toInt().toString(16))
        val green = two((colour.green * 255.9999).toInt().toString(16))
        val blue = two((colour.blue * 255.9999).toInt().toString(16))
        val opacity = if (withOpacity) two((colour.opacity * 255.9999).toInt().toString(16)) else ""
        return opacity + red + green + blue
    }

    fun load() {
        text_body = getColour("color_text_body")
        background_body = getColour("color_background_body")
        background_modal = getColour("color_background_modal")
        text_h1_h3 = getColour("color_text_h1_h3")
        text_p_h4_h6_etc = getColour("color_text_p_h4_h6_etc")
        text_link = getColour("color_text_link")
        text_link_hover_visited = getColour("color_text_link_hover_visited")
// #menu=
        background_menubar = getColour("color_background_menubar")
        text_menuitem = getColour("color_text_menuitem")
        background_menuitem = getColour("color_background_menuitem")
        text_menuitem_hover = getColour("color_text_menuitem_hover")
        background_menuitem_hover = getColour("color_background_menuitem_hover")
// #formbutton=
        text_form_button = getColour("color_text_form_button")
        background_form_button = getColour("color_background_form_button")
        text_form_button_hover = getColour("color_text_form_button_hover")
        background_form_button_hover = getColour("color_background_form_button_hover")
// #formInput=
        text_form_input = getColour("color_text_form_input")
        background_form_input = getColour("color_background_form_input")
        border_form_input = getColour("color_border_form_input")
        border_autocomplete_items = getColour("color_border_autocomplete_items")
        text_form_input_hover = getColour("color_text_form_input_hover")
        background_form_input_hover = getColour("color_background_form_input_hover")
        background_checkbox_radio_checked = getColour("color_background_checkbox_radio_checked")
        all_form_input_invalid = getColour("color_all_form_input_invalid")
// #table=
        text_table_header = getColour("color_text_table_header")
        background_table_header = getColour("color_background_table_header")
        background_table_even_rows = getColour("color_background_table_even_rows")
        background_table_row_hover = getColour("color_background_table_row_hover")
        border_table = getColour("color_border_table")
    }

}

