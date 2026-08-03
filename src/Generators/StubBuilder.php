<?php

namespace SchoolPalm\ModuleBridge\Generators;

use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use SchoolPalm\ModuleBridge\Profiles\ContractProfile;

class StubBuilder
{
    protected array $imports = [];

    /*
    |--------------------------------------------------------------------------
    | IMPORTS
    |--------------------------------------------------------------------------
    */

    public function getImports(): array
    {
        return array_unique($this->imports);
    }

    /*
    |--------------------------------------------------------------------------
    | FACTORY PROPERTY
    |--------------------------------------------------------------------------
    */

    public function buildFactoryProperty(?string $dataFactoryClass): string
    {
        if (!$dataFactoryClass) {
            return '';
        }

        return <<<PHP

    protected {$this->short($dataFactoryClass)} \$factory;

PHP;
    }

    /*
    |--------------------------------------------------------------------------
    | FACTORY CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function buildFactoryConstructor(?string $dataFactoryClass): string
    {
        if (!$dataFactoryClass) {
            return '';
        }

        $factory = $this->short($dataFactoryClass);

        return <<<PHP

    public function __construct()
    {
        \$this->__construct();

        \$this->factory = app({$factory}::class);
    }

PHP;
    }

    /*
    |--------------------------------------------------------------------------
    | METHOD STUB
    |--------------------------------------------------------------------------
    */

    public function buildMethodStub(
        ReflectionMethod $method,
        ?string $dtoClass,
        ContractProfile $profile
    ): string {

        $name = $method->getName();

        $parameters = $this->buildParameters($method);

        $returnType = $this->buildReturnType(
            $method,
            $dtoClass,
            $profile
        );

        $phpDoc = $this->buildReturnPhpDoc(
            $method,
            $dtoClass,
            $profile
        );

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT:
        | INTERNAL CONTRACTS DO NOT USE DTOS
        |--------------------------------------------------------------------------
        */
        $implementation = $profile->useDto
            ? $this->resolveDtoServiceImplementation($name, $dtoClass)
            : $this->resolveInternalServiceImplementation($name);

        /*
        |--------------------------------------------------------------------------
        | IMPLEMENTED METHOD
        |--------------------------------------------------------------------------
        */
        if ($implementation) {

            return <<<PHP

    /**
{$phpDoc}     * Auto-generated SDK scaffold method
     */
    public function {$name}({$parameters}){$returnType}
    {
        {$implementation}
    }

PHP;
        }

        /*
        |--------------------------------------------------------------------------
        | FALLBACK
        |--------------------------------------------------------------------------
        */
        $fallback = $this->buildDefaultReturn($method);

        return <<<PHP

    /**
{$phpDoc}     * Auto-generated SDK scaffold method (UNIMPLEMENTED)
     */
    public function {$name}({$parameters}){$returnType}
    {
        // TODO: implement {$name}

        {$fallback}
    }

PHP;
    }

    /*
    |--------------------------------------------------------------------------
    | FACTORY METHOD STUB
    |--------------------------------------------------------------------------
    */

    public function buildMethodStubWithFactory(
        ReflectionMethod $method,
        ?string $dtoClass,
        ?string $factoryClass,
        ContractProfile $profile
    ): string {

        $name = $method->getName();

        $parameters = $this->buildParameters($method);

        $returnType = $this->buildReturnType(
            $method,
            $dtoClass,
            $profile
        );

        $phpDoc = $this->buildReturnPhpDoc(
            $method,
            $dtoClass,
            $profile
        );

        $factoryCall = $this->buildFactoryCall($method);

        return <<<PHP

    /**
{$phpDoc}     * Auto-generated SDK scaffold method (Factory-backed)
     */
    public function {$name}({$parameters}){$returnType}
    {
        {$factoryCall}
    }

PHP;
    }

    /*
    |--------------------------------------------------------------------------
    | DTO SERVICE IMPLEMENTATION MAP
    |--------------------------------------------------------------------------
    */

