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

use Stringable;
use DateTimeImmutable;

include_once "../_Data/Findings.php";
include_once "../_Data/Formatter.php";
include_once "../_Data/Validator.php";

use tfyh\control\LoggerSeverity;

include_once "../_Control/LoggerSeverity.php";

use tfyh\util\I18n;
use tfyh\util\Language;

include_once "../_Util/Language.php";

/**
 * The `Item` class represents a node within a hierarchical structure, containing a name, type, properties, and children.
 * It supports operations such as parsing definitions, validating its state, and searching through its hierarchy. The
 * Item is the core element of all configuration data and is the basis for the entire configuration system.
 *
 * This class also handles constraints and specific configurations, such as discouraged child names,
 * relationships with parent and child items, and mutability rules for properties.
 */
class Item implements Stringable  // Stringable explicitly mentioned to ensure that the PHPstorm debugger recognizes th __toString() function
{

    /**
     * @var Item the parent item, if there is one. Otherwise, this is pointing to itself, which is only possible for the
     * root item and the invalid item. The parent item must never change.
     */
    private Item $_parentItem;
    /**
     * @var string the name of the item. The name must never change.
     */
    private string $_name;
    /**
     * @var Type the type of the item. The type must never change.
     */
    private Type $_type;
    /**
     * @var array the properties of the item. Not all are given, but only those which differ from the type's default.
     */
    private array $properties;
    /**
     * @var array the children of the item. Their may be none.
     */
    private array $children;

    /**
     * @var bool this bool is set to true, if the item is part of the program's packaged configuration, i.e. was read by
     * parsing the config file provided in the "Config/packaged" directory. Defaults of these values may change with the
     * next release.
     */
    private bool $isPackaged;

    private static array $discouragedNames = [
        //  Array type
        "length", "at", "concat", "copyWithin", "entries", "every",
        "fill", "filter", "find", "findIndex", "findLast", "findLastIndex", "flat", "flatMap", "forEach", "from",
        "fromAsync", "includes", "indexOf", "isArray", "join", "keys", "lastIndexOf", "map", "of", "pop", "push",
        "reduce", "reduceRight", "reverse", "shift", "slice", "some", "sort", "splice", "toLocaleString",
        "toReversed", "toSorted", "toSpliced", "toString", "unshift", "values", "with",
        "__defineGetter__", "__defineSetter__", "__lookupGetter__",
        //  Object
        "__lookupSetter__", "assign", "create", "defineProperties", "defineProperty", "entries", "freeze",
        "fromEntries", "getOwnPropertyDescriptor", "getOwnPropertyDescriptors", "getOwnPropertyNames",
        "getOwnPropertySymbols", "getPrototypeOf", "groupBy", "hasOwn", "hasOwnProperty", "is", "isExtensible",
        "isFrozen", "isPrototypeOf", "isSealed", "keys", "preventExtensions", "propertyIsEnumerable", "seal",
        "setPrototypeOf", "toLocaleString", "toString", "valueOf", "values"
    ];

    /**
     * Create a free floating item. To be used for the "invalid item" and the config root node.
     * @param array $definition either the rot or the invalid item definition.
     * @return Item the floating item.
     */
    public static function getFloating(array $definition): Item
    {
        return new Item(null, $definition, true);
        // if packaged is set to true, because floating items are part of the default program configuration, although they
        // are not defined in a file within the Config/packaged directory.
    }

    /**
     * Sort all top-level branches according to the canonical sequence.
     */
    public static function sortTopLevel(): void
    {
        $sortCache = [];
        $config = Config::getInstance();
        foreach (Config::$allSettingsFiles as $topBranchName)
            if ($config->getItem("." . $topBranchName) !== $config->rootItem)
                $sortCache[] = $config->getItem("." . $topBranchName);
        $config->rootItem->children = $sortCache;
    }

