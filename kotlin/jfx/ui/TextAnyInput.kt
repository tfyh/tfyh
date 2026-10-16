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

fun textAnyInput(
    formField: FormField,
    singleLine: Boolean = true
) {
    val hideText = formField.type.name() == "password"
    Box(
        modifier = formField.boxModifier {  }
    ) {
        TextField(
            modifier = Modifier
                .width(formField.width)
                .padding(top = Theme.dimensions.smallSpacing),
            colors = formField.viewModel.textFieldColors(),
            enabled = (formField.modifierChar != "!"),
            shape = TextFieldDefaults.shape,
            textStyle = Theme.fonts.p,
            value = formField.viewModel.entered.collectAsState().value.ifEmpty { formField.preset },
            visualTransformation = if (hideText) PasswordVisualTransformation() else VisualTransformation.None,
            onValueChange = {
                formField.viewModel.setEntered(it)
                formField.entered = it
            },
            singleLine = singleLine,
            label = { Text(text = formField.label, style = Theme.fonts.p, color = formField.viewModel.labelColor()) },
            keyboardOptions = KeyboardOptions(
                imeAction = ImeAction.Next,
                // keyboardType = formField.keyboardType(),
            )
        )
    }
}



