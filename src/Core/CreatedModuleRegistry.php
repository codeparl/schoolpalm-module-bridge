<?php

namespace SchoolPalm\ModuleBridge\Core;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SchoolPalm\ModuleBridge\Contracts\ModuleRegistryContract;
use SchoolPalm\ModuleBridge\Support\DevPort;
use SchoolPalm\ModuleBridge\Support\Helper;
use SchoolPalm\ModuleBridge\Traits\RegistryTrait;

class CreatedModuleRegistry implements ModuleRegistryContract
{
    protected ?string $registryPath;

    protected array $modules = [];

    use RegistryTrait;

    public function __construct(?string $registryPath)
    {
        $this->registryPath = $registryPath;
        $this->load();
    }

    /* ====================================================
     * CORE LIFE CYCLE
     * ==================================================== */

    public function load(): void
    {
        if (!File::exists($this->registryPath)) {
            $this->modules = [];
            return;
        }

        $this->modules = Cache::remember(
            Config::get('module-bridge.cache_key'),
            Carbon::now()->addMinutes(Config::get('module-bridge.cache_ttl')),
            fn() => $this->parse()
        );
    }

    public function refresh(): void
    {
        $this->clearCache();
        $this->load();
    }

    /* ====================================================
     * REGISTRY STORAGE
     * ==================================================== */

    public function save(): void
    {
        File::ensureDirectoryExists(dirname($this->registryPath));

        $fp = fopen($this->registryPath, 'c+');

        if ($fp === false) {
            throw new \RuntimeException("Cannot open registry storage file.");
        }

        if (!flock($fp, LOCK_EX)) {
            throw new \RuntimeException("Cannot acquire file lock.");
        }

        ftruncate($fp, 0);
        fwrite($fp, json_encode($this->modules, JSON_PRETTY_PRINT));
        fflush($fp);

        flock($fp, LOCK_UN);
        fclose($fp);

        $this->clearCache();
        $this->load();
    }

    /* ====================================================
     * QUERY OPERATIONS
     * ==================================================== */

    public function all(): array
    {
        return $this->modules;
    }

    public function list(): array
    {
        return array_values($this->modules);
    }

  public function get(string $moduleKey): ?array
{
    $key = $this->normalizeModuleKey($moduleKey);

    return collect($this->modules)->first(function ($m) use ($key) {

        if (!isset($m['module_key'])) {
            return false;
        }

        $moduleKey = $this->normalizeModuleKey($m['module_key']);

        // Exact normalized match first
        if ($moduleKey === $key) {
            return true;
        }

        // Fallback only
        return str_contains($moduleKey, $key);
    });
}

protected function normalizeModuleKey(string $key): string
{
    return Str::of($key)
        ->lower()
        ->replace(['-', '_', '.'], '')
        ->toString();
}

   

    public function exists(string $moduleKey): bool
    {
        return (bool) $this->get($moduleKey);
    }

    public function find(int $id): ?array
    {
        foreach ($this->modules as $m) {
            if (($m['id'] ?? 0) === $id) {
                return $m;
            }
        }

        return null;
    }

    /* ====================================================
     * REGISTRY MUTATIONS
     * ==================================================== */

    public function register(array $module): void
    {
        if ($this->exists($module['module_key'] ?? '')) {
            return;
        }

        $module['id'] = count($this->modules) + 1;

        $normalized = $this->normalizeModule($module);

        $this->modules[$normalized['module_key']] = $normalized;

        $this->save();
    }

    public function update(string $moduleKey, array $attributes): void
    {
        $key = Str::lower($moduleKey);

        if (!isset($this->modules[$key])) {
            throw new \InvalidArgumentException("Module '{$moduleKey}' not found.");
        }

        $this->modules[$key] = array_merge(
            $this->modules[$key],
            $attributes
        );

        $this->modules[$key] = $this->normalizeModule($this->modules[$key]);

        $this->save();
    }

public function forgetKey(string $moduleKey, string $key): mixed
{
    $moduleKey = Str::lower($moduleKey);

    if (!isset($this->modules[$moduleKey])) {
        throw new \InvalidArgumentException("Module '{$moduleKey}' not found.");
    }

    if (!array_key_exists($key, $this->modules[$moduleKey])) {
        return null;
    }

    $data = $this->modules[$moduleKey][$key];

    unset($this->modules[$moduleKey][$key]);

    $this->modules[$moduleKey] = $this->normalizeModule(
        $this->modules[$moduleKey]
    );

    $this->save();

    return $data;
}
    public function remove(string $moduleKey): void
    {
        unset($this->modules[Str::lower($moduleKey)]);

        DevPort::remove($moduleKey);

        $this->reindex();

        $this->save();
    }

