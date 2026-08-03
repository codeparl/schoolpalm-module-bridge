<?php

namespace SchoolPalm\ModuleBridge\Core;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleRegistry
{
    protected string $cachePath;
    protected array $cache = [];

    public function __construct()
    {
        $this->cachePath = config('sdk.module.cache.external');
        $this->load();
    }

    /**
     * Load cache from file
     */
    protected function load(): void
    {
        $this->cache = File::exists($this->cachePath)
            ? require $this->cachePath
            : [];
    }

    /**
     * Persist cache (atomic write)
     */
    public function save(): void
    {
        $temp = $this->cachePath . '.tmp';

        File::put($temp, '<?php return ' . var_export($this->cache, true) . ';');
        File::move($temp, $this->cachePath);
    }

    /**
     * Get full registry
     */
    public function all(): array
    {
        return $this->cache;
    }

    /**
     * Get modules by context
     */
    public function getContext(string $context): array
    {
        return $this->cache[$context] ?? [];
    }

     public function hasContext(string $context): bool
    {
        return isset($this->cache[$context]);
    }

    /**
     * Resolve module (WITH fallback to common)
     */
    public function get(string $context, string $module): ?array
    {
        return $this->cache[$context][$module]
            ?? null;
    }

    /**
     * Register / update module
     */
    public function set(string $context, string $module, array $data): void
    {
        $context = strtolower($context);
        $module  = strtolower($module);

        if (!isset($this->cache[$context])) {
            $this->cache[$context] = [];
        }

        $this->cache[$context][$module] = $data;

        $this->save();
    }

    /**
     * Remove module
     */
    public function remove(string $context, string $module): void
    {
        if (isset($this->cache[$context][$module])) {
            unset($this->cache[$context][$module]);
            $this->save();
        }
    }

    /**
     * Check if module exists
     */

    public function has(string $module, ?string $context = null): bool
    {
        $module = strtolower($module);

        $contexts = $context
            ? [$context => $this->cache[$context] ?? []]
            : $this->cache;

        foreach ($contexts as $contextModules) {

            foreach ($contextModules as $key => $moduleData) {

                $keyLower = strtolower($key);

                /*
            |-------------------------------------------------------------
            | 1. EXACT MATCH
            |-------------------------------------------------------------
            */
                if ($keyLower === $module) {
                    return true;
                }

                /*
            |-------------------------------------------------------------
            | 2. SHORT NAME MATCH (vendor.module → module)
            |-------------------------------------------------------------
            */
                if (Str::afterLast($keyLower, '.') === $module) {
                    return true;
                }

                /*
            |-------------------------------------------------------------
            | 3. DISPLAY NAME MATCH
            |-------------------------------------------------------------
            */
                if (
                    isset($moduleData['name']) &&
                    strtolower($moduleData['name']) === $module
                ) {
                    return true;
                }
            }
        }

        return false;
    }



    /**
     * Find module by short name or vendor.module key
     *
     * Example:
     *  students
     *  unnovatebrains.students
     */

    public function findByName(string $name, ?string $context = null): ?array
    {
        $name = strtolower($name);

        /*
    |-------------------------------------------------------------
    | 1. LIMIT SEARCH SCOPE
    |-------------------------------------------------------------
    | If context is given → search only inside it
    | Otherwise → search all contexts
    */

    if(!$this->hasContext($context)) return null;


        $contexts = $context
            ?  [$this->cache[$context ?? []]]
            : $this->cache;

            

        foreach ($contexts as $ctx => $modules) {

            foreach ($modules as $key => $module) {

                $keyLower = strtolower($key);

                /*
            |-------------------------------------------------------------
            | 1. EXACT MATCH
            |-------------------------------------------------------------
            */
                if ($keyLower === $name) {
                    return $module;
                }

                /*
            |-------------------------------------------------------------
            | 2. SHORT NAME MATCH (vendor.module → module)
            |-------------------------------------------------------------
            */
                if (Str::afterLast($keyLower, '.') === $name) {
                    return $module;
                }

                /*
            |-------------------------------------------------------------
            | 3. DISPLAY NAME MATCH
            |-------------------------------------------------------------
            */
                if (
                    isset($module['name']) &&
                    strtolower($module['name']) === $name
                ) {
                    return $module;
                }
            }
        }

        return null;
    }
}
