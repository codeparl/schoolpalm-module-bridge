<?php

namespace SchoolPalm\ModuleBridge\Platform;

use Illuminate\Support\Facades\File;
use SchoolPalm\ModuleBridge\Support\Helper;

class ConfigFetcher
{
    protected ?string $type;
    protected string $endpoint;
    protected string $cachePath;
    protected int $ttl;

    public function __construct(
        ?string $type = null,
        ?string $endpoint = null,
        ?string $cachePath = null,
        int $ttl = 86400
    ) {
        $this->type = $type;

        $baseUrl = rtrim(config('sdk.app.url'), '/');
        $apiPath = trim(config('sdk.app.api.config'), '/');

        // Endpoint changes based on type
        $this->endpoint = $endpoint ?? (
            $this->type
            ? "{$baseUrl}/api/{$apiPath}/{$this->type}"
            : "{$baseUrl}/api/{$apiPath}"
        );

        // ✅ Cache path
        $this->cachePath = $cachePath ?? (
            $this->type
            ? Helper::dataFolder() . "{$this->type}.json"
            : Helper::dataFolder() . "config.json"
        );

        $this->ttl = $ttl;
    }

    public function type(?string $type): self
    {
        return new self(
            type: $type,
            endpoint: $this->endpoint . "/{$type}",
            cachePath: Helper::dataFolder() . "{$type}.json",
            ttl: $this->ttl
        );
    }


    /**
     * Get normalized data
     */
    public function all(): array
    {
        $config = $this->getConfig();

        // If specific type requested
        if ($this->type) {
            if (isset($config[$this->type])) {
                return $config[$this->type];
            }

            if (array_is_list($config)) {
                return $config;
            }

            return [];
        }

        // If full config requested
        return is_array($config) ? $config : [];
    }

    /**
     * Get cached or API config
     */
    public function getConfig(): array
    {
        if ($this->isCacheValid()) {
            return $this->readCache();
        }

        $data = $this->fetchFromApi();

        if (!empty($data)) {
            $this->writeCache($data);
            return $data;
        }

        return $this->readCache();
    }

    /**
     * Force refresh
     */
    public function refresh(): array
    {
        $data = $this->fetchFromApi();

        if (!empty($data)) {
            $this->writeCache($data);
        }

        return $data;
    }

    /**
     * Generic find
     */
    public function find(string|int $value, string $field = 'code'): ?array
    {
        foreach ($this->all() as $item) {
            if (($item[$field] ?? null) == $value) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Filter by level
     */
    public function forLevel(int $levelId): array
    {
        return array_values(array_filter($this->all(), function ($item) use ($levelId) {
            return in_array($levelId, $item['levels'] ?? []);
        }));
    }

    /**
     * -------------------------
     * Internal Helpers
     * -------------------------
     */

    protected function fetchFromApi(): array
    {
        try {
            $response = file_get_contents($this->endpoint);

            if ($response === false) {
                return [];
            }

            $data = json_decode($response, true);

            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function isCacheValid(): bool
    {
        if (!file_exists($this->cachePath)) {
            return false;
        }

        return (time() - filemtime($this->cachePath)) < $this->ttl;
    }

    public function readCache(?string $type = null): array
    {

        if ($type !== null)
            return $this->readByType($type);

        $content = Helper::loadJson($this->cachePath);
        return $content ?? [];
    }



    protected function readByType($type)
    {

        $cachePath = Helper::dataFolder() . "{$type}.json";
        $content = Helper::loadJson($cachePath);
        return is_array($content) ? $content : [];
    }

    protected function writeCache(array $data): void
    {
        File::ensureDirectoryExists(dirname($this->cachePath));

        file_put_contents(
            $this->cachePath,
            json_encode($data, JSON_PRETTY_PRINT)
        );
    }
}
