package org.tfyh.jfx.design

import javafx.scene.text.Font
import javafx.scene.text.FontPosture
import javafx.scene.text.FontWeight
import org.tfyh.util.ResProviderIF
import java.io.ByteArrayInputStream

object AppFonts {

    private const val FONT_FAMILY_DEFAULT = "Arial"
    private const val FONT_FAMILY_DILBO = "Prompt"
    private const val FONT_FAMILY_DILBO_BOLD = "Prompt Bold"

    private val defaultFont: Font = Font.font(FONT_FAMILY_DEFAULT, FontWeight.MEDIUM,
        FontPosture.REGULAR, 16.0)

    lateinit var p : Font
    lateinit var b : Font
    lateinit var i : Font
    lateinit var h1 : Font
    lateinit var h2 : Font
    lateinit var h3 : Font
    lateinit var btn : Font

    lateinit var pStyle : String

    private var clientFont: Font? = null
    private var clientFontBold: Font? = null
    private var clientFontItalic: Font? = null
    private var clientFontBoldItalic: Font? = null

    private var pFontSize = 16.0
    private const val H3_FONT_ZOOM = 1.12
    private const val H2_FONT_ZOOM = 1.3
    private const val H1_FONT_ZOOM = 1.5

    fun loadFont(resProvider: ResProviderIF, fileName: String) : Font {
        val fontAsStream = ByteArrayInputStream(resProvider.getBytes("font/$fileName"))
        return Font.loadFont(fontAsStream, 16.0) ?: defaultFont
    }
    
    fun load(resProvider: ResProviderIF, fontZoom: Float) {
        pFontSize *= fontZoom
        clientFont = loadFont(resProvider, "prompt_medium.ttf")
        p = Font.font(FONT_FAMILY_DILBO, FontWeight.MEDIUM,FontPosture.REGULAR, pFontSize)
        pStyle = "-fx-font-family: '$FONT_FAMILY_DILBO'; -fx-font-size: $pFontSize;"
        clientFontItalic = loadFont(resProvider, "prompt_italic.ttf")
        i = Font.font(FONT_FAMILY_DILBO, FontWeight.MEDIUM,FontPosture.ITALIC, pFontSize)
        clientFontBold = loadFont(resProvider, "prompt_bold.ttf")
        b = Font.font(FONT_FAMILY_DILBO, FontWeight.BOLD,FontPosture.REGULAR, pFontSize)
        btn = Font.font(FONT_FAMILY_DILBO, FontWeight.BOLD,FontPosture.REGULAR, pFontSize * H3_FONT_ZOOM)
        h1 = Font.font(FONT_FAMILY_DILBO, FontWeight.BOLD,FontPosture.REGULAR, pFontSize * H1_FONT_ZOOM)
        h2 = Font.font(FONT_FAMILY_DILBO, FontWeight.BOLD,FontPosture.REGULAR, pFontSize * H2_FONT_ZOOM)
        clientFontBoldItalic = loadFont(resProvider, "prompt_bold_italic.ttf")
        h3 = Font.font(FONT_FAMILY_DILBO_BOLD, FontWeight.BOLD, FontPosture.ITALIC,pFontSize * H3_FONT_ZOOM)
    }

}