    /**
     * construct a new child item based on the definition array which must at least contain the name and value_type
     * fields.
     * @param Item|null $parentItemSetter the parent item setter. If null, the item becomes its own parent.
     * @param array $definition the item's definition array.
     * @param bool $isPackaged whether the item is part of the program's packaged configuration, i.e. was read by
     * parsing the config file provided in the "Config/packaged" directory. Defaults of these values may change with the
     * next release.
     */
    private function __construct(Item|null $parentItemSetter, array $definition, bool $isPackaged)
    {
        $this->_name = $definition["_name"] ?? "missing_name";
        $this->isPackaged = $isPackaged;
        // check the name's validity
        if (PropertyName::valueOfOrInvalid($this->_name) !== PropertyName::INVALID) {
            $errorMessage = "Forbidden child name " . $this->_name . " detected at " . $parentItemSetter->getPath() .
                ". Aborting.";
            Config::getInstance()->logger->log(LoggerSeverity::ERROR, "Item->__construct", $errorMessage);
            echo $errorMessage;
            exit();
        }
        // check whether the name is discouraged.
        if (in_array($this->_name, Item::$discouragedNames)) {
            $errorMessage = "Discouraged child name " . $this->_name . " detected at " . $parentItemSetter->getPath() .
                ". Changed to " . $this->_name . "!";
            Config::getInstance()->logger->log(LoggerSeverity::ERROR, "Item->__construct", $errorMessage);
            $this->_name .= "!";
        }
        if (is_null($parentItemSetter))
            $this->_parentItem = $this;
        else {
            $this->_parentItem = $parentItemSetter;
            $this->_parentItem->children[] = $this;
        }
        // set the immutable properties
        $this->properties = [];
        $this->properties[PropertyName::_NAME->value] = $this->_name;
        $this->properties[PropertyName::_PATH->value] = (is_null($parentItemSetter)) ? "#none" : $parentItemSetter->getPath();
        $this->_type = Type::get(($definition["value_type"]) ?? "none"); // the null case must never happen
        $this->properties[PropertyName::VALUE_TYPE->value] = $this->_type->name();
        // set the children
        $this->children = [];
        if ($definition["value_type"] == "template") {
            // if it is a template, copy the template
            $templatePath = $definition["value_reference"] ?? "...";
            $templateItem = Config::getInstance()->getItem($templatePath);
            if ($templateItem !== Config::getInstance()->invalidItem) {
                foreach ($templateItem->children as $templateChild) {
                    $newChild = new Item($this, ["_name" => $templateChild->name(),
                        "value_type" => $templateChild->valueType()], $isPackaged);
                    $newChild->mergeProperties($templateChild->properties);
                }
            }
        }
        // parse the definition as properties and children's actual values.
        $this->parseDefinition($definition);
    }

    /**
     * Convenience function to simplify the validity check.
     */
    public function isValid(): bool
    {
        return ($this !== Config::getInstance()->invalidItem);
    }

    /**
     * Generic search function. Find a String (lower case ASCII only) in the item's name, label, description,
     * value, and all its children. The found strings are stored in the $found array.
     * @param string $lowerCaseAsciiFind the string to search for.
     * @param array $found the array to store the found strings in.
     * @return void
     */
    public function find(string $lowerCaseAsciiFind, array &$found): void
    {
        $ownPath = $this->getPath();
        if (str_contains(WordIndex::toLowerAscii($this->name()), $lowerCaseAsciiFind))
            $found[$ownPath] = $this->name();
        if (str_contains(WordIndex::toLowerAscii($this->label()), $lowerCaseAsciiFind))
            $found["$ownPath.label"] = $this->label();
        if (str_contains(WordIndex::toLowerAscii($this->description()), $lowerCaseAsciiFind))
            $found["$ownPath.description"] = $this->description();
        if (str_contains(WordIndex::toLowerAscii($this->valueStr()), $lowerCaseAsciiFind))
            $found["$ownPath.value"] = $this->valueStr();
        foreach ($this->children as $child)
            $child->find($lowerCaseAsciiFind, $found);
    }

    /**
     * setter functions for properties
     * @param string $propertyName the name of the property to set.
     * @param string $value the value to set.
     * @param Language $language the language to use for parsing.
     * @return void
     */
    public function parseProperty(string $propertyName, string $value, Language $language): void
    {
        $property = Property::$descriptor[$propertyName] ?? Property::$invalid;
        $propertyParser = $property->parser($this->type());
        // parse and take in, if not empty.
        if ($property !== Property::$invalid) {
            $parsedProperty = Parser::parse($value, $propertyParser, $language);
            if (!ParserConstraints::isEmpty($parsedProperty, $propertyParser))
                $this->properties[$propertyName] = $parsedProperty;
        }
    }

