<?php

namespace SchoolPalm\ModuleBridge\Generators;

class FacadeGenerator
{
    public function generate(
        string $facadeClass,
        string $accessorClass,
        string $outputPath,
        string $interface
    ): void {

        $docBuilder = new FacadeDocBuilder();

        // -----------------------------
        // Scan interface
        // -----------------------------
        $methods = $docBuilder->scanInterfaceMethods($interface);

        $docBlock = $docBuilder->buildFacadeAnnotations($methods);

        //  collect DTO + class imports from doc builder
        $collectedImports = $docBuilder->getImports();

        // Always include accessor class
        $collectedImports[] = $accessorClass;

        $collectedImports = array_unique($collectedImports);
        sort($collectedImports);

        // -----------------------------
        // Namespace / class
        // -----------------------------
        $namespace = trim(
            str_replace('\\' . class_basename($facadeClass), '', $facadeClass),
            '\\'
        );

        $className = class_basename($facadeClass);

        $accessorShort = class_basename($accessorClass);

        // -----------------------------
        // Build use statements
        // -----------------------------
        $useStatements = implode("\n", array_map(
            fn($i) => "use {$i};",
            $collectedImports
        ));

        // -----------------------------
        // Generate file
        // -----------------------------
        $template = <<<PHP
<?php

namespace {$namespace};

use Illuminate\Support\Facades\Facade;
{$useStatements}

{$docBlock}
class {$className} extends Facade
{
    protected static function getFacadeAccessor()
    {
        return {$accessorShort}::class;
    }
}
PHP;

        file_put_contents($outputPath, $template);
    }


    public function buildQueryBuilderAnnotations(): array
{
    return [
        '@method static \SchoolPalm\ModuleBridge\Query\ContractQueryBuilder query()',
        '@method static \SchoolPalm\ModuleBridge\Query\ContractQueryBuilder with(string|array $relations)',
        '@method static \SchoolPalm\ModuleBridge\Query\ContractQueryBuilder where(string $field, mixed $value, string $operator = \'=\')',
    ];
}
}