<?php

namespace SchoolPalm\ModuleBridge\Manifest;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SchoolPalm\ModuleBridge\Platform\SdkVersion;
use SchoolPalm\ModuleBridge\Relations\RelationSpec;
use SchoolPalm\ModuleBridge\Support\Helper;


class ManifestFactory
{
    public static function make(array $data): array
    {
        $config = config('sdk');

        $name        = $data['name'];
        $vendor      = $data['vendor'] ?? $config['vendor'] ?? 'SchoolPalm';

        $role        = $data['role'] ?? $config['defaults']['role'] ?? 'admin';
        $version     = '1.0.0';
        $type        = $data['type'] ?? $config['defaults']['type'] ?? 'external';
        $description = $data['description'] ?? '';
        $isCommon    = $data['is_common'] ?? true;
        $level       = $data['level'] ?? [];


        if ($isCommon && !empty($level)) $isCommon = false;

        if (!$isCommon && empty($level)) $level = [0];

        $curricula =
            $data['curricula']
            ?? [];


        $_level  =  Helper::levelsFolderName($level);
        $joined_curricula  = Helper::joinCurricula($curricula);
        $moduleName   = Helper::moduleFolderName($name);
        $namespace = Helper::moduleNamespace($data, $_level);
        $root_np    = Str::beforeLast($namespace, '\\Backend');
        $authorName  =  is_array($data['author'])  ? $data['author']['name'] : $data['author'];
        $author = [
            'name'    => $authorName ?? $config['author']['name'] ?? 'SchoolPalm',
            'email'   => $data['author']['email'] ?? $config['author']['email'] ?? null,
            'website' => $data['author']['website'] ?? $config['author']['website'] ?? null,
        ];



        $dependencies = [
            'backend'  => (object) ($data['dependencies']['backend']  ?? $config['dependencies']['backend']  ?? []),
        ];

        $root_path  = str_replace('\\', '/', $root_np);
        $moduleKey = strtolower(
            trim(str_replace(['\\'], '.', $root_np), '.')
        );

        $frontend = [
            'js_main' => $root_path . '/' . $moduleName . '.js',
            'css_main' => $root_path . '/' . $moduleName . '.css',
            'assets'   => $root_path . '/assets'
        ];

        $migrations = [
            'path'           => $data['migrations']['path'] ?? 'Database/migrations',
            'run_on_install' => $data['migrations']['run_on_install'] ?? true,
            'run_on_update'  => $data['migrations']['run_on_update'] ?? true,
            'tables' => $data['tables'] ?? []
        ];



        $models = [
            'path'      => $data['models']['path'] ?? 'Models',
            'namespace' => $namespace . '\\Models',
            'autoload'  => $data['models']['autoload'] ?? true,
        ];

        $menus = $data['menus'] ?? [[
            'name'        => $moduleKey . '.' . ($config['menu']['permission'] ?? 'manage') . '.' . self::normalizePermission($name),
            'label'       => ucfirst($name),
            'icon'        => $data['icon'] ?? 'lucide-layers',
            'permission'  => ($config['menu']['permission'] ?? 'manage') . '.' . self::normalizePermission($name),
            'route'       => $config['menu']['route'] ?? null,
            'description' => $description,
            'children'    => [],
        ]];

        $actions = $data['actions'] ?? [];

        $entry = $data['entry'] ?? [
            'provider' => $namespace . '\\Providers\\' . self::generateModuleServiceProvider($moduleKey)

        ];

         $relations =  $namespace . '\\Relations\\' . self::deriveName($moduleKey,'Relations','module');

        

        $actionEntry  =  $namespace . '\\ModuleActionEntry';

        $license  = [
            'type' => $config['license'] ?? 'Proprietary',
            'path' => $root_path . '/LICENSE'
        ];
        $image  = [
            'type' => 'svg',
            'path' => $frontend['assets'] . '/module-icon.svg',
            'themeable' => true
        ];
        $joined_curricula =  $joined_curricula ? '.'.$joined_curricula : '';
        $context  = strtolower($_level . $joined_curricula);
        $provides = [];
        if (is_array($data['provides'] ?? []) && count($data['provides']) > 0) {
            foreach ($data['provides'] as $key => $contract) {
                array_push($provides, $namespace . '\\Contracts\\' . $contract);
            }
        } else {
            // Derive default provides if not supplied
            $provides = $data['provides'] ?? [$namespace . '\\Contracts\\' . Str::singular($moduleName) . 'Contract'];
        }


            $dtos = self::deriveDtos($provides);
        // Keep events empty if not supplied
      //  $events = self::generateEvents($namespace, Str::singular($moduleName))['events'];
        $sdk = ['name' => 'schoolpalm/module-sdk', 'version' => SdkVersion::current()];
        return [
            'name'         => $name,
            'namespace' => $namespace,
            'vendor'       => $vendor,
            'prefix' => $data['prefix'] ?? self::prefix($moduleKey),
            'module_key'   => $moduleKey,
            'description'  => $description,
            'version'      => $version,
            'icon'      => $data['icon'] ?? 'lucide-Layers',
            'image'     => (object)$image,
            'type'         => $type,
            'context' => $context,
            'root' => $root_path,
            'sdk'          => $sdk,
            'menus'        => $menus,
            'role'         => $role,
            'actions'      => $actions,
            'entry'        => $entry,
            'relations'      =>$relations,
            'actionEntry'  =>$actionEntry,
            'author'       => $author,
            'dependencies' => (object) $dependencies,
            'frontend'     => $frontend,
            'level'        => $data['level'] ?? [],
            'curricula'    => $curricula,
            'readme'       => (object) ['path' => $root_path . '/README.md'],
            'license'       => (object) $license,
            'is_common'    => $isCommon,
            'migrations'   => $migrations,
            'models'       => $models,
            'events'       => [],
            'provides'     => $provides,
            'dtos'         => $dtos ,
            'requires'     => $data['requires'] ?? ['modules' => new \stdClass()],
        ];
    }



