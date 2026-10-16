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


fun textAutoCompleteInput(formField: FormField, allowFreeText: Boolean = true) {
    val options: MutableList<String> = formField.options.keys.toMutableList()
    val valueEntered = formField.viewModel.entered.collectAsState().value
    val icon = if (valueEntered.isEmpty() || !allowFreeText)
            Icons.Default.ArrowDropDown
        else if (options.contains(valueEntered.trim()))
            Icons.Filled.CheckCircle
        else if (formField.isMatchingFragment(valueEntered, false))
            Icons.Default.ArrowDropDown
        else Icons.Outlined.Warning

    Box (
        modifier = formField.boxModifier { formField.viewModel.setExpanded(true) }
    ) {

        formField.filterOptions(options, if (allowFreeText) formField.entered else "",
                        false, 12)
        if (!allowFreeText && formField.viewModel.entered.collectAsState().value.isEmpty())
            formField.viewModel.setEntered(options.first())

        TextField(
            colors = formField.viewModel.textFieldColors(),
            shape = TextFieldDefaults.shape,
            textStyle = Theme.fonts.p,
            singleLine = true,
            onValueChange = {
                formField.viewModel.setTypedTextLength(formField.viewModel.typedTextLength.value + 1)
                formField.filterOptions(options, it, false, 2)
                formField.entered = if (allowFreeText) {
                    if (options.size == 1)
                        options.first()
                    else if (it.length >= formField.viewModel.typedTextLength.value)
                        it.substring(0, formField.viewModel.typedTextLength.value)
                    else it
                } else {
                    if (options.isEmpty()) {
                        formField.viewModel.setExpanded(!formField.viewModel.expanded.value)
                        formField.entered
                    } else
                        options.first()
                }
                formField.viewModel.setEntered(formField.entered)
            },
            value = formField.viewModel.entered.collectAsState().value.ifEmpty { formField.preset },
            modifier = Modifier
                .width(formField.width)
                .onSizeChanged { formField.viewModel.setDropDownWidth(it.width) }
                .padding(top = Theme.dimensions.smallSpacing)
                .onFocusChanged {
                    formField.onFocusChanged(it.isFocused) },
            label = { Text(text = formField.label, style = Theme.fonts.p, color = formField.viewModel.labelColor()) },
            trailingIcon = {
                IconButton(onClick = { formField.viewModel.setExpanded(!formField.viewModel.expanded.value) }) {
                    Icon(
                        imageVector = icon,
                        contentDescription = "Show selections",
                    )
                }
            },
        )

        DropdownMenu(
            expanded = formField.viewModel.expanded.collectAsState().value,
            onDismissRequest = { formField.viewModel.setExpanded(false) },
            modifier = Modifier
                .width(with(LocalDensity.current) {
                    formField.viewModel.dropDownWidth.collectAsState().value.toDp() })
                .height(Theme.dimensions.textFieldHeight * 2)
                .background(Theme.colors.color_background_form_input)
        ) {
            options.forEach { label ->
                DropdownMenuItem(
                    modifier = Modifier.height(Theme.dimensions.textFieldHeight / 2),
                    onClick = {
                        formField.entered = label.trim()
                        formField.viewModel.setEntered(formField.entered)
                        formField.viewModel.setExpanded(false)
                    },
                    colors = MenuItemColors(
                        textColor = Theme.colors.color_text_form_input,
                        leadingIconColor = Theme.colors.color_text_form_input,
                        trailingIconColor = Theme.colors.color_text_form_input,
                        disabledTextColor = Theme.colors.color_text_form_input_hover,
                        disabledLeadingIconColor = Theme.colors.color_text_form_input_hover,
                        disabledTrailingIconColor = Theme.colors.color_text_form_input_hover,
                    ),
                    // text assignment is not tested, different from in the stackoverflow suggestion.
                    text = { AppLabel(label.trim()) }
                )
            }
        }
    }
}