    /**
     * Parse a definition map into the item properties and its children's actual values. Overwrite but keep existing
     * properties which are not in $definition. Immutable properties and unmatched fields are skipped.
     * @param array $definition the definition map to parse.
     * @return void
     */
    public function parseDefinition(array $definition): void
    {
        $newProperties = Property::parseProperties($definition, $this->_type);
        $this->mergeProperties($newProperties);
        foreach ($this->children as $child)
            if (isset($definition[$child->name()]))
                $child->parseProperty("actual_value", $definition[$child->name()],
                    Config::getInstance()->language());
    }

    /**
     * Copy all $sourceProperties values into $this->properties except the immutable ones. Overwrite the existing,
     * but keep those which are not part of the $sourceProperties set.
     * @param array $sourceProperties the properties to copy.
     * @return void
     */
    private function mergeProperties(array $sourceProperties): void
    {
        foreach ($sourceProperties as $propertyName => $propertyValue)
            if (!Property::isImmutable($propertyName))
                if (!is_null($propertyValue))
                    $this->properties[$propertyName] = Property::copyOfValue($propertyValue);
    }

    /**
     * Clear this item from all children and properties and do this with all items of its entire
     * branch recursively. The item itself will stay as an empty stub. Remove it by the caller.
     */
    public function destroy(): void
    {
        // delete all information
        $this->properties = [];
        // then drill down
        foreach ($this->children as $child)
            $child->destroy();
        // clear the own children after they have cleared their properties
        $this->children = [];
    }

    // property getter functions
    // =========================
    // for all properties the getter will return a value. If the respective property is not set

    /**
     * get the parent item or, if there is none, this same item
     */
    public function name(): string
    {
        return $this->properties[PropertyName::_NAME->value] ?? ".invalid_name";
    }

    /**
     * Return the path property, which is different from teh getPath(), because it is the path of the parent. Cf. getPath()
     */
    public function path(): string
    {
        return $this->properties[PropertyName::_PATH->value] ?? ".invalid_path";
    }

    /**
     * @return Type the type of the item.
     */
    public function type(): Type
    {
        return $this->_type;
    }

    /**
     * @return Item the parent item or, if there is none, this same item.
     */
    public function parent(): Item
    {
        return $this->_parentItem;
    }

    /**
     * The defaultValue() is also used by the Record class; therefore, it is not private
     * @return bool|int|float|DateTimeImmutable|string|array the default value of the item.
     */
    function defaultValue(): bool|int|float|DateTimeImmutable|string|array
    {
        return $this->properties[PropertyName::DEFAULT_VALUE->value] ?? $this->_type->defaultValue();
    }

    // localised, i.e. translated properties

    /**
     * @return string the default label of the item in localized language.
     */
    private function defaultLabel(): string
    {
        return (isset($this->properties[PropertyName::DEFAULT_LABEL->value]))
            ? I18n::getInstance()->t($this->properties[PropertyName::DEFAULT_LABEL->value]) : $this->_type->defaultLabel();
    }

    /**
     * @return string the default description of the item in localized language.
     */
    private function defaultDescription(): string
    {
        return (isset($this->properties[PropertyName::DEFAULT_DESCRIPTION->value]))
            ? I18n::getInstance()->t($this->properties[PropertyName::DEFAULT_DESCRIPTION->value]) : $this->_type->defaultDescription();
    }

    public function nodeHandling(): string
    {
        return $this->properties[PropertyName::NODE_HANDLING->value] ?? $this->_type->nodeHandling();
    }

    public function nodeAddableType(): string
    {
        return $this->properties[PropertyName::NODE_ADDABLE_TYPE->value] ?? $this->_type->nodeAddableType();
    }

    public function nodeWritePermissions(): string
    {
        return $this->properties[PropertyName::NODE_WRITE_PERMISSIONS->value] ?? $this->_type->nodeWritePermissions();
    }

    public function nodeReadPermissions(): string
    {
        return $this->properties[PropertyName::NODE_READ_PERMISSIONS->value] ?? $this->_type->nodeReadPermissions();
    }

    public function valueType(): string
    {
        return $this->_type->name();
    }

    public function valueMin(): bool|int|float|DateTimeImmutable|string|array
    {
        return $this->properties[PropertyName::VALUE_MIN->value] ?? $this->_type->valueMin();
    }