    /**
     * Generate default events and listeners for a module
     *
     * @param string $namespace Base namespace for the module (e.g., Vendor\Level\Module)
     * @param string $moduleName Module name (e.g., "Student")
     * @return array List of fully qualified class names for events and listeners
     */
    public static function generateEvents(string $namespace, string $moduleName): array
    {
        // Default event actions
        $baseEvents = [
            'Created',
            'Updated',
            'Deleted',
        ];

        // Generate event FQCNs under namespace\Events\ModuleEvent
        $events = array_map(fn($e) => $namespace . '\\Events\\' . $moduleName . $e, $baseEvents);

        // Generate listener FQCNs under namespace\Listeners\ModuleEventListener
        $listeners = array_map(fn($e) => $namespace . '\\Listeners\\' . $moduleName . $e . 'Listener', $baseEvents);

        return [
            'events'    => $events,
            'listeners' => $listeners,
        ];
    }


    public static function generateModuleServiceProvider(string $key): string
    {
        return collect(explode('.', $key))
            ->map(fn($part) => Str::studly($part))
            ->implode('') . 'ServiceProvider';
    }

    public static function deriveName(
    string $key,
    string $suffix = '',
    string $part = 'full',
): string {

    $segments = explode('.', $key);

    $name = match ($part) {

        'vendor' =>
            Str::studly($segments[0] ?? ''),

        'context' =>
            Str::studly($segments[1] ?? ''),

        'module' =>
            Str::studly($segments[2] ?? ''),

        'full' =>
            collect($segments)
                ->map(fn ($segment) => Str::studly($segment))
                ->implode(''),

        default =>
            collect($segments)
                ->slice(3)
                ->map(fn ($segment) => Str::studly($segment))
                ->implode(''),
    };

    if ($suffix) {
        $name .= Str::studly($suffix);
    }

    return $name;
}

    public static function prefix(string $key): string
    {
        return  config('sdk.prefix') ??   implode('', array_map(
            fn($part) => $part[0] ?? '',
            explode('.', $key)
        ));
    }

