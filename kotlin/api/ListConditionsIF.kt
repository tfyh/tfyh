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

package org.tfyh.api

/**
 * The LIST-transaction represents predefined database queries. The API retrieves with the help of this interface
 * those query conditions which depend on a programmatically computed parameter.
 */
interface ListConditionsIF {
    /**
     * Retrieves those query conditions which depend on a programmatically computed parameter.
     * @param listName the name of the list to be retrieved
     * @return the list arguments as name-value-pair without braces, e.g. mapOf("modified_after", "0")
     */
    fun getFurtherConditions(listName: String): Map<String, String>
}