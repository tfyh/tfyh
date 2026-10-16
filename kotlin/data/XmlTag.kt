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

package org.tfyh.data

/**
 * Class to parse and hold an XML tag and the branch which is depending on it.
 */
class XmlTag {
    /**
     * @return all attributes for the top tag
     */
    private val attributes: MutableMap<String, String> = mutableMapOf()

    // children
    private val subTags: MutableList<XmlTag> = mutableListOf()

    /*
     * Lowest index is always the target (because its unique).
     */
    private var maxIndexSubTag = -1

    /*
     * The tree information. Only the top tag will have a null parent.
     */
    var parent: XmlTag? = null

    var name: String = ""
    private var tagID: String? = null
    private var comment = ""
    private var openTagText = ""
    private var closeTagText: String? = ""

    private var isOpenTag = false
    private var isComment = false
    private var isScript: Boolean = false
    private var isCloseTag = false
    private var isSelfClosing: Boolean = false

    /*
     * ######################### separator ##############################
     */
    /*
     * Constructor methods
     */
    /*
     * ######################### separator ##############################
     */
    /**
     * Constructor.
     */
    private constructor(parent: XmlTag?, index: Int = -1) {
        if (parent != null) {
            this.parent = parent
            if (index >= 0)
                name = ITEM_NAME + index
        } else
            name = "ROOT"
    }

    /**
     * Sets tagID and sets tag type flags. Self-closing tag check is performed and the last tag character for '/' is not
     * included in the tagID. If the self-closing tag has attributes, its self-closing property will be detected later
     * by attribute parsing. Close tagID must starts with a '/' character, so that the open tag and close tag do not have
     * the same tagID.
     *
     * Do only use for real tags. Sets comment and short comment flag to false,
     *
     * @param tagIDInclSlash tag ID including slash for a close tag
     */
    private fun setAndClassifyTagID(tagIDInclSlash: String) {
        isComment = false
        comment = ""

        // HTML self-closing tags, see e.g. https://www.lifewire.com/html-singleton-tags-3468620
        // ignore error like </br> instead of <br />
        tagID = tagIDInclSlash.replace("/", "")
        isSelfClosing =
            tagIDInclSlash.endsWith("/") || tagID.equals("area", ignoreCase = true) || tagID.equals(
                "base",
                ignoreCase = true
            ) ||
                    tagID.equals("br", ignoreCase = true) || tagID.equals("col", ignoreCase = true) ||
                    tagID.equals("command", ignoreCase = true) || tagID.equals("embed", ignoreCase = true) ||
                    tagID.equals("hr", ignoreCase = true) || tagID.equals("img", ignoreCase = true) ||
                    tagID.equals("input", ignoreCase = true) || tagID.equals("keygen", ignoreCase = true) ||
                    tagID.equals("link", ignoreCase = true) || tagID.equals("meta", ignoreCase = true) ||
                    tagID.equals("param", ignoreCase = true) || tagID.equals("source", ignoreCase = true) ||
                    tagID.equals("track", ignoreCase = true) || tagID.equals("wbr", ignoreCase = true)
        isCloseTag = tagIDInclSlash.startsWith("/") && !isSelfClosing // ignore errors like </br> instead of <br />
        isOpenTag = !isCloseTag && !isSelfClosing
        isScript = isOpenTag && tagID == "script"
    }

