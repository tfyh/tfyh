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

import org.tfyh.util.I18n

@Suppress("Unused")
object Findings {

    // monitoring
    private var errors = emptyArray<String>()
    private var warnings = emptyArray<String>()

    /* clear the TfyhData.errors and TfyhData.warnings  */
    fun clearFindings()
    {
        errors = emptyArray<String>()
        warnings = emptyArray<String>()
    }

    /**
     * Add a finding. Reason codes are:
     * ERRORS:
     * 1 Format error, 2 Numeric value required. 3 Exception raised, 4 mandatory field missing,
     * 5 illegal duplicate name, 6 any other error
     * WARNINGS:
     * 10 too small. Replaced, 11 too big. Replaced, 12 Unknown data type, 13 The value°s native type does not match the
     * data type, 14 The value°s data type does not match the native type, 15 String too long. Cut, 16 Value limits can
     * not be adjusted in lists, 17 any other warning.
     */
    fun addFinding(reasonCode: Int, violatingValueStr: String, violatedLimitStr: String = "")
    {
        val i18n = I18n.getInstance()
        when (reasonCode) {
            1 -> errors += (i18n.t("Im6RzC|Format error in °%1°.", violatingValueStr))
            2 -> errors += (i18n.t("4j2U0W|Numeric value required i...", violatingValueStr))
            3 -> errors += (i18n.t("RZEpM0|Exception raised when pa...", violatingValueStr, violatedLimitStr))
            4 -> errors += (i18n.t("nKI7OJ|The required field °%1° ...", violatingValueStr))
            5 -> errors += (i18n.t("fu97I0|Name °%1° is already use...", violatingValueStr, violatedLimitStr))
            6 -> errors += violatingValueStr // any other error
            10 -> warnings += (i18n.t("IL4ihl|°%1° is too small. Repla...", violatingValueStr, violatedLimitStr))
            11 -> warnings += (i18n.t("O0EFCI|°%1° is too big. Replace...", violatingValueStr, violatedLimitStr))
            12 -> warnings += (i18n.t("jN5Dvb|Unknown data type / vali...", violatingValueStr))
            13 -> warnings += (i18n.t("FHFWAq|The value°s native type ..."))
            14 -> warnings += (i18n.t(
                "R3IpuB|The value°s data type °%...", violatingValueStr))
            15 -> warnings += (i18n.t(
                "fWxdb7|String °%1° too long. Cu...", violatingValueStr, violatedLimitStr))
            16 -> warnings += (i18n.t(
                "2ofYtW|Value limits can not be ...", violatingValueStr))
            17 -> warnings += violatingValueStr // any other warning
        }
    }

    fun getErrors() = errors
    fun countErrors() = errors.size
    fun getWarnings() = warnings
    fun countWarnings() = warnings.size
    fun getFindings(includeWarnings: Boolean): String {
        var findingsStr = ""
        for (error in errors) findingsStr += error + "\n"
        if (includeWarnings)
            for (warning in warnings) findingsStr += warning + "\n"
        return findingsStr
    }

}
