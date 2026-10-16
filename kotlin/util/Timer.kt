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

import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlin.coroutines.EmptyCoroutineContext
import kotlin.reflect.KSuspendFunction0
import kotlin.time.Duration.Companion.milliseconds

class Timer(private val updateInterface: KSuspendFunction0<Unit>) {
    private var timer: Job? = null

    /**
     * Start the timer. Do nothing if already started.
     */
    fun start(millis: Long) {
        // do not restart the timer.
        if (timer != null)
            return
        val callback = updateInterface
        timer = CoroutineScope(EmptyCoroutineContext).launch {
            // repeat "eternally". Using a 500 ms interval this wll run for 34 years.
            repeat (Int.MAX_VALUE) {
                // enclose the callback into a try/catch block to ensure, that
                // the timer will nor stop due to an exception
                try {
                    callback()
                    delay(millis.milliseconds)
                } catch (_: Exception) {
                }
            }
        }
    }

    @Suppress("Unused")
    fun stop() {
        timer?.cancel()
    }
}