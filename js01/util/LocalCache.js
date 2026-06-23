/**
 * tools-for-your-hobby
 * https://www.tfyh.org
 * Copyright  2023-2025  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except
 * in compliance with the License. You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software distributed under the License
 * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 * or implied. See the License for the specific language governing permissions and limitations under
 * the License.
 */

/**
 * The local storage provides a container for String items like the JavaScript window.localStorage,
 * but provides no persistence in Web and mobile.
 */
class LocalCache {

    static #instance = new LocalCache()
    static getInstance() { return this.#instance; }

    getItem(key) { return window.localStorage.getItem(key) }
    setItem(key, value) { window.localStorage.setItem(key, value) }
    removeItem(key) { window.localStorage.removeItem(key) }
    clear() { window.localStorage.clear() }
    keys() {
        let keys = []
        for (let i = 0; i < window.localStorage.length; i++)
            keys.push(window.localStorage.key(i))
        return keys
    }
    init() {} // dummy for compatibility reasons.
}