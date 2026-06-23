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

class Findings {

    // monitoring
    static errors = []
    static warnings = []

    /* clear the TfyhData.errors and TfyhData.warnings  */
    static clearFindings()
    {
        Findings.errors = []
        Findings.warnings = []
    }

    /**
     * Add a finding. Reason codes are:
     * ERRORS:
     * 1 Format error, 2 Numeric value required. 3 Exception raised, 4 mandatory fields were missing,
     * 5 illegal duplicate names, 6 any other error
     * WARNINGS:
     * 10 too small. Replaced, 11 too big. Replaced, 12 Unknown data type, 13 The value°s native type does not match the
     * data type, 14 The value°s data type does not match the native type, 15 String too long. Cut, 16 Value limits cannot
     * be adjusted in lists, 17 any other warning.
     */
    static addFinding(reasonCode, violatingValueStr, violatedLimitStr = "")
    {
        switch (reasonCode) {
            case 1: Findings.errors.push(i18n.t("Im6RzC|Format error in °%1°.", violatingValueStr)); break;
            case 2: Findings.errors.push(i18n.t("4j2U0W|Numeric value required i...", violatingValueStr)); break;
            case 3: Findings.errors.push(i18n.t("vyIEGu|Exception raised when pa...", violatingValueStr, violatedLimitStr)); break;
            case 4: Findings.errors.push(i18n.t("nKI7OJ|The required field °%1° ...", violatingValueStr)); break;
            case 5: Findings.errors.push(i18n.t("fu97I0|Name °%1° is already use...", violatingValueStr, violatedLimitStr)); break;
            case 6: Findings.errors.push(violatingValueStr); break; // any other error
            case 10: Findings.warnings.push(i18n.t("IL4ihl|°%1° is too small. Repla...", violatingValueStr, violatedLimitStr)); break;
            case 11: Findings.warnings.push(i18n.t("O0EFCI|°%1° is too big. Replace...", violatingValueStr, violatedLimitStr)); break;
            case 12: Findings.warnings.push(i18n.t("jN5Dvb|Unknown data type / vali...", violatingValueStr)); break;
            case 13: Findings.warnings.push(i18n.t("FHFWAq|The value°s native type ...")); break;
            case 14: Findings.warnings.push(i18n.t("R3IpuB|The value°s data type °%...", violatingValueStr)); break;
            case 15: Findings.warnings.push(i18n.t("fWxdb7|String °%1° too long. Cu...", violatingValueStr, violatedLimitStr)); break;
            case 16: Findings.warnings.push(i18n.t("sBg7LI|Value limits can not be ...", violatingValueStr)); break;
            case 17: Findings.warnings.push(violatingValueStr); break; // any other warning
            default: Findings.errors.push(violatingValueStr); break; // any other error
        }
    }

    static getErrors() { return Findings.errors; }
    static countErrors() { return Findings.errors.size; }
    static getWarnings() { return Findings.warnings; }
    static countWarnings() { return Findings.warnings.size; }
    static getFindings(includeWarnings) {
        let findingsStr = ""
        for (let error of Findings.errors) findingsStr += error + "\n"
        if (includeWarnings)
            for (let warning in Findings.warnings) findingsStr += warning + "\n"
        return findingsStr
    }

}
