<?php
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
namespace tfyh\util;

include_once '../../tfyh/Util/LanguageSettings.php';

enum Language: string
{
    case EN = "en";
    case DE = "de";
    case FR = "fr";
    case IT = "it";
    case NL = "nl";
    case CSV = "csv";
    case SQL = "sql";

    public static function settingsOf(Language $language): LanguageSettings
    {
        return match ($language) {
            Language::EN => new LanguageSettings("en", "d-m-Y", true),
            Language::DE => new LanguageSettings("de", "d.m.Y", false),
            Language::FR => new LanguageSettings("fr", "d/m/Y", true),
            Language::IT => new LanguageSettings("it", "d/m/Y", true),
            Language::NL => new LanguageSettings("nl", "d-m-Y", false),
            Language::CSV => new LanguageSettings("csv", "Y-m-d", true),
            Language::SQL => new LanguageSettings("sql", "Y-m-d", true)
        };
    }

    public static function valueOfOrDefault(string $name): Language
    {
        return Language::tryFrom(strtolower($name)) ?? Language::DE;
    }

}