    /**
     * Reads an XML string and adds it to this tag.
     *
     * @param xmlString        string to be read
     * @param tagToTakeForText List of String with tag which shall be regarded as text. Set tagToTakeForText=null to
     * avoid this check at all.
     */
    fun readFile(xmlString: String, tagToTakeForText: List<String>, reportProgress: Boolean) {
        var tagsRead = 0
        var lc = this.readTag(xmlString, 0, tagToTakeForText)
        // skip xml header definition
        while (tagID().equals("xml", ignoreCase = true) || tagID().startsWith("?") || tagID().startsWith("!")) lc =
            readTag(xmlString, lc, tagToTakeForText)
        val len = xmlString.length
        var current : XmlTag? = this
        while (lc < len) {
            if (current?.isOpenTag ?: false) {
                val newChild = current.getChild() as XmlTag
                lc = newChild.readTag(xmlString, lc, tagToTakeForText)
                tagsRead++
                if (reportProgress && ((tagsRead - ((tagsRead / 1000) * 1000)) == 0)) println(" > Tags read: $tagsRead, characters read: $lc / $len")

                // assign the text following the tag to this HtmlTag object
                //
                if (lc < len) {
                    val lcn = skipText(xmlString, lc + 1, tagToTakeForText)
                    newChild.setOpenTagText(xmlString.substring(lc + 1, lcn))
                    lc = lcn
                }

                if (newChild.isCloseTag) {
                    current.setCloseTagText(newChild.getOpenTagText())
                    // close tags are not memorised, but text assigned to respective open tag.
                    current.removeChild(newChild.name)
                    // closing a tag hands the control back to the parent.
                    val newChildTagID = newChild.tagID()
                    val currentTagID = current.tagID()
                    if (newChildTagID.equals(
                            currentTagID,
                            ignoreCase = true
                        )
                    )  // close and hand over control to parent tag.
                        current = current.parent
                    else {
                        // close tag could not be matched. Ignore it, i.e. keep current tag open.
                        if (newChild.tagID == "script") println("script section ignored.")
                        else {
                            val errorStr = "Unmatched close tag found: $newChild at character #$lc"
                            println(errorStr)
                        }
                    }
                } else if (newChild.isOpenTag && !newChild.isSelfClosing) {
                    // check for disallowed child tags
                    if (current.tagID.equals("a", ignoreCase = true) && newChild.tagID.equals("a", ignoreCase = true)) {
                        val errorStr = "Anchor within anchor ignored: $newChild at character #$lc"
                        println(errorStr)
                    } else  // opening a tag hands over the control to the new tag
                        current = newChild
                }
            }
        }
    }

    /**
     * @return the tag id without the closing '/'. E.g. &lt;xml&gt; and &lt;/xml&gt; return both "xml". Tags are always
     * converted to lower case strings
     */
    fun tagID(): String {
        if (isComment) return TAG_ID_COMMENT
        return tagID!!
    }

    /**
     * Returns the text following the respective open tag.
     */
    fun getOpenTagText(): String {
        return openTagText
    }

    /**
     * Sets the text of the tag.
     */
    fun setOpenTagText(textFollowing: String) {
        this.openTagText = textFollowing
    }

    /**
     * Sets the text of the tag.
     */
    private fun setCloseTagText(textFollowing: String?) {
        this.closeTagText = textFollowing
    }

    /*
     * ######################### separator ##############################
     */
    /*
     * tag parsing and formatting methods
     */
    /*
     * ######################### separator ##############################
     */

    /**
     * Overrides default toString() method for debugging purposes.
     */
    override fun toString(): String {
        val text = StringBuilder("uText.XmlReader '$tagID': ")
        if (isComment || isScript) {
            text.append(if (isComment) "isComment. " else "isScript. ")
            if (comment.length > 25) text.append(comment).append(" ... ")
            else text.append(comment)
        }
        if (isOpenTag) text.append("isOpenTag, ")
        if (isCloseTag) text.append("isCloseTag, ")
        if (isSelfClosing) text.append("isSelfClosing, ")

        if (attributes.isNotEmpty()) text.append("attributes: ")
        for (key in attributes.keys)
            text.append(key).append("=").append(attributes[key] ?: "").append(" ")
        return text.toString()
    }

