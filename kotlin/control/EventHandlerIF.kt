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

package org.tfyh.control

import org.tfyh.data.Config
import org.tfyh.data.Item

interface EventHandlerIF {

    enum class EventType {
        UI_EVENT,   // any user interface event, like a mouse click
        API_EVENT,  // any event triggered by the API, like for user authentication
        MENU_EVENT, // all menu selections, i.e. events which require a permissions check
        DATA_EVENT // any data event like reloaded information
    }

    data class Event(
        /**
         * The event type, see enum for details
         */
        val type : EventType,
        /**
         * A String providing the event name
         */
        var name: String,
        /**
         * If the event is linked to a record, provide the records uid
         */
        var uid: String = "",
        /**
         * If the event is either linked to or a configuration item, provide the item.
         */
        var sourceItem: Item = Config.getInstance().invalidItem,
        /**
         * An optional String providing the event message
         */
        var message: String = "")
        {
            override fun toString() = "$type - $name: ${sourceItem.fullPath()} -> uid=$uid ($message)"
        }

    /**
     * Handle the Event. Return true on success, false on all errors.
     */

    fun handleEvent(event: Event): Boolean
}