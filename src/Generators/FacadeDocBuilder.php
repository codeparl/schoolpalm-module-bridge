<?php
namespace SchoolPalm\ModuleBridge\Generators;

use ReflectionClass;
use ReflectionMethod;
use ReflectionType;
use ReflectionNamedType;
use ReflectionUnionType;

class FacadeDocBuilder
{
    protected array $imports = [];

    public function getImports(): array
    {
        return array_unique($this->imports);
    }

    public function scanInterfaceMethods(string $interface): array
    {
        $reflection = new ReflectionClass($interface);

        return $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    }

    public function buildFacadeAnnotations(array $methods): string
    {
        $this->imports = []; // reset per build

        $lines = [];

        foreach ($methods as $method) {

            $return = $this->formatType($method->getReturnType()) ?? 'mixed';

            $parameters = [];

            foreach ($method->getParameters() as $param) {

                $typeName = $this->formatType($param->getType()) ?? 'mixed';

                $parameters[] = "{$typeName} \${$param->getName()}";
            }

            $paramString = implode(', ', $parameters);

            $lines[] = " * @method static {$return} {$method->getName()}({$paramString})";
        }

        return "/**\n"
            . implode("\n", $lines)
            . "\n */";
    }

    /**
     * Format type → short name + collect imports
     */
    private function formatType(?ReflectionType $type): ?string
    {
        if (!$type) {
            return null;
        }

        // Union types
        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                fn($t) => $this->formatNamedType($t),
                $type->getTypes()
            ));
        }

        return $this->formatNamedType($type);
    }

    /**
     * Convert FQCN → short name + register import
     */
    private function formatNamedType(ReflectionNamedType $type): string
    {
        $name = $type->getName();

        // Skip primitive types
        if (in_array($name, ['int', 'string', 'bool', 'float', 'array', 'void', 'mixed'])) {
            return $type->allowsNull() && $name !== 'mixed'
                ? '?' . $name
                : $name;
        }

        // If class → import it
        if (str_contains($name, '\\')) {
            $this->imports[] = $name;
            $name = class_basename($name);
        }

        return $type->allowsNull()
            ? '?' . $name
            : $name;
    }
}