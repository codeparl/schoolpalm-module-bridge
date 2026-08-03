<?php

namespace SchoolPalm\ModuleBridge\Generators;

use ReflectionMethod;
use ReflectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use SchoolPalm\ModuleBridge\Profiles\ContractProfile;

class StubBuilder
{
    protected array $imports = [];

    public function getImports(): array
    {
        return array_unique($this->imports);
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN ENTRY (PROFILE-AWARE)
    |--------------------------------------------------------------------------
    */

    public function buildMethodStub(
        ReflectionMethod $method,
        ?string $dtoClass = null,
        ContractProfile $profile
    ): string {

        $methodName = $method->getName();

        $parameters = $this->buildParameters($method, $dtoClass, $profile);
        $returnType = $this->buildReturnType($method, $dtoClass, $profile);
        $defaultReturn = $this->getDefaultReturnValue($method);

        return <<<PHP

    /**
     * Auto-generated SDK scaffold method
     */
    public function {$methodName}({$parameters}){$returnType}
    {
        // TODO: implement {$methodName}
        {$defaultReturn}
    }

PHP;
    }

    /*
    |--------------------------------------------------------------------------
    | DATA FACTORY VERSION (PROFILE-AWARE)
    |--------------------------------------------------------------------------
    */

    public function buildMethodStubWithFactory(
        ReflectionMethod $method,
        ?string $dtoClass = null,
        ?string $dataFactoryClass = null,
        ContractProfile $profile
    ): string {

        $methodName = $method->getName();

        $parameters = $this->buildParameters($method, $dtoClass, $profile);
        $returnType = $this->buildReturnType($method, $dtoClass, $profile);

        $factoryCall = $this->buildFactoryCall(
            $method,
            $dataFactoryClass,
            $profile
        );

        return <<<PHP

    /**
     * Auto-generated SDK scaffold method (DataFactory-backed)
     */
    public function {$methodName}({$parameters}){$returnType}
    {
        {$factoryCall}
    }

PHP;
    }

    /*
    |--------------------------------------------------------------------------
    | FACTORY CALL (PROFILE-AWARE)
    |--------------------------------------------------------------------------
    */

    protected function buildFactoryCall(
        ReflectionMethod $method,
        ?string $dataFactoryClass,
        ContractProfile $profile
    ): string {

        if (!$dataFactoryClass) {
            return "// No DataFactory available";
        }

        $factory = class_basename($dataFactoryClass);
        $methodName = $method->getName();

        $params = $method->getParameters();

        $idVar = null;
        $dataVar = null;

        foreach ($params as $param) {
            if ($param->getName() === 'id') {
                $idVar = '$id';
            }

            if ($param->getName() === 'data') {
                $dataVar = '$data->toArray()';
            }
        }

        $call = match ($methodName) {

            'all' => "app({$factory}::class)->all();",
            'first' => "app({$factory}::class)->first();",

            'get', 'find' =>
                "app({$factory}::class)->find({$idVar});",

            'store', 'create' =>
                "app({$factory}::class)->create({$dataVar});",

            'update' =>
                "app({$factory}::class)->update({$idVar}, {$dataVar});",

            'delete', 'destroy' =>
                "app({$factory}::class)->delete({$idVar});",

            default =>
                "app({$factory}::class)->{$methodName}();",
        };

        /*
        |--------------------------------------------------------------------------
        | PROFILE TRANSFORMATION LAYER
        |--------------------------------------------------------------------------
        */

        if ($profile->type === 'api') {
            return "return {$call}"; // DTO expected upstream
        }

        if ($profile->type === 'internal') {
            return "return {$call}"; // raw allowed
        }

        if ($profile->type === 'snapshot') {
            return "return {$call}"; // read-only optimized
        }

        return "return {$call}";
    }

    /*
    |--------------------------------------------------------------------------
    | PARAMETERS (PROFILE-AWARE)
    |--------------------------------------------------------------------------
    */

    private function buildParameters(
        ReflectionMethod $method,
        ?string $dtoClass = null,
        ContractProfile $profile
    ): string {

        $params = [];

        foreach ($method->getParameters() as $param) {

            $type = $param->getType();
            $typeString = '';

            if ($type) {
                $resolvedType = $this->resolveType($type);

                /*
                |--------------------------------------------------------------------------
                | API PROFILE ENFORCES DTO INPUT
                |--------------------------------------------------------------------------
                */
                if ($profile->type === 'api'
                    && $dtoClass
                    && $param->getName() === 'data'
                ) {
                    $resolvedType = $this->importAndShorten($dtoClass);
                }

                $typeString = $resolvedType . ' ';
            }

            $default = '';

            if ($param->isDefaultValueAvailable()) {
                $defaultValue = var_export($param->getDefaultValue(), true);
                $default = " = {$defaultValue}";
            }

            $params[] = "{$typeString}\${$param->getName()}{$default}";
        }

        return implode(', ', $params);
    }

    /*
    |--------------------------------------------------------------------------
    | RETURN TYPE (PROFILE-AWARE)
    |--------------------------------------------------------------------------
    */

    private function buildReturnType(
        ReflectionMethod $method,
        ?string $dtoClass = null,
        ContractProfile $profile
    ): string {

        $type = $method->getReturnType();

        if (!$type) {
            return '';
        }

        $resolved = $this->resolveType($type);
        $methodName = $method->getName();

        /*
        |--------------------------------------------------------------------------
        | API PROFILE → FORCE DTO TYPES
        |--------------------------------------------------------------------------
        */
        if ($profile->type === 'api' && $dtoClass) {

            if (in_array($methodName, ['find', 'findOneBy'])) {
                $resolved = class_basename($dtoClass);
            }

            if (in_array($methodName, ['all', 'search', 'findBy'])) {
                $resolved = "array<" . class_basename($dtoClass) . ">";
            }
        }

        return ': ' . $resolved;
    }

    /*
    |--------------------------------------------------------------------------
    | TYPE RESOLUTION
    |--------------------------------------------------------------------------
    */

    private function resolveType(ReflectionType $type): string
    {
        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                fn($t) => $this->resolveSingleType($t),
                $type->getTypes()
            ));
        }

        return $this->resolveSingleType($type);
    }

    private function resolveSingleType(ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {

            $name = $type->getName();

            if ($type->isBuiltin()) {
                return $type->allowsNull() && $name !== 'mixed'
                    ? '?' . $name
                    : $name;
            }

            $short = $this->importAndShorten($name);

            return $type->allowsNull()
                ? '?' . $short
                : $short;
        }

        return (string) $type;
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORTS
    |--------------------------------------------------------------------------
    */

    private function importAndShorten(string $fqcn): string
    {
        $fqcn = ltrim($fqcn, '\\');

        $this->imports[] = $fqcn;

        return class_basename($fqcn);
    }

    /*
    |--------------------------------------------------------------------------
    | DEFAULT RETURN VALUES
    |--------------------------------------------------------------------------
    */

    private function getDefaultReturnValue(ReflectionMethod $method): string
    {
        $type = $method->getReturnType();

        if (!$type) {
            return 'return null;';
        }

        if ($type instanceof ReflectionUnionType) {
            return 'return null;';
        }

        $name = $type instanceof ReflectionNamedType
            ? $type->getName()
            : null;

        return match ($name) {
            'int' => 'return 0;',
            'float' => 'return 0.0;',
            'string' => "return '';",
            'bool' => 'return false;',
            'array' => 'return [];',
            'void' => 'return;',
            default => 'return null;',
        };
    }
}