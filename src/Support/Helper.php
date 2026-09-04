<?php

namespace SchoolPalm\ModuleBridge\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SchoolPalm\ModuleBridge\Generators\RelationProviderSynchronizer;

/**
 * Class Helper
 *
 * Generic utility functions shared between SchoolPalm core and the Module SDK.
 *
 * PURPOSE:
 * - Provide pure PHP helper functions for string, module, JSON, and path handling
 * - Fully reusable in SDK and core
 * - Stateless and framework-independent
 *
 * LICENSE:
 * MIT License 
 *
 * @package SchoolPalm\ModuleBridge\Support
 */
final class Helper
{
    /* -------------------------------------------------
     | Path / Route Helpers
     |-------------------------------------------------*/

    /**
     * Get a specific segment from a path.
     *
     * Supported keys:
     * - portal, module, action, id
     *
     * @param string      $key     Segment key
     * @param string|null $path    Example: admin/students/edit/5
     * @param bool        $central Whether the route is central-style
     *
     * @return string|null
     */
    public static function getPathSegment(
        string $key,
        ?string $path = null,
        string $mode = 'full'
    ): ?string {
        $path = $path ?: request()->path();

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        /**
         * Detect modules route automatically
         */
        $isModuleRoute = str_contains($path, 'modules/');

        if ($isModuleRoute) {
            $mode = 'full';
        }

        /**
         * Define strict schemas
         */
        $schema = match ($mode) {
            'sdk' => [
                'portal',
                'module',
                'action',
                'id',
            ],

            'central' => [
                'module',
                'action',
            ],

            default => [
                'portal',
                'context',
                'module',
                'action',
                'id',
            ],
        };

        /**
         * Map strictly by position
         */
        $map = [];

        foreach ($schema as $index => $name) {
            $map[$name] = $segments[$index] ?? null;
        }
        //assumes portal/context 
        if (count($segments) <= 2 && $mode == 'full') return $segments[0];
        //assumes portal 
        if (count($segments) < 2 && $mode == 'sdk') return $segments[0];
        /**
         * Safe fallback ONLY for missing schema keys
         */
        if (!array_key_exists($key, $map)) {
            return null;
        }

        return $map[$key];
    }


    /* -------------------------------------------------
     | JSON Helpers
     |-------------------------------------------------*/

    /**
     * Load and decode a JSON file.
     *
     * @param string      $fileName File name (with or without .json)
     * @param string|null $key      Optional top-level key to extract
     * @param string|null $path     Full file path (overrides default)
     *
     * @return array<mixed>
     */
    public static function loadJson(string $fileName, ?string $key = null, ?string $path = null): array
    {
        if (substr($fileName, -5) !== '.json') {
            $fileName .= '.json';
        }

        $filePath = $path ?? $fileName;

        if (!is_file($filePath) || !is_readable($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        return $key !== null && array_key_exists($key, $data) && is_array($data[$key])
            ? $data[$key]
            : $data;
    }

    /**
     * Store an array as a JSON file.
     *
     * @param string $fileName File name (with or without .json)
     * @param array  $data     Data to encode as JSON
     * @param string $path     Directory path where file will be stored
     *
     * @return bool True on success, false on failure
     */
    public static function storeJson(string $filePath, array $data): bool
    {
        $directory = dirname($filePath);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            return false;
        }

        if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'json') {
            $filePath .= '.json';
        }

        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($content === false) {
            return false;
        }

        return file_put_contents($filePath, $content) !== false;
    }

    /* -------------------------------------------------
     | Module / Folder Helpers
     |-------------------------------------------------*/

    public static function normalizeModuleName(string $module): string
    {
        if (strpos($module, '-') !== false) {
            $module = preg_replace('/-+/', ' ', $module);
        }

        return self::studly($module);
    }


    public static function levelFolder(string $namespace): string
    {
        return explode('\\', $namespace)[1];
    }

    public static function roleFolderName(string $role): string
    {
        $role = str_replace(['-', '_'], ' ', $role);
        $role = self::title($role);
        return str_replace(' ', '', $role);
    }