    public function valueMax(): bool|int|float|DateTimeImmutable|string|array
    {
        return $this->properties[PropertyName::VALUE_MAX->value] ?? $this->_type->valueMax();
    }

    public function valueSize(): int
    {
        return $this->properties[PropertyName::VALUE_SIZE->value] ?? $this->_type->valueSize();
    }

    public function valueUnit(): string
    {
        return $this->properties[PropertyName::VALUE_UNIT->value] ?? $this->_type->valueUnit();
    }

    public function valueReference(): string
    {
        return $this->properties[PropertyName::VALUE_REFERENCE->value] ?? $this->_type->valueReference();
    }

    public function validationRules(): string
    {
        return $this->properties[PropertyName::VALIDATION_RULES->value] ?? $this->_type->validationRules();
    }

    public function sqlType(): string
    {
        return $this->properties[PropertyName::SQL_TYPE->value] ?? $this->_type->sqlType();
    }

    public function sqlNull(): bool
    {
        return $this->properties[PropertyName::SQL_NULL->value] ?? $this->_type->sqlNull();
    }

    public function sqlIndexed(): string
    {
        return $this->properties[PropertyName::SQL_INDEXED->value] ?? $this->_type->sqlIndexed();
    }

    public function inputType(): string
    {
        return $this->properties[PropertyName::INPUT_TYPE->value] ?? $this->_type->inputType();
    }

    public function inputModifier(): string
    {
        return $this->properties[PropertyName::INPUT_MODIFIER->value] ?? $this->_type->inputModifier();
    }

    public function recordEditForm(): string
    {
        return $this->properties[PropertyName::RECORD_EDIT_FORM->value] ?? $this->_type->recordEditForm();
    }

    /**
     * Get the actual values of the item and its children. The actual values are stored in the $csv string.
     * @param string $csv the string to append the actual values to.
     * @return void
     */
    public function getActualValues(string &$csv): void
    {
        $hasActualLabel = isset($this->properties[PropertyName::ACTUAL_LABEL->value]);
        $hasActualDescription = isset($this->properties[PropertyName::ACTUAL_DESCRIPTION->value]);
        $hasActualValue = isset($this->properties[PropertyName::ACTUAL_VALUE->value]);
        if ($hasActualLabel || $hasActualDescription || $hasActualValue) {
            $csv .= "\n" . $this->path() . ";" . $this->name() . ";";
            $csv .= (($hasActualLabel) ? Codec::encodeCsvEntry($this->properties[PropertyName::ACTUAL_LABEL->value]) : "") . ";";
            $csv .= (($hasActualDescription) ? Codec::encodeCsvEntry($this->properties[PropertyName::ACTUAL_DESCRIPTION->value]) : "") . ";";
            $csv .= ($hasActualValue) ? Codec::encodeCsvEntry($this->valueCsv()) : "";
        }
        foreach ($this->children as $child)
            $child->getActualValues($csv);
    }

    // value getter
    // ============
    /**
     * Get the value. This will return the actual or the item default if no actual was set. If the
     * item default is also not set, the type default is used.
     */
    public function value(): string|int|bool|float|DateTimeImmutable|array
    {
        return $this->properties[PropertyName::ACTUAL_VALUE->value] ?? $this->defaultValue();
    }

    // shorthand functions to get the value as String.
    public function valueCsv(): string
    {
        return Formatter::formatCsv($this->value(), $this->_type->parser());
    }

    public function valueSql(): string
    {
        return Formatter::format($this->value(), $this->_type->parser(), Language::SQL);
    }

    public function valueStr(): string
    {
        return Formatter::format($this->value(), $this->_type->parser());
    }

    // localised, i.e. translated properties
    public function label(): string
    {
        return (isset($this->properties[PropertyName::ACTUAL_LABEL->value]))
            ? I18n::getInstance()->t($this->properties[PropertyName::ACTUAL_LABEL->value]) : $this->defaultLabel();
    }

    public function description(): string
    {
        return (isset($this->properties[PropertyName::ACTUAL_DESCRIPTION->value]))
            ? I18n::getInstance()->t($this->properties[PropertyName::ACTUAL_DESCRIPTION->value]) : $this->defaultDescription();
    }

    public function isPackaged(): bool
    {
        return $this->isPackaged;
    }

