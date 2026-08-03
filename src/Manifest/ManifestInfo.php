<?php

namespace SchoolPalm\ModuleBridge\Manifest;

final class ManifestInfo
{
    public function __construct(
        private readonly array $data
    ) {}

    /* =========================================
     * BASIC
     * ========================================= */

    public function name(): string
    {
        return $this->data['name'] ?? '';
    }

    public function vendor(): string
    {
        return $this->data['vendor'] ?? '';
    }

    public function moduleKey(): string
    {
        return $this->data['module_key'] ?? '';
    }

    public function namespace(): string
    {
        return $this->data['namespace'] ?? '';
    }

    public function prefix(): ?string
    {
        return $this->data['prefix'] ?? null;
    }

    public function context(): string
    {
        return $this->data['context'] ?? '';
    }

    public function type(): string
    {
        return $this->data['type'] ?? 'external';
    }

    public function version(): string
    {
        return $this->data['version'] ?? '1.0.0';
    }

    public function description(): string
    {
        return $this->data['description'] ?? '';
    }

    public function role(): ?string
    {
        return $this->data['role'] ?? null;
    }

    /* =========================================
     * UI / DISPLAY
     * ========================================= */

    public function icon(): ?string
    {
        return $this->data['icon'] ?? null;
    }

    public function image(): ?array
    {
        return $this->data['image'] ?? null;
    }

    /* =========================================
     * AUTHOR
     * ========================================= */

    public function author(): ManifestAuthor
    {
        return new ManifestAuthor($this->data['author'] ?? []);
    }

    public function authorName(): ?string
    {
        return $this->data['author']['name'] ?? null;
    }

    public function authorEmail(): ?string
    {
        return $this->data['author']['email'] ?? null;
    }

    public function authorWebsite(): ?string
    {
        return $this->data['author']['website'] ?? null;
    }

    /* =========================================
     * LICENSE
     * ========================================= */

    public function license(): ?array
    {
        return $this->data['license'] ?? null;
    }

    public function licenseType(): ?string
    {
        return $this->data['license']['type'] ?? null;
    }

    public function licensePath(): ?string
    {
        return $this->data['license']['path'] ?? null;
    }

    /* =========================================
     * SDK
     * ========================================= */

    public function sdk(): ?array
    {
        return $this->data['sdk'] ?? null;
    }

    public function sdkName(): ?string
    {
        return $this->data['sdk']['name'] ?? null;
    }

    public function sdkVersion(): ?string
    {
        return $this->data['sdk']['version'] ?? null;
    }

    /* =========================================
     * ENTRY
     * ========================================= */

    public function entry(): ?array
    {
        return $this->data['entry'] ?? null;
    }

    public function provider(): ?string
    {
        return $this->data['entry']['provider'] ?? null;
    }

    /* =========================================
     * FLAGS
     * ========================================= */

    public function isCommon(): bool
    {
        return (bool) ($this->data['is_common'] ?? false);
    }

    /* =========================================
     * RAW
     * ========================================= */

    public function raw(): array
    {
        return $this->data;
    }
}