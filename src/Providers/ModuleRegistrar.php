<?php

namespace SchoolPalm\ModuleBridge\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SchoolPalm\ModuleBridge\Relations\RelationRegistryBuilder;

class ModuleRegistrar
{
    protected static array $registeredProviders = [];
    protected static array $bootedProviders = [];
    protected static array $autoloaders = [];

    /**
     * Global built relation registry
     */
    protected static array $relationRegistry = [];

    /**
     * =========================================================
     * Register Normal Modules
     * =========================================================
     */
    public static function registerModules(
        string $registryPath,
        $context = 'prod'
    ): void {

        if (!File::exists($registryPath)) {
            return;
        }

        $raw = self::loadRegistry($registryPath);

        if (!$raw) {
            return;
        }

        $modules = self::normalizeModules(
            $raw,
            $context
        );

        foreach ($modules as $module) {

            if (!($module['run'] ?? true)) {
                continue;
            }

            $baseNamespace =
                $module['namespace'] ?? null;

            $basePath =
                $module['path'] ?? null;

            if (!$baseNamespace || !$basePath) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Register FULL module autoloader
            |--------------------------------------------------------------------------
            */
            self::registerAutoloader(
                $baseNamespace,
                $basePath
            );

            /*
            |--------------------------------------------------------------------------
            | Register Providers
            |--------------------------------------------------------------------------
            */
            $providersNamespace =
                $baseNamespace . '\\Providers';

            $providersPath =
                rtrim(
                    $basePath,
                    DIRECTORY_SEPARATOR
                )
                . DIRECTORY_SEPARATOR
                . 'Providers';

            self::registerProviders(
                $providersNamespace,
                $providersPath
            );
        }
    }

    /**
     * =========================================================
     * Register Snapshots
     * =========================================================
     */
    public static function registerSnapshots(
        string $registryPath
    ): void {

        if (!File::exists($registryPath)) {
            return;
        }

        $raw = self::loadRegistry($registryPath);

        if (!$raw || !is_array($raw)) {
            return;
        }

        $snapshots = self::normalizeSnapshots($raw);

        foreach ($snapshots as $snapshot) {

            $baseNamespace =
                $snapshot['namespace'] ?? null;

            $basePath =
                $snapshot['execution_path'] ?? null;

            if (!$baseNamespace || !$basePath) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Register Autoloader
            |--------------------------------------------------------------------------
            */
            self::registerAutoloader(
                $baseNamespace,
                $basePath
            );

            /*
            |--------------------------------------------------------------------------
            | Register Providers
            |--------------------------------------------------------------------------
            */
            $providersNamespace =
                $baseNamespace . '\\Providers';

            $providersPath =
                rtrim(
                    $basePath,
                    DIRECTORY_SEPARATOR
                )
                . DIRECTORY_SEPARATOR
                . 'Providers';

            self::registerProviders(
                $providersNamespace,
                $providersPath,
                true
            );
        }
    }