    public function isOfAddableType(Item $item): bool
    {
        $itemTypeName = $item->type()->name();
        return (($itemTypeName == $this->nodeAddableType()) ||
            (($itemTypeName == "template") && ($item->valueReference() == $this->nodeAddableType())));
    }

    /**
     * Format the property value the "CSV language", but no csv encoding
     * @param string $propertyName the name of the property to format.
     * @return string the property value in CSV format.
     */
    private function propertyCsv(string $propertyName): string
    {
        if (!isset($this->properties[$propertyName]))
            return "";
        $propertyValue = $this->properties[$propertyName];
        $property = Property::$descriptor[$propertyName];
        return Formatter::format($propertyValue, $property->parser($this->_type), Language::CSV);
    }

    /**
     * Iterates through all children and returns true if the id was matched. If not,
     * false is returned.
     * @param string $name the name of the child to search for.
     * @return bool true if the child was found, false otherwise.
     */
    public function hasChild(string $name): bool
    {
        return !is_null($this->getChild($name));
    }

    /**
     * Returns the child with the given name, if existing. If not, returns null.
     * @param string $name the name of the child to search for.
     * @return Item|null the child with the given name, if existing, null otherwise. This is explicitly not returning
     * the invalid item in order to raise an exception, if an invalid name is looked up.
     */
    public function getChild(string $name): Item|null
    {
        foreach ($this->children as $child)
            if ($child->_name == $name)
                return $child;
        return null;
    }

    /**
     * @return array the children of the item.
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Returns the full path of the Item. The Item.path() will return the path property, which is the parent item's
     * path. For top level items getPath() will return ".topLevelName" and path() ""; for root and invalid getPath()
     * will return "" and path() "#none".
     */
    public function getPath(): string
    {
        if ($this === Config::getInstance()->rootItem)
            return "";
        $path = $this->_name;
        $current = $this;
        $passed = [$this->path()]; // path is a unique immutable String property of the item, not the "getPath" dynamic result.
        while ($current->parent() !== $current) {
            $current = $current->parent();
            if (in_array($current->path(), $passed)) {
                $recursionPath = $current->name() . "(#recursion#)." . $path;
                Config::getInstance()->logger->log(LoggerSeverity::ERROR, "Item.getPath()",
                    "Recursion detected in configuration. Please correct: " . $recursionPath);
                return $recursionPath;
            }
            $passed[] = $current->path();
            $path = ($current === Config::getInstance()->rootItem) ? ".$path" : $current->_name . ".$path";
        }
        return $path;
    }

    /**
     * Copy the $sourceItem's children to this item. Used by the Record class to propagate common record fields. No
     * drill down.
     * @param Item $sourceItem the item to copy the children from.
     * @param bool $isPackaged
     * @return void
     */
    function copyChildren(Item $sourceItem, bool $isPackaged): void
    {
        foreach ($sourceItem->children as $sourceChild)
            if (!$this->hasChild($sourceChild->_name)) {
                $ownChild = new Item($this, [
                    PropertyName::_NAME->value => $sourceChild->name(),
                    PropertyName::VALUE_TYPE->value => $sourceChild->type()->name()],
                    $isPackaged);
                $ownChild->mergeProperties($sourceChild->properties);
            }
    }

    /**
     * Reads the definition into a child item. This will create a new child if the child with the
     * name that is given in the definition does not exist. If the child exists, it will parse the definition into it
     * (see parseDefinition()). Returns false, only if the name or - for a not yet existing child - the value
     * type are missing in the definition or if the provided value type for a new child is invalid.
     * @param array $definition the definition of the child item.
     * @param bool $isPackaged true if the item is a packaged item, false if it is an added one.
     * @return bool true if the child was added, false otherwise.
     */
    public function putChild(array $definition, bool $isPackaged): bool
    {
        // a name and valid type must be provided in the definition
        if (!isset($definition["_name"]) || (strlen($definition["_name"]) == 0)) {
            Config::getInstance()->logger->log(LoggerSeverity::WARNING, "Item->putChildByDefinition",
                "Missing name for child of " . $this->getPath() . ".");
            return false;
        }
        $childName = $definition["_name"];
        // check whether the child already exists
        $child = $this->getChild($childName);
        if (!is_null($child)) {
            // if the child exists, replace the properties, but not the children
            $child->parseDefinition($definition);
            return true;
        }
        // for new children valid type must be provided in the definition
        if (!isset($definition["value_type"]) || (strlen($definition["value_type"]) == 0)) {
            Config::getInstance()->logger->log(LoggerSeverity::WARNING, "Item->putChildByDefinition",
                "Missing value type declaration for child of " . $this->getPath());
            return false;
        }
        $childTypeString = $definition["value_type"];
        $childType = Type::get($childTypeString);
        if ($childType === Type::$invalid) {
            Config::getInstance()->logger->log(LoggerSeverity::WARNING, "Item->putChildByDefinition",
                "Invalid value type declaration for child of " . $this->getPath());
            return false;
        }
        new Item($this, $definition, $isPackaged);
        return true;
    }