    private function resolveDtoServiceImplementation(
        string $method,
        ?string $dtoClass
    ): ?string {

        $dto = $this->short($dtoClass);

        return match ($method) {

            /*
            |--------------------------------------------------------------------------
            | READ OPERATIONS
            |--------------------------------------------------------------------------
            */

            'find' =>
                "return \$this->findDto(\$id, {$dto}::class);",

            'findMany' =>
                "return \$this->findManyDto(\$ids, {$dto}::class);",

            'all' =>
                "return \$this->allDto({$dto}::class);",

            'search' =>
                "return \$this->searchDto(\$filters, {$dto}::class);",

            'findBy' =>
                "return \$this->findByDto(\$criteria, {$dto}::class);",

            'findOneBy' =>
                "return \$this->findOneByDto(\$criteria, {$dto}::class);",

            /*
            |--------------------------------------------------------------------------
            | RELATIONS
            |--------------------------------------------------------------------------
            */

            'searchWithRelations' =>
                "return \$this->searchDto(\$filters, {$dto}::class);",

            'findByWithRelations' =>
                "return \$this->findByDto(\$criteria, {$dto}::class);",

            'findOneByWithRelations' =>
                "return \$this->findOneByDto(\$criteria, {$dto}::class);",

            /*
            |--------------------------------------------------------------------------
            | PAGINATION / STREAMING
            |--------------------------------------------------------------------------
            */

            'paginate' =>
                "return \$this->paginateDto(\$page, \$perPage, \$filters, {$dto}::class, \$sort ?? []);",

            'cursor' =>
                "return \$this->cursor(\$filters ?? []);",

            'chunk' =>
                "\$this->chunk(\$size, \$callback);\n        return;",

            /*
            |--------------------------------------------------------------------------
            | META OPERATIONS
            |--------------------------------------------------------------------------
            */

            'exists' =>
                "return \$this->exists(\$id);",

            'count' =>
                "return \$this->count(\$filters ?? []);",

            'aggregate' =>
                "return \$this->aggregate(\$function, \$field, \$filters ?? []);",

            default => null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL SERVICE IMPLEMENTATION MAP
    |--------------------------------------------------------------------------
    */

    private function resolveInternalServiceImplementation(
        string $method
    ): ?string {

        return match ($method) {

            /*
            |--------------------------------------------------------------------------
            | RAW READ
            |--------------------------------------------------------------------------
            */

            'find' =>
                "return \$this->rawFind(\$id);",

            'findMany' =>
                "return \$this->rawFindMany(\$ids);",

            'all' =>
                "return \$this->rawAll();",

            'search' =>
                "return \$this->rawSearch(\$filters);",

            'findBy' =>
                "return \$this->rawFindBy(\$criteria);",

            'findOneBy' =>
                "return \$this->rawFindOneBy(\$criteria);",

            /*
            |--------------------------------------------------------------------------
            | PERSISTENCE
            |--------------------------------------------------------------------------
            */

            'store' =>
                "return \$this->engine->insert(\$data);",

            'update' =>
                "return \$this->engine->updateById(\$id, \$data);",

            'updateMany' =>
                "return \$this->engine->updateWhere(\$criteria, \$data);",

            'delete' =>
                "return \$this->engine->deleteById(\$id);",

            'deleteMany' =>
                "return \$this->engine->deleteWhere(\$criteria);",

            /*
            |--------------------------------------------------------------------------
            | PAGINATION
            |--------------------------------------------------------------------------
            */

            'paginate' =>
                "return \$this->engine->paginate(\$page, \$perPage, \$filters, \$sort ?? []);",

            /*
            |--------------------------------------------------------------------------
            | META
            |--------------------------------------------------------------------------
            */

            'exists' =>
                "return \$this->exists(\$id);",

            'count' =>
                "return \$this->count(\$filters ?? []);",

            'aggregate' =>
                "return \$this->aggregate(\$function, \$field, \$filters ?? []);",

            'sum' =>
                "return \$this->engine->sum(\$field, \$filters ?? []);",

            'avg' =>
                "return \$this->engine->avg(\$field, \$filters ?? []);",

            'min' =>
                "return \$this->engine->min(\$field, \$filters ?? []);",

            'max' =>
                "return \$this->engine->max(\$field, \$filters ?? []);",

            /*
            |--------------------------------------------------------------------------
            | STREAMING
            |--------------------------------------------------------------------------
            */

            'cursor' =>
                "return \$this->cursor(\$filters ?? []);",

            'chunk' =>
                "\$this->chunk(\$size, \$callback);\n        return;",

            /*
            |--------------------------------------------------------------------------
            | RAW
            |--------------------------------------------------------------------------
            */

            'raw' =>
                "return \$this->engine->raw();",

            default => null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | FACTORY CALLS
    |--------------------------------------------------------------------------
    */

    private function buildFactoryCall(ReflectionMethod $method): string
    {
        return "return \$this->factory->{$method->getName()}(...func_get_args());";
    }

    /*
    |--------------------------------------------------------------------------
    | PARAMETERS
    |--------------------------------------------------------------------------
    */

    private function buildParameters(ReflectionMethod $method): string
    {
        $params = [];

        foreach ($method->getParameters() as $param) {

            $type = $param->getType();

            $typeString = $type
                ? $this->resolveType($type) . ' '
                : '';

            $default = $param->isDefaultValueAvailable()
                ? ' = ' . var_export($param->getDefaultValue(), true)
                : '';

            $params[] = "{$typeString}\${$param->getName()}{$default}";
        }

        return implode(', ', $params);
    }

    /*
    |--------------------------------------------------------------------------
    | RETURN TYPE
    |--------------------------------------------------------------------------
    */

    private function buildReturnType(
        ReflectionMethod $method,
        ?string $dtoClass,
        ContractProfile $profile
    ): string {

        $type = $method->getReturnType();

        if (!$type) {
            return '';
        }

        $resolved = $this->resolveType($type);

        /*
        |--------------------------------------------------------------------------
        | DTO RETURN OVERRIDES
        |--------------------------------------------------------------------------
        */
        if (
            $profile->useDto &&
            $dtoClass
        ) {

            $dto = $this->short($dtoClass);

            $methodName = $method->getName();

            if (in_array($methodName, [
                'find',
                'findOneBy',
                'findOneByWithRelations',
            ])) {

                return ': ?' . $dto;
            }

            if (in_array($methodName, [
                'paginate',
            ])) {

                return ': array';
            }
        }

        if (str_contains($resolved, 'array<')) {
            $resolved = 'array';
        }

        return ': ' . $resolved;
    }

    /*
    |--------------------------------------------------------------------------
    | PHPDOC
    |--------------------------------------------------------------------------
    */

    private function buildReturnPhpDoc(
        ReflectionMethod $method,
        ?string $dtoClass,
        ContractProfile $profile
    ): string {

        if (
            !$profile->useDto ||
            !$dtoClass
        ) {
            return '';
        }

        $dto = class_basename($dtoClass);

        return match ($method->getName()) {

            'all',
            'search',
            'findBy',
            'findMany',
            'searchWithRelations',
            'findByWithRelations'
                => "     * @return {$dto}[]\n",

            default => '',
        };
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

                return $type->allowsNull() &&
                    $name !== 'mixed'
                    ? '?' . $name
                    : $name;
            }

            return $this->short($name);
        }

        return (string) $type;
    }

    /*
    |--------------------------------------------------------------------------
    | DEFAULT RETURNS
    |--------------------------------------------------------------------------
    */

    private function buildDefaultReturn(
        ReflectionMethod $method
    ): string {

        $type = $method->getReturnType();

        if (!$type instanceof ReflectionNamedType) {
            return 'return null;';
        }

        return match ($type->getName()) {

            'int' =>
                'return 0;',

            'float' =>
                'return 0.0;',

            'string' =>
                "return '';",

            'bool' =>
                'return false;',

            'array' =>
                'return [];',

            'iterable' =>
                'return [];',

            'void' =>
                'return;',

            default =>
                'return null;',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function short(?string $fqcn): string
    {
        if (!$fqcn) {
            return 'mixed';
        }

        $fqcn = ltrim($fqcn, '\\');

        $this->imports[] = $fqcn;

        return class_basename($fqcn);
    }
}