    public static function moduleFolderName(string|array|object $module): string
    {
        if (is_string($module)) {
            $moduleKey = $module;
        } elseif (is_array($module) && isset($module['module_key'])) {
            $moduleKey = $module['module_key'];
        } elseif (is_object($module) && isset($module->module_key)) {
            $moduleKey = $module->module_key;
        } else {
            throw new \InvalidArgumentException('Invalid module parameter provided.');
        }

        $parts = explode('.', $moduleKey);
        if (count($parts) > 1) array_shift($parts);

        $combined = implode(' ', $parts);
        $combined = str_replace(['.', '_', '-'], ' ', $combined);

        return self::studly($combined);
    }

    public static function namespaceToPath(string $namespace): string
    {
        return str_replace('\\', '/', trim($namespace, '\\'));
    }

    public static function modulePath(string $key): string
    {
        return self::namespaceToPath(self::moduleKeyToNamespace($key));
    }

    /**
     * Get academic levels from encrypted config.
     *
     * Vendors cannot write or modify this file directly.
     *
     * @return array<int,array{label:string,code:string}>
     */
    public static function getAcademicLevels(): array
    {
        // Read and return data
        return EncryptedConfig::read('academic_levels');
    }
    /* -------------------------------------------------
     | String Helpers (Laravel-like, PHP-pure)
     |-------------------------------------------------*/

    public static function contains(string $haystack, string|array $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && strpos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }



    public static function checksum(string $path): string
    {
        if (!File::exists($path)) {
            throw new \InvalidArgumentException("checksum: Path not found: {$path}");
        }

        //  Single file
        if (File::isFile($path)) {
            return sha1_file($path);
        }

        //  Directory
        $files = File::allFiles($path);

        // Ensure consistent order (VERY important)
        usort($files, fn($a, $b) => strcmp($a->getRealPath(), $b->getRealPath()));

        $hashes = [];

        foreach ($files as $file) {
            $hashes[] = sha1_file($file->getRealPath());
        }

        return sha1(implode('', $hashes));
    }


