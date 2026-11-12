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

package org.tfyh.jfx.ui

import javafx.scene.Cursor
import javafx.scene.control.Button
import javafx.scene.effect.DropShadow
import javafx.scene.input.KeyCode
import javafx.scene.layout.*
import javafx.scene.paint.Color
import javafx.scene.paint.Paint
import org.tfyh.jfx.design.AppColours
import org.tfyh.jfx.design.AppFonts

fun submitButton(
    text: String = "submit button",
    onSubmit: () -> Unit = {}
): Pane {
    val submitButton = Button(text).apply {
        font = AppFonts.btn
        background = Background(BackgroundFill(Paint.valueOf(AppColours.hex(AppColours.background_form_button)),
            CornerRadii(3.0), null))
        textFill = Paint.valueOf(AppColours.hex(AppColours.text_form_button))
        effect = DropShadow(12.0, Color(0.0, 0.0, 0.0, 0.3))
        cursor = Cursor.HAND
        setOnMouseEntered {
            background = Background(BackgroundFill(Paint.valueOf(AppColours.hex(AppColours.background_form_button_hover)),
                CornerRadii(3.0), null))
        }
        setOnMouseExited {
            background = Background(BackgroundFill(Paint.valueOf(AppColours.hex(AppColours.background_form_button)),
                CornerRadii(3.0), null))
        }
        setOnMouseClicked { onSubmit() }
        onKeyPressed = { e ->
            if (e.code == KeyCode.ENTER)
                onSubmit()
        }
    }
    return VBox(submitButton)
}