    /**
     * Remove the child item from this item's children array.
     * @param Item $child the child item to remove.
     * @return void
     */
    public function removeChild(Item $child): void
    {
        $childIndex = -1;
        $childName = $child->name();
        for ($i = 0; $i < count($this->children); $i++)
            if ($this->children[$i]->_name == $childName)
                $childIndex = $i;
        if ($childIndex >= 0)
            array_splice($this->children, $childIndex, 1);
    }

    /**
     * Validate value against this item's constraints and validation rules. Returns an updated value,
     * when adjusted by the limit checks. If a value is left out or set null, the items' actual
     * value will be validated, updated, and returned. See the Findings class to get errors and warnings.
     * @param mixed|null $value the value to validate.
     * @return bool|int|float|DateTimeImmutable|string|array the validated value.
     */
    public function validate(mixed $value = null): bool|int|float|DateTimeImmutable|string|array
    {
        // validate
        if (is_null($value))
            $validated = $this->properties[PropertyName::ACTUAL_VALUE->value] ?? ParserConstraints::empty($this->type()->parser());
        else {
            // empty values are always syntactically compliant
            if (ParserConstraints::isEmpty($value, $this->type()->parser()))
                return $value;
            $validated = $value;
        }
        // limit conformance
        $validated = Validator::adjustToLimits($validated, $this->_type, $this->valueMin(), $this->valueMax(), $this->valueSize());
        // validation rules conformance
        Validator::checkAgainstRule($validated, $this->validationRules());
        return $validated;
    }

    /**
     * @return string a readable String for debugging purposes
     */
    public function __toString(): string
    {
        if (strlen($this->valueCsv()) == 0)
            return $this->_name . " (" . $this->_type->name() . ") => " . $this->parent()->getPath();
        return $this->_name . "=" . $this->valueCsv() . " (" . $this->_type->name() . " => " . $this->parent()->getPath();
    }

    /**
     * Read a full branch from its definition array. If the branch contains items which are already existing,
     * the definitions will be merged using "putChild()".
     * @param array $definitionsArray the definition array of the branch.
     * @param bool $isPackaged true if the item is a packaged item, false if it is an added one.
     * @return string an error message, if any.
     */
    public function readBranch(array $definitionsArray, bool $isPackaged): string
    {
        $config = Config::getInstance();
        foreach ($definitionsArray as $definition) {
            // read the relative path
            $path = $definition["_path"];
            $name = $definition["_name"];
            if (!is_null($path) && !is_null($name)) {
                $parent = $config->getItem($path);
                if (!$parent->isValid())
                    // an invalid parent means that the path could not be resolved
                    return "Failed to find parent '$path' for child '$name' reading for " . $this->getPath();
                else {
                    $success = $parent->putChild($definition, $isPackaged);
                    if (!$success)
                        // adding can fail if child names are duplicate
                        return "Failed to add child '$name' at $path";
                }
            }
        }
        return "";
    }

    /**
     * Collect all items of a branch for saving them as a csv file.
     * @param bool $packaged true if only the packaged items shall be collected, false if only the added items shall
     * be collected.
     * @param array $items the array to collect the items into.
     * @param array $fieldNames the array to collect the field names into.
     * @param int $drillDown the maximum depth of the branch to collect.
     * @param int $level the currently reached level of the branch.
     * @return void
     */
    private function collectItems(bool $packaged, array &$items, array &$fieldNames, int $drillDown, int $level = 0): void
    {
        foreach ($this->children as $child) {
            if ($child !== $this) {
                if ($child->isBasic() == $packaged) {
                    // avoid endless drill down loops. Misconfiguration can cause such situations
                    $items[] = $child;
                    foreach ($child->properties as $propertyName => $propertyValue)
                        if (!in_array($propertyName, $fieldNames))
                            $fieldNames[] = $propertyName;
                }
                if ($level < $drillDown)
                    $child->collectItems($packaged, $items, $fieldNames, $drillDown, $level + 1);
            }
        }
    }

