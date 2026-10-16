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

import javafx.application.Platform
import java.util.*


/**
 * A simple looper to catch events from background threads into the UI thread.
 */
class Looper(val eventHandler: EventHandlerIF) {

    private var running = false
    private val eventQueue : MutableList<EventHandlerIF.Event> = mutableListOf()
    private val timer = Timer()

    fun start(looperPeriodMillis: Long) {
        if (running) return
        timer.schedule(object : TimerTask() {
            override fun run() {
                Platform.runLater(object : Runnable {
                    override fun run() {
                        if (eventQueue.isEmpty()) return
                        eventHandler.handleEvent(eventQueue.removeFirst())
                    }
                })
            }
        }, looperPeriodMillis, looperPeriodMillis)
        running = true
    }

    fun stop() {
        running = false
        timer.cancel()
    }

    fun append(event: EventHandlerIF.Event) { eventQueue.add(event) }

}