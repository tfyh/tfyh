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

namespace tfyh\data;

use DateTimeImmutable;

include_once '../../tfyh/Data/PropertyName.php';

use tfyh\util\I18n;

/**
 * The Type class represents a descriptor for a specific type, containing properties, its parser,
 * and related metadata used in managing and interpreting data values within the system. Types are immutable
 * and do never have actual values.
 *
 * This class provides functionality for initialising, retrieving, and working with types
 * and their associated metadata. It ensures the immutability of types and encapsulates logic
 * for interpreting type properties, constraints, and behaviours.
 */
class Type
{

    // The list of types available
    public static array $types = array();
    public static Type $invalid;

    /**
     * Initialise the descriptor and types
     * @param string $descriptorCsv the csv file containing the descriptor definition
     * @param string $typesCsv the csv file containing the type definitions
     * @return void
     */
    public static function init(string $descriptorCsv, string $typesCsv): void
    {
        // initialise descriptor
        $definitionsArray = Codec::csvToMap($descriptorCsv);
        Property::$descriptor = [];
        Property::$invalid = new Property(Property::$invalidPropertyDefinition); // no static initialisation possible
        Property::$descriptor[PropertyName::INVALID->name] = Property::$invalid;
        foreach ($definitionsArray as $definition)
            Property::$descriptor[PropertyName::valueOfOrInvalid($definition["name"])->value] = new Property($definition);
        // initialise type catalogue
        $definitionsArray = Codec::csvToMap($typesCsv);
        Type::$types = [];
        foreach ($definitionsArray as $definition)
            Type::$types[$definition["_name"] ?? "Oops! No name."] = new Type($definition);
        self::$invalid = new Type([ "_name" => "invalid", "default_label" => "invalid", "parser" => "none"]);
    }

    /**
     * Retrieves a type based on the provided type name. If there is no match, return Type::$invalid, which is
     *  created during bootstrap.
     *
     * @param string $typeName The name of the type to retrieve.
     * @return mixed The corresponding type if found, or an invalid type if not found.
     */
    public static function get(string $typeName): mixed
    { return self::$types[$typeName] ?: Type::$invalid; }

    // the type properties define the content of the value, e.g. its limits, its parser, its form input
    // and SQL representation asf.
    public string $name;
    public ParserName $parser;
    private array $properties;

    /**
     * Constructs a new instance using the given definition array. Initialises the name, parser,
     * and properties based on the provided input, ensuring that required keys are parsed
     * and immutable properties are correctly set.
     *
     * @param array $definition The associative array containing the definition data for initialization.
     * @return void
     */
    public function __construct(array $definition)
    {
        $this->name = isset($definition["_name"]) ? strval($definition['_name']) : "missing_type_name";
        $this->parser = isset($definition["parser"]) ? ParserName::valueOfOrNone($definition['parser']) : ParserName::NONE;
        // set the properties. Parse them first and ensure the immutable properties are set.
        $this->properties = Property::parseProperties($definition, $this);
        $this->properties[PropertyName::_NAME->value] = $this->name;
        $this->properties[PropertyName::_PATH->value] = "";
        $this->properties[PropertyName::VALUE_TYPE->value] = $this->name;
    }

    public function name(): String { return $this->name; }
    public function label(): String { return $this->defaultLabel(); }
    public function description(): String { return $this->defaultDescription(); }

    public function parser(): ParserName {
        return $this->parser;
    }
    public function defaultValue(): bool|int|float|DateTimeImmutable|string|array {
        return $this->properties[PropertyName::DEFAULT_VALUE->value] ?? ParserConstraints::empty($this->parser);
    }
    // localised, i.e. translated properties
    public function defaultLabel(): string {
        return I18n::getInstance()->t(strval($this->properties[PropertyName::DEFAULT_LABEL->value] ?? ""));
    }
    public function defaultDescription(): string {
        return I18n::getInstance()->t(strval($this->properties[PropertyName::DEFAULT_DESCRIPTION->value] ?? ""));
    }
    // property getter functions
    public function nodeHandling(): string { return strval($this->properties[PropertyName::NODE_HANDLING->value] ?? ""); }
    public function nodeAddableType(): string { return strval($this->properties[PropertyName::NODE_ADDABLE_TYPE->value] ?? ""); }
    public function nodeWritePermissions(): string { return strval($this->properties[PropertyName::NODE_WRITE_PERMISSIONS->value] ?? ""); }
    public function nodeReadPermissions(): string { return strval($this->properties[PropertyName::NODE_READ_PERMISSIONS->value] ?? ""); }

    // Types are immutable and do not have actual values.
    public function valueMin(): bool|int|float|DateTimeImmutable|string|array {
        return $this->properties[PropertyName::VALUE_MIN->value] ?? ParserConstraints::min($this->parser);
    }
    public function valueMax(): bool|int|float|DateTimeImmutable|string|array {
        return $this->properties[PropertyName::VALUE_MAX->value] ?? ParserConstraints::max($this->parser);
    }
    public function valueSize(): int { return intval($this->properties[PropertyName::VALUE_SIZE->value] ?? "0"); }
    public function valueUnit(): string { return $this->properties[PropertyName::VALUE_UNIT->value] ?? ""; }
    public function valueReference(): string { return $this->properties[PropertyName::VALUE_REFERENCE->value] ?? ""; }
    public function validationRules(): string { return $this->properties[PropertyName::VALIDATION_RULES->value] ?? ""; }
    public function sqlType(): string { return $this->properties[PropertyName::SQL_TYPE->value] ?? ""; }
    public function sqlNull(): bool { return (bool) $this->properties[PropertyName::SQL_NULL->value]; }
    public function sqlIndexed(): string { return $this->properties[PropertyName::SQL_INDEXED->value] ?? ""; }
    public function inputType(): string { return $this->properties[PropertyName::INPUT_TYPE->value] ?? "text"; }
    public function inputModifier(): string { return $this->properties[PropertyName::INPUT_MODIFIER->value] ?? ""; }
    public function recordEditForm(): string { return $this->properties[PropertyName::RECORD_EDIT_FORM->value] ?? ""; }

    public function __toString() { return $this->name; }

}