    /**
     * Sort all children of this item in alphabetical order of their names. No drill down.
     */
    public function sortChildrenByName(): void
    {
        $sorter = [];
        foreach ($this->children as $child)
            $sorter[$child->name()] = $child;
        ksort($sorter);
        $this->children = [];
        foreach ($sorter as $child)
            $this->children[] = $child;
    }

    /**
     * Sort all children to get all branches first or last, but do not change the inner sequence of
     * branches and leaves.
     * @param int $drillDown the maximum depth of the branch to sort.
     * @param bool $branchesFirst true if the branches shall be sorted first, false if the leaves shall be sorted first.
     * @return void
     */
    function sortChildren(int $drillDown, bool $branchesFirst): void
    {
        // split children into branches and leafs
        $branchItems = [];
        $leafItems = [];
        foreach ($this->children as $child) {
            if ((count($child->children) > 0) || (strlen($child->nodeAddableType()) > 0))
                $branchItems[] = $child;
            else
                $leafItems[] = $child;
        }

        // now rearrange the children according to the rearranged names.
        $this->children = ($branchesFirst) ? array_merge($branchItems, $leafItems) : array_merge($leafItems, $branchItems);

        // go for further levels, if required.
        if ($drillDown > 0)
            foreach ($this->children as $child)
                if ($child !== $this)
                    // avoid endless drill down loops. Misconfiguration can cause such situations
                    $child->sortChildren($drillDown - 1, $branchesFirst);
    }

    /**
     * Get the entire branch as a csv table.
     * @param int $drillDown the maximum depth of the branch to collect.
     * @param bool $packaged true if only the packaged items shall be collected, false if only the added items shall
     * be collected.
     * @return string the branch as a csv table.
     */
    public function branchToCsv(int $drillDown, bool $packaged): string
    {
        $items = ($this->isPackaged() == $packaged) ? [$this] : []; // packaged config files include their root item
        $fieldNames = array_keys($this->properties);
        $this->sortChildren($drillDown, false);
        $this->collectItems($packaged, $items, $fieldNames, $drillDown);
        $fieldNamesSorted = Property::sortProperties($fieldNames);
        $header = "";
        foreach ($fieldNamesSorted as $fieldName)
            $header .= ";" . $fieldName;
        $csv = substr($header, 1) . "\n";
        foreach ($items as $item) {
            $rowCsv = "";
            foreach ($fieldNamesSorted as $fieldName)
                $rowCsv .= ";" . Codec::encodeCsvEntry($item->propertyCsv($fieldName));
            $csv .= substr($rowCsv, 1) . "\n";
        }
        return $csv;
    }

    /**
     * @return int the depth of the item in the branch, 0 being the root item..
     */
    public function getLevel(): int
    {
        if (($this === Config::getInstance()->rootItem) || !$this->isValid())
            return 0;
        return count(explode(".", $this->getPath())) - 1;
    }

    /**
     * Move a child branch up or down in the children sequence. PHP associative arrays are ordered, See:
     * https://stackoverflow.com/questions/10914730/are-php-associative-arrays-ordered
     *
     * @param Item $item
     *            the item to move
     * @param int $by
     *            the number of places to move. +1 to move down, -1 to move up
     * @return bool true in case of success
     */
    public function moveChild(Item $item, int $by): bool
    {
        if ($by == 0) // nothing to move
            return true;

        // identify the current and new item position
        $parent = $item->parent();
        $from_position = array_search($item, $parent->children);
        $to_position = $from_position + $by;
        // do not move if target position is beyond the ends
        if (($to_position >= count($parent->children)) || ($to_position < 0))
            return false;

        // now move the names in between $from_position and $to_position
        // this will duplicate the name at the $to_position
        $end = abs($by);
        $fwd = $by / $end;
        for ($i = 1; $i <= $end; $i++)
            $parent->children[$from_position + (($i - 1) * $fwd)] = $parent->children[$from_position + ($i * $fwd)];
        // replace the name at the $to_position by the cached name
        $parent->children[$to_position] = $item;
        return true;
    }

}
