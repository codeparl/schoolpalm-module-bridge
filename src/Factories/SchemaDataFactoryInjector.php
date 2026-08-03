<?php

namespace SchoolPalm\ModuleBridge\Factories;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SchemaDataFactoryInjector
{
    /**
     * Generate DataFactories from contracts + schema
     */
    public function generate(
        array $providedContracts,
        string $schemaPath,
        string $factoriesPath,
        string $namespace
    ): void {

        File::ensureDirectoryExists(
            $factoriesPath,
            0777,
            true
        );

        foreach ($providedContracts as $contractClass) {

            $contractName = class_basename($contractClass);

            if (!File::exists($schemaPath)) {
                throw new \RuntimeException(
                    "Schema not found for contract: {$contractName}"
                );
            }

            $schema = json_decode(
                file_get_contents($schemaPath),
                true
            );

            if (!$schema || !isset($schema['columns'])) {
                throw new \RuntimeException(
                    "Invalid schema for contract: {$contractName}"
                );
            }

            $this->generateFactory(
                contractClass: $contractClass,
                schema: $schema,
                factoriesPath: $factoriesPath,
                namespace: $namespace
            );
        }
    }

    /**
     * Generate single factory
     */
    protected function generateFactory(
        string $contractClass,
        array $schema,
        string $factoriesPath,
        string $namespace
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Naming
        |--------------------------------------------------------------------------
        */
        $contractBase = class_basename($contractClass);

        $entity = preg_replace('/Contract$/', '', $contractBase);

        $factoryName = "{$entity}DataFactory";
        $dtoName     = "{$entity}Data";

        /*
        |--------------------------------------------------------------------------
        | DTO Namespace
        |--------------------------------------------------------------------------
        */
        $dtoNamespace = Str::beforeLast($namespace, '\\Factories') . '\\DTOs';

        /*
        |--------------------------------------------------------------------------
        | Mock File (schema-driven, not table-driven)
        |--------------------------------------------------------------------------
        */
        $mockFile = Str::snake($entity) . '.json';

        /*
        |--------------------------------------------------------------------------
        | Build class
        |--------------------------------------------------------------------------
        */
        $class = $this->buildClass(
            namespace: $namespace,
            factoryName: $factoryName,
            dtoName: $dtoName,
            dtoNamespace: $dtoNamespace,
            mockFile: $mockFile
        );

        File::put(
            $factoriesPath . '/' . $factoryName . '.php',
            $class
        );
    }

    /**
     * Build factory class
     */
    protected function buildClass(
        string $namespace,
        string $factoryName,
        string $dtoName,
        string $dtoNamespace,
        string $mockFile
    ): string {

        return <<<PHP
<?php

namespace {$namespace};

use SchoolPalm\\ModuleBridge\\Factories\\DataFactory;
use {$dtoNamespace}\\{$dtoName};

class {$factoryName} extends DataFactory
{
    protected function dtoClass(): string
    {
        return {$dtoName}::class;
    }

    protected function mockDataPath(): string
    {
        return __DIR__ . '/../data/{$mockFile}';
    }
}
PHP;
    }
}