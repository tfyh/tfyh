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

namespace Data;

use DateTimeImmutable;

include_once '../../tfyh/Data/ParserName.php';
include_once '../../tfyh/Data/Parser.php';

// internationalisation support needed to localise property label and description
use Util\I18n;
use Util\Language;

/**
 * Class representing a property with associated metadata and parsing logic. All properties are defined in the
 * descriptor configuration file. A property itself is defined by a name, a label, a description, and a parser to parse
 * its value.
 */
class Property {

    private String $propertyName;
    private String $propertyLabel;
    private String $propertyDescription;
    private ParserName $propertyParser;

    public function __construct (array $definition)
    {
        $this->propertyName = $definition['name'] ?? "missing_property_field__name";
        $this->propertyLabel = $definition['label'] ?? "missing_property_field__label";
        $this->propertyDescription = $definition['description'] ?? "missing_property_field__description";
        $this->propertyParser = ParserName::valueOfOrNone($definition['parser']);
    }

    public function name(): string { return $this->propertyName; }
    public function label(): string { return I18n::getInstance()->t($this->propertyLabel); }
    public function description(): string { return I18n::getInstance()->t($this->propertyDescription); }
    public function parser(Type $valueType): ParserName { return ($this->propertyParser == ParserName::NONE) ? $valueType->parser() : $this->propertyParser; }

    // and the property descriptor
    static array $descriptor = [];
    static array $invalidPropertyDefinition = [ "_name" => "invalid",
        "default_label" => "invalid", "default_description" => "invalid name for property used.", "parser" => "string" ];

    public static Property $invalid;

    /**
     * Parse a definition map into properties.
     * @param array $definition the definition map to parse.
     * @param Type $type the type of the item to parse the properties for. This is used to determine the parser to use.
     * @return array the parsed properties.
     */
    public static function parseProperties(array $definition, Type $type): array {
        $properties = [];
        foreach ($definition as $name => $propertyDefinition) {
            if (!is_null($propertyDefinition)) {
                // identify the parser to apply
                $propertyName = PropertyName::valueOfOrInvalid($name);
                $property = Property::$descriptor[$propertyName->value] ?? Property::$invalid;
                $propertyParser = $property->parser($type);
                // parse property but only use it, if not empty.
                if ($propertyName !== PropertyName::INVALID) {
                    $parsedProperty = Parser::parse($propertyDefinition, $propertyParser, Language::CSV);
                    if (!ParserConstraints::isEmpty($parsedProperty, $propertyParser))
                        $properties[$propertyName->value] = $parsedProperty;
                }
            }
        }
        return $properties;
    }

    /**
     * Sort a set of property name Strings according to the order provided in the PropertyName Enum.
     * @param array $propertyNames the property names to sort.
     * @return array the sorted property names.
     */
    public static function sortProperties(array $propertyNames): array {
        $sorted = [];
        foreach (array_column(PropertyName::cases(), 'value') as $propertyName)
            if (in_array($propertyName, $propertyNames))
                $sorted[] = $propertyName;
        return $sorted;
    }

    /**
     * Make sure that objects are really copied instead of referencing them for date and datetime.
     * @param mixed $value the value to copy
     * @return array|bool|DateTimeImmutable|float|int|mixed|string the copy or clone in case of DateTimeImmutable.
     */
    static function copyOfValue(mixed $value): mixed
    {
         if ((gettype($value) === "object") && (get_class($value) === "DateTimeImmutable"))
             return Parser::parse(Formatter::format($value, ParserName::DATETIME, Language::CSV),
                 ParserName::DATETIME, Language::CSV);
        else
            return $value;
    }

    private static array $isImmutable; // array initialisation needs language level PHP 8.2
    private static array $isValue;
    private static array $isActual;
    /**
     * Read-only properties must never change, i.e. must not be set except on type or item instantiation.
     * @param String $propertyName the name of the property to check.
     * @return bool whether the property is read-only.
     */
    public static function isImmutable(String $propertyName): bool {
        self::$isImmutable = array(PropertyName::_NAME->value, PropertyName::_PATH->value,
            PropertyName::VALUE_TYPE->value);
        return in_array($propertyName, self::$isImmutable);
    }
    /**
     * Value properties have no fixed parser but use the parser of the type.
     * @param String $propertyName the name of the property to check.
     * @return bool whether the property is a value property.
     */
    public static function isValue(String $propertyName): bool {
        self::$isValue = array(PropertyName::DEFAULT_VALUE->value, PropertyName::VALUE_MIN->value,
            PropertyName::VALUE_MAX, PropertyName::ACTUAL_VALUE->value);
        return in_array($propertyName, self::$isValue);
    }
    /**
     * Checks if the given property name is part of the list of properties considered as "actual" which are set by
     * the tenant. They are stored in a separate file
     * @param String $propertyName the name of the property to check.
     * @return bool whether the property is part of the list of actual properties.
     */
    public static function isActual(String $propertyName): bool {
        self::$isActual = array(PropertyName::ACTUAL_VALUE->value, PropertyName::ACTUAL_LABEL->value,
            PropertyName::ACTUAL_DESCRIPTION);
        return in_array($propertyName, self::$isActual);
    }
}