    /**
     * Returns true, if at this point the xmlStr has an end of tag. This is reached if xmlStr.charAt(checkAt) equals
     * "&gt;" or a comment properly ends with the COMMENT_END.
     */
    private fun isTagEnd(xmlStr: String, checkAt: Int): Boolean {
        // ignore all ">" characters within a comment
        if (isComment && (checkAt >= COMMENT_END.length)) {
            val compare = xmlStr.substring(checkAt + 1 - COMMENT_END.length, checkAt + 1)
            return (compare.equals(COMMENT_END, ignoreCase = true))
        } else if (isScript && (checkAt >= SCRIPT_END.length)) {
            val compare = xmlStr.substring(checkAt + 1 - SCRIPT_END.length, checkAt + 1)
            return (compare.equals(SCRIPT_END, ignoreCase = true))
        } else return (xmlStr[checkAt] == '>')
    }

    /**
     * Fill this tag by reading the next tag from a given XML string (full file, usually). Parses String from beginAt
     * looking for the "&lt;" character. Once found, it copies the text into the tag and returns the position of the
     * '&gt;' character of the tag end.
     *
     * Skips all tags which shall be taken for text. Identifies a text number via
     * the TNUM_ATTR_ID within the tag and puts it to the text number field of the tag.
     *
     * @param xmlStr           String top be parsed
     * @param beginAt          position to start with parsing
     * @param tagToTakeForText List of String with tag which shall be regarded as text. Set tagToTakeForText=null to
     * avoid this check at all.
     * @return the "beginAt" value for continued parsing.
     */
    private fun readTag(xmlStr: String, beginAt: Int, tagToTakeForText: List<String>): Int {
        isOpenTag = false
        isCloseTag = false
        isSelfClosing = false
        isComment = false
        comment = ""
        isScript = false
        attributes.clear()
        var isXmlStartTag = false

        // read tag
        var lc = beginAt
        var len = xmlStr.length
        var tag = StringBuilder()
        // find start
        while ((lc < len) && isNoTagStart(xmlStr, lc, tagToTakeForText)) {
            lc++
        }

        // parse tag.
        var withinQuotes = false // double quote delimited literal
        var lastC: Char
        // find end and check, whether tag is comment tag
        while ((lc < len) && (!isTagEnd(xmlStr, lc) || withinQuotes)) {
            val c = xmlStr[lc]
            withinQuotes = !withinQuotes && c == '"' || withinQuotes && c != '"'
            tag.append(xmlStr[lc])
            lc++
            isComment = (tag.length >= COMMENT_START.length) &&
                    tag.substring(0, COMMENT_START.length).equals(COMMENT_START, ignoreCase = true)
            isScript = (tag.length >= SCRIPT_START.length) &&
                    tag.substring(0, SCRIPT_START.length).equals(SCRIPT_START, ignoreCase = true)
            isXmlStartTag = tag.startsWith("<?")
            isSelfClosing = tag.endsWith("/>")
        }
        // close tag and return, if comment tag
        if (lc < len) tag.append(xmlStr[lc])
        if (isComment) {
            tagID = "!--"
            comment = tag.toString()
            return lc
        }
        if (isScript) {
            tagID = "script"
            return lc
        }
        if (isXmlStartTag) {
            tag = StringBuilder(tag.toString().replace("<?", "<").replace("?>", ">"))
        }
        // parse tag.
        withinQuotes = false // double quote delimited literal
        var pair = StringBuilder()
        lastC = ' '
        var lt = 0
        len = tag.length

        // split at blanks, if not in a String.
        // tagID is taken care of later.
        tagID = ""
        while (lt < len) {
            val c = tag[lt]
            // check whether parser is within String literal.
            withinQuotes =
                (!withinQuotes && (c == '"')) || (withinQuotes && (c != '"')) || (withinQuotes && (lastC == '\\'))

            // find end of element
            if (!withinQuotes && ((c == ' ') || (c == '\n') || (c == '\r') || (c == '\t') || (c == '>'))) {
                val pairAsList = pair.toString().split("=".toRegex(), limit = 2)
                if (pairAsList.size == 2)
                    attributes[pairAsList[0]] = pairAsList[1]
                else if (tagID?.isEmpty() ?: false)
                        tagID = (pairAsList.getOrNull(0) ?: "<").substring(1)
                else
                    attributes[pairAsList[0]] = ""
                pair = StringBuilder()
            } else pair.append(tag[lt])
            lt++
            lastC = c
        }

        setAndClassifyTagID(tagID ?: "")
        return lc

    }

