<?php

namespace SchoolPalm\ModuleBridge\Manifest;

final class ManifestRequires
{
    public function __construct(
        private readonly array $data
    ) {}

    public function raw(): array
    {
        return $this->data;
    }

    /* ---------------- Required Modules ---------------- */

    public function modules(): array
    {
        return $this->data['modules'] ?? [];
    }

    public function hasModules(): bool
    {
        return !empty($this->modules());
    }

    public function requiresModule(string $moduleKey): bool
    {
        return array_key_exists($moduleKey, $this->modules());
    }

    public function moduleConstraint(string $moduleKey): ?string
    {
        return $this->modules()[$moduleKey] ?? null;
    }

    public function eachModule(callable $callback): void
    {
        foreach ($this->modules() as $key => $constraint) {
            $callback($key, $constraint);
        }
    }

   
}
