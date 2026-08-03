<?php

namespace SchoolPalm\ModuleBridge\Core;

use Illuminate\Support\Facades\File;
use SchoolPalm\ModuleBridge\Contracts\ModuleRegistryContract;
use SchoolPalm\ModuleBridge\Traits\RegistryTrait;

/**
 * Class AutoloadModuleRegistry
 *
 * Registry for modules that are discovered via filesystem autoloading.
 *
 * IMPORTANT SEMANTICS:
 * - Any module present in this registry is considered INSTALLED by definition.
 * - This registry does NOT manage lifecycle state (install / uninstall).
 * - `group` represents the folder name and encodes joined academic levels.
 *   This value is CRITICAL for autoload path resolution.
 *
 * Storage shape:
 * [
 *   group => [
 *     vendor => [
 *       module_key => moduleManifestArray
 *     ]
 *   ]
 * ]
 */
class AutoloadModuleRegistry implements ModuleRegistryContract
{
    use RegistryTrait;
    /**
     * Absolute path to the PHP cache file.
     */
    protected string $cacheFile;

    /**
     * Loaded modules indexed by group → vendor → module_key.
     *
     * @var array<string, array<string, array<string, array>>>
     */
    protected array $modules = [];

    /**
     * Create a new autoload registry instance.
     *
     * The registry is eagerly loaded from disk.
     *
     * @param string $registryPath
     */
    public function __construct(string $registryPath)
    {
        $this->cacheFile = $registryPath;
        $this->load();
    }

    /**
     * Load registry data from the cache file.
     *
     * Missing cache file results in an empty registry.
     */
    public function load(): void
    {
        if (!File::exists($this->cacheFile)) {
            $this->modules = [];
            return;
        }

        $this->modules = include $this->cacheFile;
    }

    /**
     * Persist registry state to disk.
     *
     * Uses a PHP return file for zero-cost loading.
     */
    public function save(): void
    {
        File::ensureDirectoryExists(dirname($this->cacheFile));

        $content = "<?php\n\nreturn " . var_export($this->modules, true) . ";\n";
        File::put($this->cacheFile, $content);
    }

    /**
     * Get all registered modules (grouped).
     *
     * @return array
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * Retrieve a module by key.
     *
     * If vendor is NULL, search across all vendors within the group.
     *
     * @param string      $moduleKey
     * @param string|null $group   Folder name / academic level group
     * @param string|null $vendor
     *
     * @return array|null
     */
    public function get(string $moduleKey, ?string $group = 'common', ?string $vendor = null): ?array
    {
        $groupModules = $this->modules[$group] ?? [];

        if ($vendor === null) {
            foreach ($groupModules as $vendorModules) {
                if (isset($vendorModules[$moduleKey])) {
                    return $vendorModules[$moduleKey];
                }
            }
            return null;
        }

        return $groupModules[$vendor][$moduleKey] ?? null;
    }

    /**
     * Determine if a module exists in the registry.
     *
     * Existence === discoverable via autoload.
     */
    public function exists(string $moduleKey, ?string $group = 'common', ?string $vendor = null): bool
    {
        return $this->get($moduleKey, $group, $vendor) !== null;
    }

    /**
     * Determine if a module is installed.
     *
     * AUTOLOAD REGISTRY RULE:
     * - Any module present here is always installed.
     * - This method is therefore a semantic no-op.
     */
    public function isInstalled(string $moduleKey, ?string $group = 'common', ?string $vendor = null): bool
    {
        return $this->exists($moduleKey, $group, $vendor);
    }

    /**
     * Register a module into the autoload registry.
     *
     * Expected minimal module attributes:
     * - module_key (string)
     * - vendor     (string)
     *
     * @param array       $module
     * @param string|null $group   Folder / academic level group
     * @param string|null $vendor
     */
    public function register(array $module, ?string $group = 'common', ?string $vendor = null): void
    {
        $moduleKey = strtolower($module['module_key']);
        $vendor    = $vendor ?? $module['vendor'] ?? 'unknown';

        // Autoload registry implies installed
        $module['installed'] = true;
        $module['folder']    = $group;
        $module['vendor']    = $vendor;

        $this->modules[$group][$vendor][$moduleKey] = $module;

        $this->save();
    }

    /**
     * Remove a module from the registry.
     *
     * This represents physical or logical removal from autoload discovery.
     */
    public function remove(string $moduleKey, ?string $group = 'common', ?string $vendor = null): void
    {
        if ($vendor !== null) {
            unset($this->modules[$group][$vendor][$moduleKey]);

            if (empty($this->modules[$group][$vendor])) {
                unset($this->modules[$group][$vendor]);
            }
        } else {
            foreach ($this->modules[$group] ?? [] as $v => $mods) {
                unset($this->modules[$group][$v][$moduleKey]);

                if (empty($this->modules[$group][$v])) {
                    unset($this->modules[$group][$v]);
                }
            }
        }

        if (empty($this->modules[$group] ?? [])) {
            unset($this->modules[$group]);
        }

        $this->save();
    }
    
  

    /**
     * Mark a module as installed.
     *
     * AUTOLOAD REGISTRY RULE:
     * - Modules are always installed.
     * - Method retained for interface compatibility.
     */
    public function install(string $moduleKey): void
    {
        // no-op by design
    }

    /**
     * Mark a module as uninstalled.
     *
     * AUTOLOAD REGISTRY RULE:
     * - Uninstall is meaningless here.
     * - Physical removal should use remove().
     */
    public function uninstall(string $moduleKey): void
    {
        // no-op by design
    }

    /**
     * Filter modules by install status.
     *
     * Since all autoload modules are installed:
     * - 'installed' → all modules
     * - 'not_installed' → empty array
     */
    public function filterByStatus(string $status): array
    {
        return match ($status) {
            'installed', 'all' => $this->modules,
            default            => [],
        };
    }

    /**
     * Get total number of registered modules.
     */
    public function count(): int
    {
        $total = 0;

        foreach ($this->modules as $vendors) {
            foreach ($vendors as $modules) {
                $total += count($modules);
            }
        }

        return $total;
    }

    /**
     * Remove all modules from the registry.
     */
    public function clear(): void
    {
        $this->modules = [];
        $this->save();
    }

    /**
     * Return a flat numeric list of all modules.
     *
     * Useful for iteration and pipelines.
     */
    public function list(): array
    {
        $list = [];

        foreach ($this->modules as $vendors) {
            foreach ($vendors as $modules) {
                foreach ($modules as $module) {
                    $list[] = $module;
                }
            }
        }

        return $list;
    }

    /**
     * Reload registry from disk.
     */
    public function refresh(): void
    {
        $this->load();
    }

    /**
     * Update attributes of a registered module.
     *
     * @param string      $moduleKey
     * @param array       $attributes
     * @param string|null $group
     * @param string|null $vendor
     */
    public function update(
        string $moduleKey,
        array $attributes,
        ?string $group = 'common',
        ?string $vendor = null
    ): void {
        $module = $this->get($moduleKey, $group, $vendor);

        if (!$module) {
            return;
        }

        $vendor    = $vendor ?? $module['vendor'] ?? 'unknown';
        $moduleKey = $module['module_key'];

        $this->modules[$group][$vendor][$moduleKey] =
            array_merge($module, $attributes);

        $this->save();
    }
}