    /**
     * Simple getter
     *
     * @return all sub-tags in a flat list. No drill down.
     */
    private fun getSubTags(): MutableList<XmlTag> {
        return subTags
    }

    /**
     * Appends the text included in the given branch without any tag to the provided textBuilder
     *
     * @param textBuilder StringBuilder to append the tag text to.
     */
    private fun getBranchText(textBuilder: StringBuilder) {
        textBuilder.append(openTagText)
        for (subTag in subTags) {
            subTag.getBranchText(textBuilder)
        }
        textBuilder.append(closeTagText)
    }

    val children: MutableList<XmlTag>
        get() = subTags

    fun getChild(childName: String = ""): XmlTag? {
        val cName = childName.ifEmpty {
            maxIndexSubTag++
            ITEM_NAME + maxIndexSubTag
        }
        // check for doublets. Return existing, if the child was found.
        for (child in subTags)
            if (child.name.equals(cName, ignoreCase = true)) return child

        // check index, adapt max index
        val itemName = getItemName(cName)
        val index = getIndex(cName)
        if (itemName.equals(ITEM_NAME, ignoreCase = true)) {
            if (index == -1)  maxIndexSubTag++
            else if (index > maxIndexSubTag) maxIndexSubTag = index
            val newTag = XmlTag(this, maxIndexSubTag)
            subTags.add(newTag)
            newTag.parent = this
            return newTag
        }
        return null
    }

    /**
     * Remove a tag from its parent. Returns the removed tag or null, if no child of the given name exists
     */
    fun removeChild(extraChildName: String): XmlTag? {
        val extraTag: XmlTag = getChild(extraChildName) ?: return null
        subTags.remove(extraTag)
        extraTag.parent = null
        return extraTag
    }

