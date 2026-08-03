<?php

namespace SchoolPalm\ModuleBridge\Manifest;

use SchoolPalm\ModuleBridge\Support\Helper;

final class ManifestMigrations
{
    public function __construct(
        private readonly array $data
    ) {}

    public function raw(): array
    {
        return $this->data;
    }

    public function path(): string
    {
        return $this->data['path'] ?? 'Database/migrations';
    }

     public function seedersPath(): string
    {
        return Helper::beforeLast($this->path(),DIRECTORY_SEPARATOR)
        .'/Seeders';
    }

    public function runOnInstall(): bool
    {
        return (bool) ($this->data['run_on_install'] ?? true);
    }

    public function runOnUpdate(): bool
    {
        return (bool) ($this->data['run_on_update'] ?? true);
    }

    public function shouldRun(bool $isInstall = true): bool
    {
        return $isInstall ? $this->runOnInstall() : $this->runOnUpdate();
    }

     public function tables(bool $onlyModels = false): array
    {
        if($onlyModels){
            return collect($this->data['tables'] ?? [])->filter(fn($t)=> $t['model'] )->toArray();
        }

        return $this->data['tables'] ?? [];
    }
}
