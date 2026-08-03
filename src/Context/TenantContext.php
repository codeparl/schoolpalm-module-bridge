<?php
namespace SchoolPalm\ModuleBridge\Context;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SchoolPalm\ModuleBridge\Facades\ModuleRegistry;
use SchoolPalm\ModuleBridge\Facades\ModuleTransit;
use SchoolPalm\ModuleBridge\Support\Helper;

class TenantContext
{
    /**
     * Switch to a specific tenant context
     */
    public static function boot(string $tenantId): void
    {
        // Use the tenancy helper if available, or resolve from container
        if (function_exists('tenancy')) {
            $tenant = self::getTenantModel()::find($tenantId);
            if ($tenant) {
                tenancy()->initialize($tenant);
            }
        }
    }

    /**
     * Revert to the central database
     */
    public static function restore(): void
    {
        if (function_exists('tenancy')) {
            tenancy()->end();
        }
    }

    /**
     * Run a callback for each tenant safely
     */
    public static function forEachTenant(callable $callback): void
    {
        $tenantModel = self::getTenantModel();

        // Standard Stancl Tenancy way to loop through all tenants
        $tenantModel::all()->each(function ($tenant) use ($callback) {
            $tenant->run(function () use ($tenant, $callback) {
                $callback($tenant);
            });
        });
    }

    /**
     * Dynamically resolve the Tenant model class from the main app's config
     */
    protected static function getTenantModel(): string
    {
        return 'App\\Models\\Tenant';
    }

public static function storeModule(array $manifest): array
{
    $vendorName  = Str::studly($manifest['vendor']);
    $isCommon    = $manifest['is_common'] ?? false;
    $namespace   = $manifest['namespace'];
    $relativePath = $manifest['root'];
    $transit  =  ModuleTransit::find($namespace);
    $data = [
        'name'         => $manifest['name'],
        'vendor'       => $vendorName,
        'module_key'   => $manifest['module_key'],
        'namespace'    => $namespace,
        'path'         => $relativePath,
        'manifest'     => null,
        'description'  => $manifest['description'] ?? '',
        'author'       => $manifest['author']['name'] ?? '',
        'version'      => $manifest['version'],
        'is_protected' => $manifest['is_protected'] ?? true,
        'is_common'    => $isCommon,
        'type'         => $manifest['type'],
        'installed'    => true,
        'role'         => $manifest['role'] ?? 'admin',
        'active'       => false,
        'settings'     => json_encode([
            'level' => $manifest['level'] ?? [],
            'role'  => $manifest['role'] ?? 'admin',
            'plans'=>$transit['plans'] ?? null
        ]),
        'created_at'   => now(),
        'updated_at'   => now(),
    ];

    try {
        DB::table('modules')->insert($data);
    } catch (\Throwable $th) {
       throw new RuntimeException('Module already exists');
    }
    

    return $data;
}

public static function registerModuleToCache(array $manifest, string $modulePath): void
{
    $context = $manifest['context'];
    $rootPath  =  Str::beforeLast(rtrim($modulePath,'/'),'/');
    $manifestPath = $rootPath . DIRECTORY_SEPARATOR . 'manifest.json';

    // 🔹 Normalize keys
    $moduleKey  = strtolower($manifest['module_key']);
    $moduleSlug = strtolower($manifest['name']);

    //  Early check (optional log hook)
   if (ModuleRegistry::has($context, $moduleKey)) {
    throw new \RuntimeException(
        "Module already registered in cache: {$moduleKey} (context: {$context})"
    );
}

    //  Normalize paths
    $relativePath = $manifest['root'];

    $frontendBase = rtrim(config('sdk.module.exec_dir.frontend_dir'), DIRECTORY_SEPARATOR);
    $backendPath = rtrim(config('sdk.modules_path'), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . $relativePath . DIRECTORY_SEPARATOR . 'Backend';

    //  Build frontend assets safely
    $frontendAssets = [
        'js_main'  =>  $frontendBase  . '/' . $manifest['frontend']['js_main'],

        'css_main' =>  $frontendBase  . '/' . $manifest['frontend']['css_main']
,
    ];

    //Build module data
    $moduleData = [
        'module_key'    => $moduleKey,
        'vendor'        => $manifest['vendor'],
        'module'        => $manifest['name'],
        'name'          =>$moduleSlug,
        'namespace'     => $manifest['namespace'],
        'relative_path' => $relativePath,
        'front_end'     => $frontendAssets,
        'path'          => $backendPath,
        'app_id'        => str_replace('.', '_', $moduleKey) . '_app',
        'manifest'      => $manifestPath,
        'role'          => $manifest['role'] ?? 'admin',
    ];

    // Register (single normalized key)
    ModuleRegistry::set($context, $moduleKey, $moduleData);
}


public static function removeStoredModule(string $moduleKey): void
{
    DB::table('modules')
        ->where('module_key', $moduleKey)
        ->delete();
}

public static function unregisterModuleFromCache(string $context, string $moduleKey): void
{
    ModuleRegistry::forget($context, $moduleKey);
}

}
