package org.tfyh.kmp.ui

import androidx.compose.ui.unit.Dp

data class Dimensions(val widthDp: Dp, val heightDp: Dp) {
    fun isPortrait() = widthDp < heightDp
}