    companion object {

        private val CHARACTER_TO_ENTITY_MAP: Map<String, String>
        private val ENTITY_TO_CHARACTER_MAP: Map<String, String>

        /**
         * These are static encodings. use dynamic ones (as of the java platform) by running the static
         * initialisation with invocation of the getEncodings() method
         */
        private val encodings: List<String> = listOf(
            "weierp=8472", "supe=8839", "image=8465", "ecirc=234", "Otilde=213", "uacute=250",
            "diams=9830", "ntilde=241", "dArr=8659", "Ecirc=202", "ograve=242", "yacute=253", "times=215",
            "iuml=239", "rArr=8658", "micro=181", "rceil=8969", "plusmn=177", "there4=8756", "nabla=8711",
            "lsaquo=8249", "rang=9002", "Iuml=207", "real=8476", "sup3=179", "sube=8838", "acirc=226",
            "sup2=178", "sup1=185", "lsquo=8216", "Acirc=194", "sect=167", "notin=8713", "radic=8730",
            "ocirc=244", "oplus=8853", "euml=235", "Oacute=211", "rfloor=8971", "rdquo=8221", "Ocirc=212",
            "Igrave=204", "minus=8722", "trade=8482", "szlig=223", "Agrave=192", "forall=8704", "laquo=171",
            "cedil=184", "Euml=203", "ensp=8194", "Egrave=200", "otilde=245", "lowast=8727", "uml=168",
            "perp=8869", "int=8747", "nbsp=160", "Oslash=216", "Ugrave=217", "auml=228", "part=8706", "gt=62",
            "ouml=246", "ge=8805", "para=182", "empty=8709", "Auml=196", "isin=8712", "ang=8736", "uarr=8593",
            "agrave=224", "Ouml=214", "and=8743", "cap=8745", "exist=8707", "oline=8254", "egrave=232",
            "rsquo=8217", "oacute=243", "frac34=190", "larr=8592", "amp=38", "lrm=8206", "Atilde=195",
            "iquest=191", "infin=8734", "reg=174", "igrave=236", "sbquo=8218", "ucirc=251", "Ucirc=219",
            "yuml=255", "copy=169", "nsub=8836", "prime=8242", "raquo=187", "Ccedil=199", "Prime=8243",
            "hearts=9829", "oslash=248", "ugrave=249", "harr=8596", "brvbar=166", "Dagger=8225", "equiv=8801",
            "quot=34", "ordm=186", "deg=176", "bull=8226", "alefsym=8501", "frac14=188", "frac12=189",
            "ordf=170", "Iacute=205", "sim=8764", "zwnj=8204", "lfloor=8970", "otimes=8855", "rsaquo=8250",
            "Aacute=193", "uuml=252", "ndash=8211", "clubs=9827", "sup=8835", "atilde=227", "spades=9824",
            "sum=8721", "not=172", "loz=9674", "curren=164", "shy=173", "Eacute=201", "or=8744", "thinsp=8201",
            "sdot=8901", "aring=229", "sub=8834", "uArr=8657", "pound=163", "bdquo=8222", "Aring=197",
            "Uuml=220", "darr=8595", "Uacute=218", "cong=8773", "Ntilde=209", "ccedil=231", "aelig=230",
            "lArr=8656", "emsp=8195", "rarr=8594", "Ograve=210", "lceil=8968", "thorn=254", "Yacute=221",
            "euro=8364", "permil=8240", "dagger=8224", "ni=8715", "cent=162", "ne=8800", "cup=8746",
            "lang=9001", "asymp=8776", "THORN=222", "aacute=225", "AElig=198", "crarr=8629", "acute=180",
            "ETH=208", "iexcl=161", "icirc=238", "eacute=233", "divide=247", "eth=240", "hArr=8660",
            "ldquo=8220", "Icirc=206", "macr=175", "rlm=8207", "yen=165", "iacute=237", "hellip=8230",
            "middot=183", "prop=8733", "lt=60", "frasl=8260", "mdash=8212", "zwj=8205", "prod=8719", "le=8804"
        )

        init {
            val characterToEntityMap: MutableMap<String, String> = HashMap()
            val entityToCharacterMap: MutableMap<String, String> = HashMap()
            /*
             * These are static encodings. use dynamic ones (as of the java platform)
             * by running the static initialisation wit invocation of the
             * getEncodings() method:
             * String[] encodings = getEncodings();
             */
            for (e in encodings) {
                val nvp = e.split("=".toRegex()).dropLastWhile { it.isEmpty() }.toTypedArray()
                val value = nvp[1].toInt()
                val charOfValue = value.toChar().toString()
                val codeWord: String = nvp[0]
                entityToCharacterMap[codeWord] = charOfValue
                characterToEntityMap[charOfValue] = codeWord
            }
            entityToCharacterMap["#039"] = "'"
            characterToEntityMap["'"] = "#039"
            entityToCharacterMap["nbsp"] = " "
            characterToEntityMap[" "] = "nbsp"
            ENTITY_TO_CHARACTER_MAP = entityToCharacterMap.toMap()
            CHARACTER_TO_ENTITY_MAP = characterToEntityMap.toMap()
        }

        private const val ITEM_NAME = "t$"

        private const val COMMENT_START = "<!--"
        private const val COMMENT_END = "-->"
        private const val SCRIPT_START = "<script"
        private const val SCRIPT_END = "/script>"

        private const val TAG_ID_COMMENT = "!--[comment]--"

        /**
         * Constructor. Creates a new tag object and parses the passed String. Shall be the XML file for which the new
         * XmlReader ist than the root tag.
         *
         * @param xmlString        tag String, e.g. "&lt;h1 class='title'&gt;Headline"
         * @param tagToTakeForText List of String with tag which shall be regarded as text. Set tagToTakeForText=null to
         * avoid this check at all.
         * @param reportProgress   reports progress to the console, if true.
         */
        fun getTree(xmlString: String, tagToTakeForText: List<String>, reportProgress: Boolean) : XmlTag {
            val xmlReader = XmlTag(null)
            xmlReader.readTag(xmlString, 0, tagToTakeForText)
            xmlReader.readFile(xmlString, tagToTakeForText, reportProgress)
            return xmlReader
        }

        /**
         * Reads a text sequence in between tags. beginAt must be the first character of the text (the character after the
         * '&gt;' character). Returns just the last character index.
         *
         * @param xmlStr           resource to parse
         * @param beginAt          start position
         * @param tagToTakeForText List of String with tag which shall be regarded as text. Set tagToTakeForText=null to
         * avoid this check at all.
         * @return end position, i.e. the position of the first '&lt;' character
         */
        private fun skipText(xmlStr: String, beginAt: Int, tagToTakeForText: List<String>?): Int {
            if (beginAt >= xmlStr.length) return beginAt
            val len = xmlStr.length
            var lc = beginAt
            while ((lc < len) && (isNoTagStart(xmlStr, lc, tagToTakeForText))) lc++
            return lc
        }

        /**
         * Returns true, if at this point the xmlStr has a start of tag. This is reached if xmlStr.charAt(checkAt) equals
         * "&lt;" and the subsequent tag does not equal any of the tags which shall be taken for text.
         *
         * @param xmlStr            String to be parsed
         * @param checkAt           position to look at
         * @param tagsToTakeForText List of String with tag which shall be regarded as text. Set tagToTakeForText=null to
         * avoid this check at all.
         * @return true, if following text is start of an XML tag. At minimum the character is "<"
         */
        private fun isNoTagStart(xmlStr: String, checkAt: Int, tagsToTakeForText: List<String>?): Boolean {
            if (xmlStr[checkAt] != '<') return true
            if (tagsToTakeForText == null) return false
            val len = xmlStr.length
            if (checkAt >= len - 2) return true
            val tagStart = if (xmlStr[checkAt + 1] == '/') checkAt + 2 else checkAt + 1
            for (tagToSkip in tagsToTakeForText) {
                if ((tagStart + tagToSkip.length) < xmlStr.length) {
                    val compare = xmlStr.substring(tagStart, tagStart + tagToSkip.length)
                    val nextC = xmlStr[tagStart + tagToSkip.length]
                    if (compare.equals(tagToSkip, ignoreCase = true) &&
                        ((nextC == '\t') || (nextC == '\n') || (nextC == '\r') || (nextC == ' ') || (nextC == '>'))
                    ) return true
                }
            }
            return false
        }

        /**
         * Recursive tag adding to create a flat tag list
         *
         * @param tagsList list to add tags to
         * @param tag      tag to be added, including all children asf. in a flat style
         */
        private fun addBranchTags(tagsList: MutableList<XmlTag?>, tag: XmlTag) {
            tagsList.add(tag)
            for (t in tag.getSubTags()) addBranchTags(tagsList, t)
        }

        /*
         * ######################### separator ##############################
         */
        /*
         * Getter and setter methods
         */
        /*
         * ######################### separator ##############################
         */

        /**
         * Cuts the name in two at $-character and returns the item name part of it including the '$'
         * character at the end. Returns name, if there is no $ delimiter.
         */
        private fun getItemName(name: String): String {
            if (name.indexOf('$') < 0) return name
            return name.substring(0, name.indexOf('$') + 1)
        }

        /**
         *
         *  Cuts the name in two at $-character and returns the index parsed. Returns -1, if there is
         * no $ or parsing of integer failed.
         */
        private fun getIndex(name: String): Int {
            if (name.indexOf('$') < 0) return -1
            val `is` = name.substring(name.indexOf('$') + 1)
            val ret: Int = try {
                `is`.toInt()
            } catch (_: Exception) {
                -1
            }
            return ret
        }
    }
}
