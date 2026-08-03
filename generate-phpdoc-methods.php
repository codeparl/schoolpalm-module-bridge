<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

if ($argc < 2) {
    echo "Usage: php generate-phpdoc-methods.php Fully\\Qualified\\ClassName [--static]\n";
    exit(1);
}

$class = $argv[1];
$isStatic = in_array('--static', $argv, true);

if (! class_exists($class) && ! interface_exists($class)) {
    echo "Class or Interface [{$class}] does not exist.\n";
    exit(1);
}

$reflection = new ReflectionClass($class);

$ignored = [
    '__construct',
    '__destruct',
    '__clone',
    '__call',
    '__callStatic',
    '__get',
    '__set',
    '__isset',
    '__unset',
    '__toString',
    '__invoke',
];

$imports = [];
$docLines = [];

foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
    if (in_array($method->getName(), $ignored, true)) {
        continue;
    }

    /*
    |--------------------------------------------------------------------------
    | Ignore inherited methods from parent classes / traits
    |--------------------------------------------------------------------------
    */
    if ($method->getDeclaringClass()->getName() !== $class) {
        continue;
    }

    $returnType = formatType($method->getReturnType(), $class, $imports);
    $parameters = [];

    foreach ($method->getParameters() as $parameter) {
        $paramStr = '';

        if ($parameter->hasType()) {
            $paramStr .= formatType($parameter->getType(), $class, $imports) . ' ';
        }

        if ($parameter->isVariadic()) {
            $paramStr .= '...';
        }

        $paramStr .= '$' . $parameter->getName();

        if ($parameter->isOptional() && $parameter->isDefaultValueAvailable()) {
            $paramStr .= ' = ' . exportDefaultValue($parameter->getDefaultValue());
        }

        $parameters[] = $paramStr;
    }

    $signature    = implode(', ', $parameters);
    $staticPrefix = $isStatic ? 'static ' : '';

    $docLines[] = " * @method {$staticPrefix}{$returnType} {$method->getName()}({$signature})";
}

// Format Use Statements cleanly at the top
sort($imports);
$imports = array_unique($imports);

if (! empty($imports)) {
    foreach ($imports as $import) {
        echo "use {$import};\n";
    }
    echo "\n";
}

echo "/**\n";
foreach ($docLines as $line) {
    echo $line . "\n";
}
echo " */\n";

/**
 * Format type hints (Handles Named, Union, Intersection, and Nullable types).
 */
function formatType(?ReflectionType $type, string $targetClass, array &$imports): string
{
    if (! $type) {
        return 'mixed';
    }

    if ($type instanceof ReflectionNamedType) {
        return formatNamedType($type, $targetClass, $imports);
    }

    if ($type instanceof ReflectionUnionType) {
        $types = array_map(
            fn($t) => formatNamedType($t, $targetClass, $imports),
            $type->getTypes()
        );

        return implode('|', $types);
    }

    if ($type instanceof ReflectionIntersectionType) {
        $types = array_map(
            fn($t) => formatNamedType($t, $targetClass, $imports),
            $type->getTypes()
        );

        return implode('&', $types);
    }

    return 'mixed';
}

function formatNamedType(ReflectionNamedType $type, string $targetClass, array &$imports): string
{
    $name = $type->getName();

    // Handle fluent return self / static / current class
    if ($name === $targetClass || $name === 'self' || $name === 'static') {
        return 'self';
    }

    // Built-in types (string, int, bool, array, mixed, void, etc.)
    if ($type->isBuiltin()) {
        return ($type->allowsNull() && $name !== 'mixed') ? '?' . $name : $name;
    }

    // Qualified Class Names -> Add to imports and use Short Name
    $imports[] = $name;
    $shortName = (new ReflectionClass($name))->getShortName();

    return $type->allowsNull() ? '?' . $shortName : $shortName;
}

/**
 * Format parameter default values cleanly into PHP representation.
 */
function exportDefaultValue(mixed $value): string
{
    if (is_array($value)) {
        return '[]';
    }
    if (is_null($value)) {
        return 'null';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_string($value)) {
        return "'" . addslashes($value) . "'";
    }

    return var_export($value, true);
}