    public static function normalizeJson(array &$data, array $schema, string $path = ''): void
    {
        if (!isset($schema['properties']) || !is_array($schema['properties'])) {
            return;
        }

        foreach ($schema['properties'] as $key => $propertySchema) {
            $currentPath = $path === '' ? $key : "{$path}.{$key}";
            $valueExists = array_key_exists($key, $data);
            $value = $valueExists ? $data[$key] : null;

            // --------------------------------------------------
            // CASE 1: OBJECT normalization
            // --------------------------------------------------
            if (($propertySchema['type'] ?? null) === 'object') {
                $hasRequiredProps = isset($propertySchema['required']);
                $isMapObject = isset($propertySchema['additionalProperties']);

                // Normalize missing value
                if (!$valueExists) {
                    if (isset($propertySchema['default'])) {
                        $data[$key] = $propertySchema['default'];
                    } elseif ($isMapObject && !$hasRequiredProps) {
                        $data[$key] = (object)[];
                    }
                }

                // Normalize [] → {}
                if ($valueExists && is_array($value) && empty($value) && $isMapObject) {
                    $data[$key] = (object)[];
                }

                // Recurse if object now exists
                if (isset($data[$key]) && is_array($data[$key])) {
                    self::normalizeJson($data[$key], $propertySchema, $currentPath);
                }

                if (isset($data[$key]) && is_object($data[$key])) {
                    // Convert object to array temporarily for recursion
                    $tmp = (array) $data[$key];
                    self::normalizeJson($tmp, $propertySchema, $currentPath);
                    $data[$key] = (object) $tmp;
                }
            }

            // --------------------------------------------------
            // CASE 2: ARRAY recursion (items)
            // --------------------------------------------------
            if (($propertySchema['type'] ?? null) === 'array' && isset($propertySchema['items']) && $valueExists && is_array($value)) {
                foreach ($value as $index => $item) {
                    if (is_array($item) && isset($propertySchema['items']['properties'])) {
                        self::normalizeJson($data[$key][$index], $propertySchema['items'], "{$currentPath}[{$index}]");
                    }
                }
            }
        }
    }



    protected static function normalizePermission(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '.', trim($name)));
    }



    /**
     * Load a module manifest JSON file
     *
     * @param string $filePath Full path to manifest.json
     * @return array|null Returns associative array on success, null on failure
     */
    public  static function  loadManifest(string $filePath): ?array
    {

        if (!file_exists($filePath)) {
            return null;
        }
        return Helper::loadJson($filePath);
    }


   public static function update($existingManifest, array $data, string $filePath)
{
    $provides = $data['provides'] ?? $existingManifest['provides'] ?? [];
    $relation =  RelationSpec::normalizeRelations($data['relation'] ?? []);
    $dtos = self::deriveDtos($provides);
    $namespace = $existingManifest['namespace'];
    $module_key = $existingManifest['module_key'];
    $relations =  $namespace . '\\Relations\\' . self::deriveName($module_key,'Relations','module');
    $updatedManifest = array_merge($existingManifest, [

        // -------------------------
        // Core fields (safe merge)
        // -------------------------
        'name'        => $data['name'] ?? $existingManifest['name'],
        'description' => $data['description'] ?? $existingManifest['description'],
        'version'     => $data['version'] ?? $existingManifest['version'],
        'icon'        => $data['icon'] ?? $existingManifest['icon'] ?? 'lucide-Layers',
        'prefix'      => $data['prefix'] ?? self::prefix($data['module_key'] ?? $existingManifest['module_key']),

        // -------------------------
        // UI / structure
        // -------------------------
        'menus'       => $data['menus'] ?? $existingManifest['menus'],

        // -------------------------
        // Contracts / DTOs
        // -------------------------
        'provides'    => $provides,
        'dtos'        => $dtos,
        'relations'    => $data['relations'] ?? $relations,

        // -------------------------
        // IMPORTANT FIX: safe fallback
        // -------------------------
        'migrations'  => $data['migrations'] ?? $existingManifest['migrations'] ?? [],
        'actions'     => $data['actions'] ?? $existingManifest['actions'] ?? [],

        // -------------------------
        // Optional fields (safe)
        // -------------------------
        'events'      => $data['events'] ?? $existingManifest['events'] ?? [],

    ]);

    ManifestValidator::validate($updatedManifest);
    Helper::storeJson($filePath, $updatedManifest);
    $relationPath  = dirname($filePath).'/Backend/Relations';

    //update relations 
    if(File::exists($relationPath) && File::isDirectory($relationPath))
           Helper::syncRelationsFromUI($updatedManifest,$relation,$relationPath);
}


    public static function deriveDtos(array $provides): array
{
    $dtos = [];

    foreach ($provides as $contract) {

        $contractName = Helper::afterLast($contract, '\\');

        // Only process valid contracts
        if (!str_ends_with($contractName, 'Contract')) {
            continue;
        }

        // Convert Contract → Data
        $dtoName = str_replace('Contract', 'Data', $contractName);

        // Replace namespace Contracts → DTOs
        $baseNamespace = Helper::beforeLast($contract, '\\');
        $dtoNamespace = str_replace('\\Contracts', '\\DTOs', $baseNamespace);

        $dtos[] = $dtoNamespace . '\\' . $dtoName;
    }

    return array_values(array_unique($dtos));
}
}
