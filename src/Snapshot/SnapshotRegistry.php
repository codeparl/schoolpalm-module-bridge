<?php

namespace SchoolPalm\ModuleBridge\Snapshot;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\File;

class SnapshotRegistry
{
    protected string $file;
    protected array $data = [];

    public function __construct(
        protected Cache $cache,
        ?string $path = null
    ) {
        $this->file = $path ?? storage_path('app/sdk/snapshots.json');
        $this->load();
    }

    /**
     * Create a registry instance with custom path (fluent + facade friendly)
     */
    public  function make(?string $path = null): self
    {
        // Resolve from Laravel container (works with facade too)
        return app(self::class, ['path' => $path]);
    }

    /**
     * Load from cache or file
     */
    protected function load(): void
    {
        $this->data = $this->cache->rememberForever('snapshots.registry', function () {
            if (!File::exists($this->file)) {
                File::ensureDirectoryExists(dirname($this->file));
                File::put($this->file, json_encode(new \stdClass(), JSON_PRETTY_PRINT));
            }

            return json_decode(File::get($this->file), true) ?? [];
        });
    }

    /**
     * Persist to disk + cache
     */
    protected function save(): void
    {
        File::put($this->file, json_encode($this->data, JSON_PRETTY_PRINT));

        $this->cache->forever('snapshots.registry', $this->data);
    }

    /**
     * Get all snapshots
     */
    public function all(): array
    {
        return $this->data;
    }

    public function get(string $moduleKey, string $version): ?array
    {
        return $this->data[$moduleKey][$version] ?? null;
    }

    public function add(string $moduleKey, string $version, array $snapshot): void
    {
        $this->data[$moduleKey][$version] = $snapshot;
        $this->save();
    }

    public function remove(string $moduleKey, string $version): void
    {
        if (!isset($this->data[$moduleKey][$version])) {
            return;
        }

        $snapshot = $this->data[$moduleKey][$version];

        if (!empty($snapshot['path']) && File::exists($snapshot['path'])) {
            File::delete($snapshot['path']);
        }

        unset($this->data[$moduleKey][$version]);

        if (empty($this->data[$moduleKey])) {
            unset($this->data[$moduleKey]);
        }

        $this->save();
    }
}