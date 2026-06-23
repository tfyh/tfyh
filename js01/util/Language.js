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

const Language = Object.freeze({
    EN: "en",
    DE: "de",
    FR: "fr",
    IT: "it",
    NL: "nl",
    CSV: "csv",
    SQL: "sql",

    settingsOf: function(language)
    {
        switch (language) {
            // NB: The date template is ignored because JavaScript uses its own in the Date.prototype.toLocaleDateString()
            case Language.EN: return new LanguageSettings("en", "d-m-Y", true);
            case Language.DE: return new LanguageSettings("de", "d.m.Y", false);
            case Language.FR: return new LanguageSettings("fr", "d/m/Y", true);
            case Language.IT: return new LanguageSettings("it", "d/m/Y", true);
            case Language.NL: return new LanguageSettings("nl", "d-m-Y", false);
            case Language.CSV: return new LanguageSettings("csv", "Y-m-d", true);
            case Language.SQL: return new LanguageSettings("sql", "Y-m-d", true);
            default: return new LanguageSettings("en", "Y-m-d", true);
        }
    },

    // name String to PropertyName resolution function
    valueOfOrDefault: function(name) {
        for (let language of Object.keys(Language))
            if (name === Language[language])
                return Language[language]
        return Language.DE
    }

});