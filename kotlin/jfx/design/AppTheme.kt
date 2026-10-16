package org.tfyh.jfx.design

class AppTheme private constructor() {

    private var colors = appLightColorScheme
    private var dimensions = appDefaultDimensions
    companion object {
        private val instance = AppTheme()
        fun getInstance() = instance
    }
}