    public static function normalizePath(string $path): string
    {
        // Replace both forward and backward slashes with the OS separator
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        return preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR) . '+#', DIRECTORY_SEPARATOR, $path);
    }

    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0) {
                return true;
            }
        }
        return false;
    }

    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && substr($haystack, -strlen($needle)) === $needle) {
                return true;
            }
        }
        return false;
    }

    public static function before(string $subject, string $search): string
    {
        if ($search === '') return $subject;
        $pos = strpos($subject, $search);
        return $pos === false ? $subject : substr($subject, 0, $pos);
    }

    public static function after(string $subject, string $search): string
    {
        if ($search === '') return $subject;
        $pos = strpos($subject, $search);
        return $pos === false ? $subject : substr($subject, $pos + strlen($search));
    }

    public static function beforeLast(string $subject, string $search): string
    {
        if ($search === '') return $subject;
        $pos = strrpos($subject, $search);
        return $pos === false ? $subject : substr($subject, 0, $pos);
    }

    public static function afterLast(string $subject, string $search): string
    {
        if ($search === '') return $subject;
        $pos = strrpos($subject, $search);
        return $pos === false ? $subject : substr($subject, $pos + strlen($search));
    }

    public static function between(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') return '';
        return self::before(self::after($subject, $from), $to);
    }

    public static function kebab(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($value)));
    }

    public static function snake(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($value)));
    }

    public static function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        return str_replace(' ', '', ucwords($value));
    }

    public static function title(string $value): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $value));
    }

    public static function getConfigFileContent($path = null): array
    {
        if ($path == null)
            return (require realpath(__DIR__ . '/config/module-bridge.php'))  ?? [];

        return (require $path) ?? [];
    }

    public static function configPath()
    {
        return realpath(__DIR__ . '/config/module-bridge.php');
    }

    public static function dataPath()
    {
        return realpath(__DIR__) . '/../data/module-transit.json';
    }

    public static function dataFolder(string $path = '')
    {
        return realpath(__DIR__) . '/../data/' . $path;
    }
    public static function schemaPath()
    {
        return realpath(__DIR__) . '/../data/module-manifest.schema.json';
    }



    /**
     * Generate a safe archive filename from a dot-notated module key and version.
     *
     * Example:
     *  makeFileName('unnovatebrains.school.student', '1.0.0')
     *  => unnovatebrains-school-student-1.0.0.zip
     *
     * @param string $moduleKey Format: vendor.context.module
     * @param string $version
     * @param string $extension Default: zip
     *
     * @return string
     */
    public static function makeFileNameFromModule(
        string $moduleKey,
        string $version,
        string $extension = 'zip'
    ): string {
        return Archive::makeFileName($moduleKey, $version, $extension);
    }


    static function   moduleKeyToNamespace(string $moduleKey, bool $studly = true): string
    {
        // Normalize separators
        $normalized = str_replace(['/', '-', '\\'], '.', $moduleKey);

        // Split into parts
        $parts = explode('.', $normalized);

        // Convert each segment
        $parts = array_map(function ($part) use ($studly) {
            return $studly ? Str::studly($part) : $part;
        }, $parts);

        // Build namespace
        return implode('\\', $parts) . '\\Backend';
    }

    /**
     * Convert multiple levels into combined folder string
     */

    public static function levelsFolderName(array $levels): string
    {
        return LevelManager::level()->joinByCodes($levels);
    }

    public static function moduleKey(string $moduleKey, string $context): string
    {
        $moduleKey = strtolower($moduleKey);
        $context   = strtolower($context);

        $parts = explode('.', $moduleKey);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException(
                "Invalid module_key format. Expected 'vendor.module'"
            );
        }

        [$vendor, $module] = $parts;

        return "{$vendor}.{$context}.{$module}";
    }

    public static function joinCurricula(array $curricula): string
    {
        $codes = [];

        foreach ($curricula as $c) {
            // Split by underscore, dash, or space
            $parts = preg_split('/[_\-\s]+/', $c);

            // Convert each part to StudlyCase
            $parts = array_map(function ($part) {
                return ucfirst(strtolower($part));
            }, $parts);

            // Join into CamelCase
            $codes[] = implode('', $parts);
        }

        return $codes ? implode('', $codes) : '';
    }

    /**
     * Check if a specific school level exists in a combined levels folder string
     */
    public static function levelExists(string $level, string $folderName): bool
    {
        return LevelManager::level()->levelExists($level, $folderName);
    }

    /**
     * Generate the PHP namespace for a module
     */
    public static function moduleNamespace(array $module, string $levelFolder): string
    {

        $moduleName = Str::studly(self::moduleFolderName($module));
        $vendorName = Str::studly($module['vendor']);
        $levelFolder .= self::joinCurricula($module['curricula'] ?? []);
        return "{$vendorName}\\{$levelFolder}\\{$moduleName}\\Backend";
    }


    public static function namespaceToKey(string $key)
    {

        if (str_contains($key, 'Backend'))
            $key  =  self::before($key, '\Backend');
        $normalized = str_replace(['/', '-', '\\'], '.', strtolower($key));
        return $normalized;
    }

    public static function modulePart(
        string $key,
        string $part = 'module'
    ): ?string {

        if (empty($key)) {
            return null;
        }



        $normalized = str_replace(
            ['\\', '/'],
            '.',
            trim($key)
        );

        $segments = array_values(
            array_filter(
                explode('.', $normalized)
            )
        );

        if (empty($segments)) {
            return null;
        }

        /*
    |--------------------------------------------------------------------------
    | Remove Backend Suffix
    |--------------------------------------------------------------------------
    */

        $last = end($segments);

        if (
            strtolower($last) === 'backend'
        ) {
            array_pop($segments);
        }

        if (empty($segments)) {
            return null;
        }

        /*
    |--------------------------------------------------------------------------
    | Detect Original Style
    |--------------------------------------------------------------------------
    */

        $isNamespace = str_contains($key, '\\');

        $separator = $isNamespace
            ? '\\'
            : '.';

        $count = count($segments);

        return match ($part) {

            /*
        |--------------------------------------------------------------------------
        | Vendor
        |--------------------------------------------------------------------------
        */

            'vendor' => $segments[0] ?? null,

            /*
        |--------------------------------------------------------------------------
        | Context
        |--------------------------------------------------------------------------
        */

            'context' => $count >= 2
                ? $segments[$count - 2]
                : null,

            /*
        |--------------------------------------------------------------------------
        | Module
        |--------------------------------------------------------------------------
        */

            'module' => $segments[$count - 1] ?? null,

            /*
        |--------------------------------------------------------------------------
        | Context.Module
        |--------------------------------------------------------------------------
        */

            'context.module' => $count >= 2
                ? implode(
                    $separator,
                    array_slice($segments, -2)
                )
                : null,

            /*
        |--------------------------------------------------------------------------
        | Full
        |--------------------------------------------------------------------------
        */

            'full' => implode(
                $separator,
                $segments
            ),

            default => null,
        };
    }


    public static  function copyFile(string $source, string $destination)
    {

        if (!File::exists($source))
            throw new \Exception('Source file not found');

        File::ensureDirectoryExists(dirname($destination));
        File::copy($source, $destination);
    }

    public static function copyDirectory(string $source, string $destination): void
    {
        if (!File::exists($source)) {
            throw new \Exception("Source directory not found: {$source}");
        }

        $source = rtrim(str_replace('\\', '/', $source), '/');
        $destination = rtrim(str_replace('\\', '/', $destination), '/');

        File::ensureDirectoryExists($destination);

        foreach (File::allFiles($source) as $file) {

            $filePath = str_replace('\\', '/', $file->getPathname());

            // correct relative path
            $relativePath = str_replace($source . '/', '', $filePath);

            $targetPath = $destination . '/' . $relativePath;

            File::ensureDirectoryExists(dirname($targetPath));

            File::copy($file->getPathname(), $targetPath);
        }
    }

    /**
     * Create a new generated relation provider or update an existing one
     * by merging the submitted relation definition by name.
     *
     * @param class-string $providerClass
     * @param array<string,mixed> $submittedRelation
     */
    public static function syncRelationProviders(
        string $providerClass,
        array $submittedRelations,
        string $outputPath
    ): void {
        (new RelationProviderSynchronizer())->createOrUpdateRelations(
            $providerClass,
            $submittedRelations,
            $outputPath
        );
    }

    /**
     * Sync a module relation provider using a provided manifest array.
     *
     * @param array<string,mixed> $manifest
     * @param array<string,mixed> $submittedRelation
     */
    public static function syncManifestRelations(
        array $manifest,
        array $submittedRelations,
        ?string $basePath = null
    ): void {
        $providerClass = (string) ($manifest['relations'] ?? '');
        $root = (string) ($manifest['root'] ?? '');

        if ($providerClass === '') {
            throw new \InvalidArgumentException('Manifest relations class is required.');
        }

        if ($root === '') {
            throw new \InvalidArgumentException('Manifest root is required.');
        }
        $basePath = $basePath ?? base_path();
        $outputPath = $basePath . '/' . class_basename($providerClass) . '.php';

        self::syncRelationProviders($providerClass, $submittedRelations, $outputPath);
    }


    public static function syncRelationsFromUI(
        array $manifest,
        array $submittedRelations,
        ?string $basePath = null
    ): void {
        $providerClass = (string) ($manifest['relations'] ?? '');
        $root = (string) ($manifest['root'] ?? '');

        if ($providerClass === '') {
            throw new \InvalidArgumentException('Manifest relations_provider class is required.');
        }

        if ($root === '') {
            throw new \InvalidArgumentException('Manifest root is required.');
        }

        $basePath = $basePath ?? base_path();

        $basePath = $basePath ?? base_path();
        $outputPath = $basePath . '/' . class_basename($providerClass) . '.php';


        app(RelationProviderSynchronizer::class)
            ->syncFromUi($providerClass, $submittedRelations, $outputPath);
    }

    public static  function resolveRelationDefinitions(string $fqcn): array
    {
        if (!class_exists($fqcn)) {
            return [];
        }
        if (is_callable([$fqcn, 'getDefinitions'])) {
            return $fqcn::getDefinitions();
        }

        return [];
    }

    public static  function resolveRelations(string $fqcn): array
    {
        if (!class_exists($fqcn)) {
            return [];
        }

        $instance = app($fqcn);

        if (method_exists($instance, 'relations')) {
            return $instance->relations();
        }

        return [];
    }

    public static function isSdkRuntime()
    {
        return config('sdk.runtime', 'sdk') !== 'schoolpalm';
    }
}