    public function clear(): void
    {
        $this->modules = [];
        $this->save();
    }

    /* ====================================================
     * INSTALL STATE CONTROL
     * ==================================================== */

    public function install(string $moduleKey): void
    {
        $module = &$this->modules[Str::lower($moduleKey)] ?? null;

        if ($module) {
            $module['installed'] = true;
            $this->save();
        }
    }

    public function uninstall(string $moduleKey): void
    {
        $module = &$this->modules[Str::lower($moduleKey)] ?? null;

        if ($module) {
            $module['installed'] = false;
            $this->save();
        }
    }

    public function isInstalled(string $moduleKey): bool
    {
        $module = $this->get($moduleKey);

        return $module['installed'] ?? false;
    }

    /* ====================================================
     * FILTERS
     * ==================================================== */

    public function filterByStatus(string $status): array
    {
        return array_values(array_filter($this->modules, function ($module) use ($status) {

            return match ($status) {
                'installed' => ($module['installed'] ?? false) === true,
                'not_installed' => ($module['installed'] ?? false) === false,
                'run' => ($module['run'] ?? false) === true,
                'not_run' => ($module['run'] ?? false) === false,
                'published' => ($module['published'] ?? false) === true,
                'not_published' => ($module['published'] ?? false) === false,
                'all' => true,
                default => false,
            };
        }));
    }

    /* ====================================================
     * INTERNAL HELPERS
     * ==================================================== */

    protected function reindex(): void
    {
        $id = 1;

        foreach ($this->modules as &$module) {
            $module['id'] = $id++;
        }
    }

    protected function validateModuleEntry(array $module): void
    {
        foreach (['module_key', 'namespace', 'path','root', 'manifest'] as $key) {
            if (empty($module[$key])) {
                throw new \InvalidArgumentException("Registry entry missing {$key}");
            }
        }
    }

    protected function normalizeModule(array $entry): array
    {
        $this->validateModuleEntry($entry);

        [$vendor,$context, $module] = explode('.', strtolower($entry['module_key']), 3);

        $path = Helper::normalizePath($entry['path']);

        return [
            'id' => $entry['id'] ?? null,
            'module_key' => strtolower($entry['module_key']),
            'vendor' => Str::studly($vendor),
            'module' => Str::studly($module),
            'dev_port' => DevPort::add(strtolower($entry['module_key'])),
            'role' => Str::studly($entry['role'] ?? 'admin'),
            'run' => (bool) ($entry['run'] ?? false),
            'namespace' => trim($entry['namespace'], '\\'),
            'root' =>$entry['root'] ?? '' ,
            'app_id' => strtolower(str_replace('.', '_', $entry['module_key'])) . '_app',
            'path' => $path,
            'context'=>$context,
            'manifest' => Helper::normalizePath($entry['manifest'] ?? ''),
            'folder' => $entry['folder'] ?? 'Common',
            'is_common' => (bool) ($entry['is_common'] ?? false),
            'published' => (bool) ($entry['published'] ?? false),
            'installed' => (bool) ($entry['installed'] ?? false),
            'created_at' => $entry['created_at'] ?? now(),
        ];
    }

    public function listForCLI(?string $filter = null): array
    {
        $choices = [];

        $modules = $filter
            ? $this->filterByStatus($filter)
            : array_values($this->modules);

        foreach ($modules as $i => $m) {
            $choices[$i + 1] = $m['namespace'];
        }

        return $choices;
    }

    public function clearCache(): void
    {
        cache()->forget(Config::get('module-bridge.cache_key'));
    }

    protected function parse(): array
    {
        $data = Helper::loadJson($this->registryPath);

        return collect($data)
            ->map(fn($entry) => $this->normalizeModule($entry))
            ->keyBy(fn($m) => $m['module_key'])
            ->toArray();
    }

    /** * {@inheritdoc} */
    public function count(): int
    {
        return count($this->modules);
    }
}
