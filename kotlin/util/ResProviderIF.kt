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

package org.tfyh.util

/**
 * Provide framework access to the application's resources.
 */
interface ResProviderIF {

    /**
     * Read a resource into a ByteArray. This is an asynchronous getter.
     */
    suspend fun readBytes(path: String): ByteArray

    /**
     * Read all resources into memory to avoid suspend calls during UI composition
     */
    suspend fun initDrawablesAndFonts()

    /**
     * Get a drawable resource as ByteArray. This is a synchronous getter and requires an initDrawablesAndFonts() call to read the
     * all files within the drawables resource directory into memory. Must return an empty ByteArray, if no drawable
     * resource of the given name was found.
     * @param fileName the file name of the drawable, e.g. app_logo.png
     */
    fun getDrawable(fileName: String): ByteArray

    /**
     * Get a Font resource as ByteArray. This is a synchronous getter and requires an initDrawablesAndFonts() call to read the
     * all files within the fonts resource directory into memory. Must return an empty ByteArray, if no font
     * resource of the given name was found.
     * @param fileName the file name of the font, e.g. arial.ttf
     */
    fun getFont(fileName: String): ByteArray
}