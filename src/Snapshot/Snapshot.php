<?php

namespace SchoolPalm\ModuleBridge\Snapshot;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SchoolPalm\ModuleBridge\Context\ModuleRuntimeContext;
use SchoolPalm\ModuleBridge\Facades\SnapshotRegistry;
use SchoolPalm\ModuleBridge\Generators\ModuleContract;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;
use SchoolPalm\ModuleBridge\Snapshot\SnapshotServiceProviderGenerator as SnapshotProviderGenerator;
use SchoolPalm\ModuleBridge\Support\Archive;
use SchoolPalm\ModuleBridge\Support\Helper;
use SchoolPalm\ModuleBridge\Factories\SchemaDataFactoryInjector;

class Snapshot
{
    private ModuleRuntimeContext $context;
    private string $rootPath;
    private string $basePath;
    private array $structure = [];
    private  string $snapshotRoot;
    private  ModuleManifest  $manifest;

    public function __construct(string $manifest_path, ?string $rootPath = null)
    {
        $manifest = new ModuleManifest($manifest_path);

        $this->context = new ModuleRuntimeContext(
            manifest: $manifest,
            backendPath: $rootPath
        );

        $this->manifest =  $this->context->manifest;

        $this->rootPath = $rootPath ? $rootPath : $this->context->backendPath;

        $this->basePath = Str::beforeLast($this->rootPath, '/Backend');
        $this->snapshotRoot = $this->context->snapshot_root . '/' . $this->context->manifest->root();
    }

    public function generateStructure()
    {
        $generator = new StructureGen;
        $this->structure = $generator->generate($this->context);

        return $this;
    }

public function loadStructure():array {
 $path  =  $this->snapshotRoot.'/structure.json'; 
 return Helper::loadJson($path);  
}

public function loadManifest():array {
 $path = $this->snapshotRoot . '/snapshot.manifest.json';
 return Helper::loadJson($path);  
}

    public function createManifest(): self
    {
        $manifest = $this->context->manifest;

        $info  = $manifest->info();
        $baseNamespace = $this->normalizeNamespace($info->namespace());

        $data = [
            'name'        => $info->name(),
            'module_key'  => $manifest->key(),
            'vendor'      => $info->vendor(),
            'namespace'   => $info->namespace(),
            'base_namespace' => $baseNamespace,
            'version'     => $manifest->info()->version(),
            'php_version' =>  $manifest->dependencies->php() ?? null,

            'sdk' => [
                'name'    => $manifest->sdk->name() ?? null,
                'version' => $manifest->sdk->version() ?? null,
            ],

            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'checksum' => $this->context->module_checksum,
                'snapshot_version' => '1.0.0',
            ],
            'relations'=>$manifest->raw()['relations'] ?? null,
            'provider' => $info->namespace() . '\Providers\SnapshotServiceProvider',
            'provides' => $manifest->provides ?? [],
            'dtos'     => $manifest->dtos ?? [],
            'events'   => array_values($manifest->events ?? []),
        ];

        $path = $this->snapshotRoot . '/snapshot.manifest.json';
        Helper::storeJson($path, $data);
        return $this;
    }

   public function packZip(bool $deleteSource = false)
{
    $source = $this->snapshotRoot;

    $key = $this->manifest->key();
    $version = $this->manifest->info()->version();

    $zipName = 'snapshot-' . Helper::makeFileNameFromModule($key, $version);
    $zipPath = $this->context->snapshot_root . '/' . $zipName;

    $ignore = ['structure.json'];

    // Create zip FIRST
    Archive::zip($source, $zipPath, $ignore, $deleteSource);

    // Ensure file was created successfully
    if (!file_exists($zipPath)) {
        throw new \RuntimeException("Failed to create snapshot zip: {$zipPath}");
    }

    $snapshot = $this->loadManifest();

    $data = [
        'file' => $zipName,
        'path' => $zipPath,
        'created_at' => $snapshot['meta']['generated_at'] ?? now()->toDateTimeString(),
    ];

    SnapshotRegistry::add($key, $version, $data);
}

    private function normalizeNamespace(string $namespace): string
    {
        return str_replace('\\Backend', '', $namespace);
    }


    public function copyContracts()
    {
        $destination = $this->structure['contracts'] ?? null;
        if (!$destination) return $this;

        $baseSource = $this->rootPath . '/Contracts/';
        $contracts = $this->context->manifest->providedContracts ?? [];

        foreach ($contracts as $namespace) {
            $file = class_basename($namespace) . '.php';

            Helper::copyFile(
                $baseSource . $file,
                $destination . '/' . $file
            );
        }

        return $this;
    }

    public function generateServices(bool $useDataFactory = true)
{
    $contracts = $this->manifest->providedContracts;

    foreach ($contracts as $contract) {

        $mContract = app(ModuleContract::class);

        /*
        |--------------------------------------------------------------------------
        | Inject generation mode into contract execution
        |--------------------------------------------------------------------------
        |
        | If useDataFactory = true:
        | → Service methods will be implemented using DataFactory
        |
        */
        $mContract->executeContract(
            contractInterface: $contract,
            customPath: $this->snapshotRoot . '/Backend',
            useDataFactory: $useDataFactory
        );
    }

    return $this;
}

    public function copyEvents()
    {
        $destination = $this->structure['events'] ?? null;
        if (!$destination) return $this;

        $baseSource = $this->rootPath . '/Events/';
        $events = $this->context->manifest->events ?? [];

        foreach ($events as $namespace) {
            $file = class_basename($namespace) . '.php';
            Helper::copyFile(
                $baseSource . $file,
                $destination . '/' . $file
            );
        }

        return $this;
    }

