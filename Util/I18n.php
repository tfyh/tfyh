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
include_once "../_Util/Language.php";

class I18n {
    private static I18n $instance;
    public static function getInstance(): I18n {
        if (!isset(self::$instance))
            self::$instance = new I18n();
        return self::$instance;
    }

    private array $map;
    private bool $loaded;

    private function __construct() {
        $this->map = [];
        $this->loaded = false;
    }
    public function loadResource (Language $language): void
    {
        $this->map = [];
        $this->loaded = false;
        $i18nURI = "../i18n/" . $language->value . ".lrf";
        $lr_file = file_get_contents($i18nURI);
        if (!$lr_file) return;
        $lr_lines = explode("\n", $lr_file);
        $text = "";
        $token = "-";
        foreach ($lr_lines as $lr_line) {
            $pipe_at = strpos($lr_line, "|");
            if ($pipe_at !== false) {
                if ($pipe_at == 6) { // new language resource. Store current.
                    if (strlen($token) == 6)
                        $this->map[$token] = $text;
                    $token = substr($lr_line, 0, 6);
                    $text = substr($lr_line, 7);
                } elseif ($pipe_at == 0) { // continued multiline language resource text
                    $text .= "\n" . substr($lr_line, 1);
                }
            }
        }
        // add last entry
        $this->map[$token] = $text;
        $this->loaded = true;
    }

    public function isValidI18nReference(String $toCheck): bool
    {
        if ((strlen($toCheck) < 7) || (substr($toCheck, 6, 1) != "|"))
            return false;
        elseif (!$this->loaded)
            return false;
        else
            return (! is_null($this->map[substr($toCheck, 0, 6)]));
    }

    // translation and placeholder replacement
    function t(...$args)
    {
        if ((count($args) == 0) || is_null($args[0]))
            return "";
        $i18nResource = $args[0];
        if ((strlen($i18nResource) < 7) || (substr($i18nResource, 6, 1) != "|"))
            $text = $i18nResource;
        elseif (!$this->loaded)
            $text = substr($i18nResource, 7);
        else
            $text = $this->map[substr($i18nResource, 0, 6)] ?? substr($i18nResource, 7);
        for ($i = 1; $i < count($args); $i ++)
            $text = str_replace("%" . $i, $args[$i], $text);
        return $text;
    }

}

