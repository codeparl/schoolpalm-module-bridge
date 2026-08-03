<?php

namespace SchoolPalm\ModuleBridge\Manifest;

final class ManifestDependencies
{
    public function __construct(
        private readonly array $data
    ) {}

    public function raw(): array
    {
        return $this->data;
    }

    /* ---------------- Backend ---------------- */

    public function backend(): array
    {
        return $this->data['backend'] ?? [];
    }

    public function php(): ?string
    {
        return $this->backend()['php'] ?? null;
    }

    public function requiresPhp(): bool
    {
        return isset($this->backend()['php']);
    }

    public function extensions(): array
    {
        return $this->backend()['extensions'] ?? [];
    }

    public function requiresExtensions(): bool
    {
        return !empty($this->extensions());
    }

    public function packages(): array
    {
        return $this->backend()['packages'] ?? [];
    }

    public function hasComposerPackages(): bool
    {
        return !empty($this->packages());
    }

    /* ---------------- Frontend ---------------- */

    public function frontend(): array
    {
        return $this->data['frontend'] ?? [];
    }

    public function hasFrontendDependencies(): bool
    {
        return !empty($this->frontend());
    }
}