public function generateDataFactory(): self
{
    $contracts = $this->manifest->providedContracts ?? [];

    if (empty($contracts)) {
        return $this;
    }

    $schemasPath = $this->structure['schemas'] ?? null;

    if (!$schemasPath || !is_dir($schemasPath)) {
        throw new \RuntimeException("Schemas directory not found");
    }

    /*
    |--------------------------------------------------------------------------
    | Factories Output
    |--------------------------------------------------------------------------
    */
    $factoriesPath = $this->snapshotRoot . '/Backend/Factories';

    /*
    |--------------------------------------------------------------------------
    | Namespace
    |--------------------------------------------------------------------------
    */
    $namespace = rtrim(
        $this->context->manifest->info()->namespace(),
        '\\'
    ) . '\\Factories';

    $injector = new SchemaDataFactoryInjector();

    /*
    |--------------------------------------------------------------------------
    | Iterate Contracts
    |--------------------------------------------------------------------------
    */
    foreach ($contracts as $contract) {

        /*
        |--------------------------------------------------------------------------
        | Contract-Based Schema Name
        |--------------------------------------------------------------------------
        */
        $contractName = class_basename($contract);

        $schemaPath = $schemasPath . '/' . $contractName . '.json';

        /*
        |--------------------------------------------------------------------------
        | Ensure Schema Exists
        |--------------------------------------------------------------------------
        */
        if (!is_file($schemaPath)) {
            throw new \RuntimeException(
                "Schema not found for contract [{$contractName}] at [{$schemaPath}]"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Load schema to validate contract binding
        |--------------------------------------------------------------------------
        */
        $schema = json_decode(file_get_contents($schemaPath), true);

        if (!$schema || !isset($schema['columns'])) {
            throw new \RuntimeException(
                "Invalid schema for contract [{$contractName}]"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Generate DataFactory
        |--------------------------------------------------------------------------
        */
        $injector->generate(
            providedContracts: [$contract],
            schemaPath: $schemaPath,
            factoriesPath: $factoriesPath,
            namespace: $namespace
        );
    }

    return $this;
}

    public function copyDTOs()
    {
        $destination = $this->structure['dtos'] ?? null;
        if (!$destination) return $this;

        $baseSource = $this->rootPath . '/DTOs/';
        $dtos = $this->context->manifest->dtos ?? [];

        foreach ($dtos as $namespace) {
            $file = class_basename($namespace) . '.php';

            Helper::copyFile(
                $baseSource . $file,
                $destination . '/' . $file
            );
        }

        return $this;
    }

public function copySchemas(): self
{
    $destination = $this->structure['schemas'] ?? null;

    if (!$destination) {
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Source Schemas Directory
    |--------------------------------------------------------------------------
    */
    $baseSource = $this->rootPath . '/Database/schemas/';

    if (!is_dir($baseSource)) {
        return $this;
    }

    $contracts = $this->manifest->providedContracts ?? [];

    $schemaFiles = File::files($baseSource);

    foreach ($contracts as $contract) {

        $entity = Str::snake(
            preg_replace('/Contract$/', '', class_basename($contract))
        );

        $plural = Str::plural($entity);

        $contractName = class_basename($contract);
        $snapshotSchemaName = $contractName . '.json';

        $matched = null;

        foreach ($schemaFiles as $file) {

            $name = $file->getFilenameWithoutExtension();

            if (str_ends_with($name, "{$plural}_table")) {
                $matched = $file->getPathname();
                break;
            }
        }

        if (!$matched) {
            throw new \RuntimeException(
                "Schema not found for contract [{$contract}]"
            );
        }

      
        $schema = json_decode(file_get_contents($matched), true);

        if (!$schema || !isset($schema['columns'])) {
            throw new \RuntimeException(
                "Invalid schema file [{$matched}]"
            );
        }

        $safeSchema = [
            'contract' => $contractName,
            'entity'   => $entity,
            'table'    => $plural . '_table',
            'columns'  => $schema['columns'],
        ];

        Helper::storeJson(
            $destination . '/' . $snapshotSchemaName,
            $safeSchema
        );
    }

    return $this;
}

    public function generateProvider()
    {
        $generator = new SnapshotProviderGenerator();
        $stabPath  = realpath(__DIR__ . '/SnapshotServiceProvider.php.stub');
        $this->structure['module_path'] =  $this->context->manifest->root();
        $generator->generate(
            namespace: $this->context->manifest->info()->namespace(),
            stubPath: $stabPath,
            outputPath: $this->snapshotRoot . '/Backend/Providers/SnapshotServiceProvider.php'
        );

        return $this;
    }
}
