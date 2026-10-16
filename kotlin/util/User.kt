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

import org.tfyh.data.Codec
import org.tfyh.data.Config
import org.tfyh.data.Ids

class User private constructor(
) {

    private var userId = -1
    private var firstName = "First"
    private var lastName = "Last"
    private var uuid = Ids.NIL_UUID
    private var role = "anonymous"
    private var subscriptions = 0
    private var workflows = 0
    private var concessions = 0
    private var preferences = ""

    companion object {
        private var isPrivilegedRole = mutableMapOf<String, Boolean>()
        var includedRoles = mutableMapOf<String, List<String>>()
        private val instance = User()
        fun getInstance() = instance
        fun setIncludedRoles() {
            val roles = Config.getInstance().getItem(".access.roles")
            for (role in roles.getChildren()) {
                val mainRole = role.name()
                var includedRoles = role.valueStr()
                val isPrivilegedRole = includedRoles.startsWith("*")
                User.isPrivilegedRole[mainRole] = isPrivilegedRole
                if (isPrivilegedRole)
                    includedRoles = includedRoles.substring(1)
                this.includedRoles[mainRole] = includedRoles.split(",")
            }
        }
    }

    /**
     * Set the session user. The change will not be notified, but must be notified to the
     * application by the caller of this setter which shall only be the ApiHandler
     */
    internal fun set(csv: String) {
        val rows = Codec.csvToMap(csv)
        userId = -1
        firstName = "First"
        lastName = "Last"
        uuid = Ids.NIL_UUID
        role = "anonymous"
        workflows = 0
        subscriptions = 0
        concessions = 0
        preferences = ""
        if (rows.isEmpty())
            return
        val fields = rows[0]
        try {
            userId = (fields["user_id"] ?: "-1").toInt()
            firstName = fields["first_name"] ?: "First"
            lastName = fields["last_name"] ?: "Last"
            uuid = fields["uuid"] ?: Ids.NIL_UUID
            role = fields["role"] ?: "anonymous"
            subscriptions = (fields["subscriptions"] ?: "0").toInt()
            workflows = (fields["workflows"] ?: "0").toInt()
            concessions = (fields["concessions"] ?: "0").toInt()
            preferences = fields["preferences"] ?: ""
        } catch (_: Exception) {}
    }

    /* ======================== Access Control ============================== */

    fun userId() = userId
    @Suppress("Unused")
    fun firstName() = firstName
    @Suppress("Unused")
    fun lastName() = lastName
    @Suppress("Unused")fun fullName() = "$firstName $lastName"

    fun uuid() = uuid
    fun role() = role
    @Suppress("Unused")
    fun subscriptions() = subscriptions
    fun workflows() = workflows
    @Suppress("Unused")
    fun concessions() = concessions
    @Suppress("Unused")
    fun preferences() = preferences

    fun isHiddenItem(permission: String) = (isAllowedOrHiddenItem(permission) and 2) > 0
    fun isAllowedItem(permission: String) = (isAllowedOrHiddenItem(permission) and 1) > 0

    /**
     * Check for workflows, concessions and subscriptions whether they are allowed for the current user.
     */
    private fun addAllowedOrHiddenService(allowedOrHidden: Int, permissionsArray: List<String>,
        services: Int, serviceIdentifier: String): Int
    {
        var allowedOrHiddenNew = allowedOrHidden
        for (permissionsElement in permissionsArray) {
            if (permissionsElement.contains(serviceIdentifier)) {
                val elementHidden = permissionsElement.startsWith(".")
                val elementServiceMap = permissionsElement.substring(if (elementHidden) 2 else 1).toInt()
                val elementAllowed = (services and elementServiceMap) > 0
                if (elementAllowed) {
                    // add allowance, if element is allowed
                    allowedOrHiddenNew = allowedOrHiddenNew or 1
                    // remove hidden flag, if allowed and not hidden.
                    if (!elementHidden && ((allowedOrHidden and 2) > 0))
                        allowedOrHiddenNew -= 2
                }
            }
        }
        return allowedOrHiddenNew
    }

    /**
     * Check whether a role shall get access to the given item and, if so, whether it should be displayed in
     * the menu. The role will be expanded according to the hierarchy and all included roles are as well
     * checked, except it is preceded by a '!'. If the permission String is preceded by a "." the menu will
     * not be shown, but accessible - same for all accessing roles.
     */
    private fun isAllowedOrHiddenItem(permission: String): Int
    {
        // else it must match one of the role in the hierarchy.
        val rolesOfHierarchy = includedRoles[role] ?: listOf()

        // now check permissions. This will for every permissions entry check allowance and display.
        val permissionsArray = permission.split(",")
        // the $allowed_or_hidden integer carries the result as 0-3 reflecting two bits:
        // for permitted AND with 0x1, for hidden AND with 0x2
        var allowedOrHidden = 2 // default is not permitted, hidden
        for (permissionsElement in permissionsArray) {
            val elementHidden = permissionsElement.startsWith(".")
            val elementRole = if (elementHidden) permissionsElement.substring(1) else permissionsElement
            val elementAllowed = rolesOfHierarchy.indexOf(elementRole) >= 0
            if (elementAllowed) {
                // add allowance, if element is allowed
                allowedOrHidden = allowedOrHidden or 1
                // remove hidden flag, if allowed and not hidden.
                if (!elementHidden && ((allowedOrHidden and 2) > 0))
                    allowedOrHidden -= 2
            }
        }
        // or meet the permitted subscriptions.
        if (subscriptions > 0)
            allowedOrHidden = addAllowedOrHiddenService(allowedOrHidden, permissionsArray, subscriptions, "#")
        // or meet the permitted workflows.
        if (workflows > 0)
            allowedOrHidden = addAllowedOrHiddenService(allowedOrHidden, permissionsArray, workflows, "@")
        // or meet the permitted concessions.
        if (concessions > 0)
            allowedOrHidden = addAllowedOrHiddenService(allowedOrHidden, permissionsArray, concessions, "$")
        return allowedOrHidden
    }

}