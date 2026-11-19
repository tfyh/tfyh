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

import javafx.scene.layout.Pane
import org.tfyh.data.Config
import org.tfyh.data.Item

interface EventHandlerIF {

    enum class EventType {
        UI_EVENT,    // any user interface event, like a mouse click
        API_EVENT,   // any event triggered by the API, like for user authentication
        MENU_EVENT,  // all menu selections, i.e. events which require a permissions check
        DATA_EVENT,  // any data event like reloaded information
        SYSTEM_EVENT // system events like a clock trigger
    }

    data class Event(
        /**
         * The event type, see enum for details
         */
        val type : EventType,
        /**
         * A String providing the event name
         */
        val name: String,
        /**
         * If the event is linked to a record, provide the records uid
         */
        val uid: String = "",
        /**
         * If the event is either linked to or a configuration item, provide the item.
         */
        val sourceItem: Item = Config.getInstance().invalidItem,
        /**
         * An optional String providing the event message
         */
        val message: String = "")
        {
            /**
             * create a clone event. Every which is not given will be cloned. Change a property by calling the function
             * with a value for the property to change, usually the name.
             */
            fun clone(type: EventType = this.type, name: String = this.name, uid: String = this.uid,
                      sourceItem: Item = this.sourceItem, message: String = this.message) =
                Event(type, name, uid, sourceItem, message)

            override fun toString() = "$type - $name: ${sourceItem.fullPath()} -> uid=$uid ($message)"
        }

    /**
     * the pane on which this event handling UI-element is displayed. Used to remove the Eventhandler registration when
     * the pane is destroyed
     */
    var isDisplayedOn: Pane?
    /**
     * Handle the Event. Return true if handled, false on no relevance or all errors.
     */
    fun handleEvent(event: Event): Boolean
}