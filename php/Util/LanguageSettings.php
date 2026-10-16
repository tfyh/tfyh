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

namespace Util;

class LanguageSettings
{
    public String $code;
    public String $dateTemplate;
    public bool $decimalPoint;
    public function __construct(String $code, String $dateTemplate, bool $decimalPoint)
    {
        $this->code = $code;
        $this->dateTemplate = $dateTemplate;
        $this->decimalPoint = $decimalPoint;
    }
}