    /**
     * =========================================================
     * Boot Relations
     * =========================================================
     *
     * Builds ONE merged relation registry from:
     *
     * - modules registry
     * - optional snapshots registry
     *
     */
  public static function bootRelations(
    string $modulesRegistryPath,
    ?string $snapshotsRegistryPath = null,
    string $context = 'prod'
): void {

    $allModules = [];

    /*
    |--------------------------------------------------------------------------
    | Load Normal Modules
    |--------------------------------------------------------------------------
    */

    if (File::exists($modulesRegistryPath)) {

        $rawModules = self::loadRegistry($modulesRegistryPath);

        if ($rawModules) {

            $modules = self::normalizeModules($rawModules, $context);

            $allModules = array_merge($allModules, $modules);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Load Snapshots
    |--------------------------------------------------------------------------
    */

    if (
        $snapshotsRegistryPath !== null &&
        File::exists($snapshotsRegistryPath)
    ) {

        $rawSnapshots = self::loadRegistry($snapshotsRegistryPath);

        if ($rawSnapshots) {

            $snapshots = self::normalizeSnapshots($rawSnapshots);

            $allModules = array_merge($allModules, $snapshots);
        }
    }

    if (empty($allModules)) {
        return;
    }

    try {

        /*
        |--------------------------------------------------------------------------
        | Build Registry
        |--------------------------------------------------------------------------
        */

        $builder = app(RelationRegistryBuilder::class);

        $registry = $builder->build($allModules);
   
        /*
        |--------------------------------------------------------------------------
        | SAFE MODULE-KEY MERGE
        |--------------------------------------------------------------------------
        */

        $existing = app()->bound('module.relations')
            ? app('module.relations')
            : [];

        foreach ($registry as $moduleKey => $relations) {

            /*
            |--------------------------------------------------------------------------
            | HARD SAFETY CHECK
            |--------------------------------------------------------------------------
            */

            if (isset($existing[$moduleKey])) {

                throw new \RuntimeException(
                    "Duplicate module_key detected in relations registry: {$moduleKey}"
                );
            }

            $existing[$moduleKey] = $relations;
        }

        /*
        |--------------------------------------------------------------------------
        | REGISTER
        |--------------------------------------------------------------------------
        */

        app()->instance('module.relations', $existing);

        self::$relationRegistry = $existing;

    } catch (\Throwable $e) {
        report($e);
    }
}

    /**
     * =========================================================
     * Access Relation Registry
     * =========================================================
     */
    public static function getRelationRegistry(): array
    {
        return self::$relationRegistry;
    }

    /**
     * =========================================================
     * Load Registry
     * =========================================================
     */
    protected static function loadRegistry(
        string $path
    ): ?array {

        if (Str::endsWith($path, '.json')) {

            return json_decode(
                File::get($path),
                true
            );
        }

        return require $path;
    }

    /**
     * =========================================================
     * Normalize Modules
     * =========================================================
     */
    protected static function normalizeModules(
        array $raw,
        $context = 'prod'
    ): array {

        $modules = [];

        if (isset($raw[0])) {
            return $raw;
        }

        /*
        |--------------------------------------------------------------------------
        | Production Registry Structure
        |--------------------------------------------------------------------------
        */
        if ($context == 'prod') {

            foreach ($raw as $contextModules) {

                foreach ($contextModules as $module) {

                    $modules[] = [

                        'namespace' =>
                            $module['namespace'] ?? null,

                        'path' =>
                            $module['path'] ?? null,
                            'module_key'=> $module['module_key'],

                            'relations'=>$module['relations'] ?? null,

                        'run' => true,
                    ];
                }
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | SDK Flat Structure
            |--------------------------------------------------------------------------
            */
            foreach ($raw as $module) {

                $modules[] = [

                    'namespace' =>
                        $module['namespace'] ?? null,

                    'path' =>
                        $module['path'] ?? null,
                         'relations'=>$module['relations'] ?? null,
                         'module_key'=> $module['module_key'],

                    'run' => true,
                ];
            }
        }

        return $modules;
    }

    /**
     * =========================================================
     * Normalize Snapshot Registry
     * =========================================================
     */
    protected static function normalizeSnapshots(
        array $raw
    ): array {

        $modules = [];

        foreach ($raw as $moduleKey => $versions) {

            if (!is_array($versions)) {
                continue;
            }

            foreach ($versions as $version => $snapshot) {

                $modules[] = [

                    'module_key' =>
                        $snapshot['module_key']
                        ?? $moduleKey,

                    'namespace' =>
                        $snapshot['namespace']
                        ?? null,

                    'path' =>
                        $snapshot['execution_path']
                        ?? null,

                    'execution_path' =>
                        $snapshot['execution_path']
                        ?? null,

                    'zip_path' =>
                        $snapshot['zip_path']
                        ?? null,

                    'version' =>
                        $version,

                    'run' => true,
                ];
            }
        }

        return $modules;
    }

    /**
     * =========================================================
     * Register PSR-4 Autoloader
     * =========================================================
     */
    protected static function registerAutoloader(
        string $namespace,
        string $basePath
    ): void {

        if (isset(self::$autoloaders[$namespace])) {
            return;
        }

        spl_autoload_register(function (
            $class
        ) use (
            $namespace,
            $basePath
        ) {

            if (
                strncmp(
                    $class,
                    $namespace . '\\',
                    strlen($namespace . '\\')
                ) !== 0
            ) {
                return;
            }

            $relativeClass = Str::after(
                $class,
                $namespace . '\\'
            );

            $file =
                rtrim(
                    $basePath,
                    DIRECTORY_SEPARATOR
                )
                . DIRECTORY_SEPARATOR
                . str_replace(
                    '\\',
                    DIRECTORY_SEPARATOR,
                    $relativeClass
                )
                . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });

        self::$autoloaders[$namespace] = true;
    }

    /**
     * =========================================================
     * Register Providers
     * =========================================================
     */
    protected static function registerProviders(
        string $namespace,
        string $providersPath,
        bool $snapshot = false
    ): void {

        if (!is_dir($providersPath)) {
            return;
        }

        foreach (
            File::files($providersPath)
            as $file
        ) {

            $class = pathinfo(
                $file->getFilename(),
                PATHINFO_FILENAME
            );

            $providerClass =
                $namespace . '\\' . $class;

            if (!class_exists($providerClass)) {
                continue;
            }

            if (!app()->getProvider($providerClass)) {
                app()->register($providerClass);
            }

            if (
                !in_array(
                    $providerClass,
                    self::$registeredProviders
                )
            ) {

                self::$registeredProviders[] =
                    $providerClass;
            }
        }
    }

    /**
     * =========================================================
     * Called inside provider boot()
     * =========================================================
     */
    public static function markAsBooted(
        string $providerClass
    ): void {

        if (
            !in_array(
                $providerClass,
                self::$bootedProviders
            )
        ) {

            self::$bootedProviders[] =
                $providerClass;
        }
    }

    /**
     * =========================================================
     * Get Registered Providers
     * =========================================================
     */
    public static function getRegisteredProviders(): array
    {
        return self::$registeredProviders;
    }

    /**
     * =========================================================
     * Get Booted Providers
     * =========================================================
     */
    public static function getBootedProviders(): array
    {
        return self::$bootedProviders;
    }

    /**
     * =========================================================
     * Debug Registration
     * =========================================================
     */
    public static function debugRegistration(
        string $registryPath,
        $context = 'prod'
    ): void {

        if (!File::exists($registryPath)) {

            dd(
                'Registry file not found:',
                $registryPath
            );
        }

        $raw = self::loadRegistry($registryPath);

        if (!$raw) {
            dd('Registry empty or invalid');
        }

        $modules = self::normalizeModules(
            $raw,
            $context
        );

        $report = [];

        foreach ($modules as $module) {

            $baseNamespace =
                $module['namespace'] ?? null;

            $basePath =
                $module['path'] ?? null;

            $providersNamespace =
                $baseNamespace . '\\Providers';

            $providersPath =
                rtrim(
                    $basePath,
                    DIRECTORY_SEPARATOR
                )
                . DIRECTORY_SEPARATOR
                . 'Providers';

            $moduleReport = [

                'module' => $module,

                'autoload_test' =>
                    class_exists(
                        $baseNamespace
                        . '\\ModuleActionEntry'
                    ),

                'providers_path_exists' =>
                    is_dir($providersPath),

                'providers' => [],
            ];

            if (is_dir($providersPath)) {

                foreach (
                    File::files($providersPath)
                    as $file
                ) {

                    $class = pathinfo(
                        $file->getFilename(),
                        PATHINFO_FILENAME
                    );

                    $providerClass =
                        $providersNamespace
                        . '\\'
                        . $class;

                    $moduleReport['providers'][] = [

                        'class' =>
                            $providerClass,

                        'class_exists' =>
                            class_exists(
                                $providerClass
                            ),

                        'registered' =>
                            app()->getProvider(
                                $providerClass
                            ) !== null,
                    ];
                }
            }

            $report[] = $moduleReport;
        }

        $report[] = [
            'relations_registry' =>
                self::$relationRegistry
        ];

        dd($report);
    }
}