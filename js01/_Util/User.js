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

class User {

    #userId = -1
    #firstName = "First"
    #lastName = "Last"
    #uuid = Ids.NIL_UUID
    #role = "anonymous"
    #subscriptions = 0
    #workflows = 0
    #concessions = 0
    #preferences = ""

    static isPrivilegedRole = {}
    static includedRoles = {}
    static #instance = new User()
    static getInstance() { return User.#instance }

    setIncludedRoles()
    {
        // resolve role hierarchy
        let roles = config.getItem(".framework.roles");
        for (let role of roles.getChildren()) {
            let mainRole = role.name()
            let includedRoles = role.valueStr()
            let isPrivilegedRole = includedRoles.startsWith("*")
            User.isPrivilegedRole[mainRole] = isPrivilegedRole
            if (isPrivilegedRole)
                includedRoles = includedRoles.substring(1)
            User.includedRoles[mainRole] = includedRoles.split(/,/g)
        }
    }

    set(csv) {
        let rows = Codec.csvToMap(csv)
        this.#userId = -1
        this.#firstName = "First"
        this.#lastName = "Last"
        this.#role = "anonymous"
        this.#workflows = 0
        this.#subscriptions = 0
        this.#concessions = 0
        this.#preferences = ""
        if (rows.length === 0)
            return
        let fields = rows[0];
        if (fields["userId"]) this.#userId = fields["userId"]
        if (fields["firstName"]) this.#firstName = fields["firstName"]
        if (fields["lastName"]) this.#lastName = fields["lastName"]
        if (fields["role"]) this.#role = fields["role"]
        if (fields["subscriptions"]) this.#subscriptions = fields["subscriptions"]
        if (fields["workflows"]) this.#workflows = fields["workflows"]
        if (fields["concessions"]) this.#concessions = fields["concessions"]
        if (fields["preferences"]) this.#preferences = fields["preferences"]
    }

    /* ======================== Access Control ============================== */

    userId() { return this.#userId }
    firstName() { return this.#firstName }
    lastName() { return this.#lastName }
    fullName() { return this.#firstName + " " + this.#lastName }
    uuid() { return this.#uuid }
    role() { return this.#role }
    subscriptions() { return this.#subscriptions }
    workflows() { return this.#workflows }
    concessions() { return this.#concessions }
    preferences() { return this.#preferences }

    isHiddenItem(permission) {
        return (this.#isAllowedOrHiddenItem(permission) & 2) > 0
    }
    isAllowedItem(permission) {
        return (this.#isAllowedOrHiddenItem(permission) & 1) > 0
    }
    /**
     * Check for workflows, concessions, and subscriptions whether they are allowed for the current user.
     */
    #addAllowedOrHiddenService(allowedOrHidden, permissionsArray, services, serviceIdentifier)
    {
        let allowedOrHiddenNew = allowedOrHidden
        for (let permissionsElement of permissionsArray) {
            if (permissionsElement.indexOf(serviceIdentifier) >= 0) {
                let elementHidden = permissionsElement.startsWith(".")
                let elementServiceMap = parseInt(permissionsElement.substring((elementHidden) ? 2 : 1))
                let elementAllowed = (services & elementServiceMap) > 0
                if (elementAllowed) {
                    // add allowance if element is allowed
                    allowedOrHiddenNew = allowedOrHiddenNew | 1
                    // remove hidden flag, if allowed and not hidden.
                    if (!elementHidden && ((allowedOrHidden & 2) > 0))
                    allowedOrHiddenNew -= 2
                }
            }
        }
        return allowedOrHiddenNew
    }

    /**
     * Check whether a role shall get access to the given item and, if so, whether it should be displayed in
     * the menu. The role will be expanded according to the hierarchy, and all included roles are as well
     * checked, except it is preceded by a '!'. If the permission String is preceded by a "." the menu will
     * not be shown, but accessible - same for all accessing roles.
     */
    #isAllowedOrHiddenItem(permission)
    {
        // else it must match one of the roles in the hierarchy.
        let includedRoles = User.includedRoles[this.#role]

        // now check permissions. This will for every permissions entry check allowance and display.
        let permissionsArray = permission.split(",")
        // the $allowed_or_hidden integer carries the result as 0-3 reflecting two bits:
        // for permitted AND with 0x1, for hidden AND with 0x2
        let allowedOrHidden = 2; // default is not permitted, hidden
        for (let permissionsElement of permissionsArray) {
            let elementHidden = permissionsElement.startsWith(".")
            let elementRole = (elementHidden) ? permissionsElement.substring(1) : permissionsElement
            let elementAllowed = includedRoles.indexOf(elementRole) >= 0
            if (elementAllowed) {
                // add allowance if element is allowed
                allowedOrHidden = allowedOrHidden | 1
                // remove hidden flag, if allowed and not hidden.
                if (!elementHidden && ((allowedOrHidden & 2) > 0))
                allowedOrHidden -= 2
            }
        }
        // or meet the permitted subscriptions.
        if (this.#subscriptions > 0)
            allowedOrHidden = this.#addAllowedOrHiddenService(allowedOrHidden, permissionsArray, this.#subscriptions, "#");
        // or meet the permitted workflows.
        if (this.#workflows > 0)
            allowedOrHidden = this.#addAllowedOrHiddenService(allowedOrHidden, permissionsArray, this.#workflows, "@");
        // or meet the permitted concessions.
        if (this.#concessions > 0)
            allowedOrHidden = this.#addAllowedOrHiddenService(allowedOrHidden, permissionsArray, this.#concessions, "$");
        return allowedOrHidden;
    }

}