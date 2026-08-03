<?php

namespace SchoolPalm\ModuleBridge\Manifest;
use SchoolPalm\ModuleBridge\Support\Helper ;

final class ModuleManifest
{
    private ?array $data = null;

    private array $cache = [];

    private ?array $pipeline = null;

    public array $dependencyContracts = [];
    public array $registeredProviders = [];
    public array $providedContracts = [];

    public function __construct(private readonly string|array $path)
    {
        if (is_string($path) && !file_exists($path)) {
            throw new \RuntimeException("Module manifest not found at: {$path}");
        }

        if (is_array($path)) {
            $this->data = $path;
        }

        $this->initializePipelineData();
        
    }

    /* =========================================================
     * CORE LOADING
     * ========================================================= */

    private function load(): array
    {
        if ($this->data === null) {
            $json = Helper::loadJson($this->path);

            if (!is_array($json)) {
                throw new \RuntimeException("Invalid module manifest JSON");
            }

            $this->data = $json;
        }

        return $this->data;
    }

    public function raw(): array
    {
        return $this->load();
    }

    /* =========================================================
     * PIPELINE LOADING
     * ========================================================= */

    public function pipeline(): array
    {
        if ($this->pipeline !== null) {
            return $this->pipeline;
        }

       
        $pipelinePath = $this->path() . '/pipeline.json';

        if (!file_exists($pipelinePath)) {
            throw new \RuntimeException("pipeline.json not found for module");
        }

        $json = Helper::loadJson($pipelinePath);

        if (!is_array($json)) {
            throw new \RuntimeException("Invalid pipeline.json structure");
        }

        return $this->pipeline = $this->normalizePipeline($json);
    }

    private function normalizePipeline(array $pipeline): array
    {
        $normalized = [];

        foreach ($pipeline as $phase) {

            $phaseName = $phase['phase'] ?? null;

            if (!$phaseName) {
                throw new \RuntimeException("Pipeline phase missing name");
            }

            $normalized[$phaseName] = [
                'phase' => $phaseName,
                'steps' => []
            ];

            foreach ($phase['steps'] ?? [] as $step) {

                $key = $step['key'] ?? null;

                if (!$key) {
                    throw new \RuntimeException("Pipeline step missing key");
                }

                $normalized[$phaseName]['steps'][$key] = [
                    'key' => $key,
                    'name' => $step['name'] ?? $key,
                    'description' => $step['description'] ?? null,
                ];
            }
        }

        return $normalized;
    }

    public function pipelinePhases(): array
    {
        return array_keys($this->pipeline());
    }

    public function pipelineSteps(string $phase): array
    {
        return $this->pipeline()[$phase]['steps'] ?? [];
    }

    public function findStep(string $key): ?array
    {
        foreach ($this->pipeline() as $phase) {
            if (isset($phase['steps'][$key])) {
                return $phase['steps'][$key];
            }
        }

        return null;
    }

    /* =========================================================
     * PIPELINE INITIALIZATION
     * ========================================================= */

    private function initializePipelineData(): void
    {
        $data = $this->load();

        $this->dependencyContracts = $data['dependencies']['backend'] ?? [];
        $this->providedContracts   = $data['provides'] ?? [];

        $entryProvider = $data['entry']['provider'] ?? null;

        $this->registeredProviders = $entryProvider
            ? [$entryProvider]
            : [];
    }

    /* =========================================================
     * BASIC INFO
     * ========================================================= */

    public function key(): string
    {
        return $this->load()['module_key'];
    }

    public function info(): ManifestInfo
    {
        return $this->cache['info'] ??=
            new ManifestInfo($this->load());
    }

    public function isCommon(): bool
    {
        return (bool) ($this->load()['is_common'] ?? false);
    }
    public function isProtected(): bool
    {
        return (bool) ($this->load()['is_protected'] ?? false);
    }

     public function root(): string
    {
        return $this->load()['root'];
    }

    public function vendor(): string
    {
        return $this->info()->vendor();
    }

    public function module(): string
    {
        return Helper::moduleFolderName($this->key());
    }

     public function moduleEntry(): string
    {
        $entry  = $this->load()['actionEntry'] ?? null;
        $entry  =  $entry ? class_basename($entry).'.php'  :'ModuleActionEntry.php'; 
        return $entry;
    }

     public function moduleFullEntry(): string
    {
        return  $this->load()['actionEntry'] ?? null;
    }

    public function level(): array
    {
        return $this->load()['level'] ?? [];
    }

    public function folder(): string
    {
        return $this->info()->context();
    }

    /* =========================================================
     * PATHS
     * ========================================================= */

    public function path(): string
    {
      return dirname($this->path);
    }

 public function modulePath(): string
    {
      return $this->path().'/Backend/ModuleActionEntry.php';
    }

     public function moduleExecutionPath(): string
    {
      return config('sdk.modules_path').'/'. $this->root().'/Backend/ModuleActionEntry.php';
    }
    public function moduleBasePath(): string
    {
        return $this->path();
    }
    /* =========================================================
     * MAGIC RESOLVER 
     * ========================================================= */

    public function __get(string $name): mixed
    {
        return $this->cache[$name] ??=
            $this->resolveSection($name);
    }
        function resolveSection(string $name): mixed
    {
        $data = $this->load();

        return match ($name) {

            // System sections
            'requires' => new ManifestRequires($data['requires'] ?? []),
            'sdk' => new ManifestSDK($data['sdk'] ?? []),
            'dependencies' => new ManifestDependencies($data['dependencies'] ?? []),
            'entry' => new ManifestEntry($data['entry'] ?? []),
            'migrations' => new ManifestMigrations($data['migrations'] ?? []),
            'dtos' => $data['dtos'] ?? [],
            //frontend resources
            'frontend' => new ManifestResources($data['frontend'] ?? []),
            // Arrays / raw
            'menus' => $this->buildMenus($data['menus'] ?? []),
            'actions' => $this->buildActions($data['actions'] ?? []),
            'models' => (object) ($data['models'] ?? []),
            'provides' => $data['provides'] ?? [],
            'events' => $data['events'] ?? [],

            default => null,
        };
    }

        /* =========================================================
     * HELPERS
     * ========================================================= */

    private function buildMenus(array $menus): array
    {
        return array_map(
            fn($item) => new ManifestMenuItem($item),
            $menus
        );
    }

    private function buildActions(array $actions): array
    {
        return array_map(
            fn($item) => new ManifestActionItem($item),
            $actions
        );
    }
    /* =========================================================
     * SECURITY
     * ========================================================= */

    public function checksum(): string
    {
        return hash('sha256', json_encode($this->load()));
    }


}
