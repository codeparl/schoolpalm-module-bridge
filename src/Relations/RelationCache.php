<?php

namespace SchoolPalm\ModuleBridge\Relations;

/**
 * Simple in-memory cache for relation batch results during a load cycle.
 */
class RelationCache
{
    protected array $cache = [];

    public function get(string $key): mixed
    {
        return $this->cache[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->cache[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }
}
