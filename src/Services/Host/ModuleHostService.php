<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Services\Host;

use SchoolPalm\ModuleBridge\Contracts\Host\ModuleHost;
use SchoolPalm\ModuleBridge\Facades\CreatedRegistry;
use SchoolPalm\ModuleBridge\Support\ContextData;
use SchoolPalm\ModuleBridge\Support\Helper;

class ModuleHostService implements ModuleHost
{
    protected static ?array $testContext = null;

    public static function fake(?array $context = null): void
    {
        static::$testContext = $context ?? [
            'name'          => 'Test Module',
            'namespace'     => 'SchoolPalm\\TestModule',
            'module_key'    => 'schoolpalm.testmodule',
            'manifest_path' => null,
            'path'          => null,
        ];
    }

    public static function tearDownFake(): void
    {
        static::$testContext = null;
    }

    public function name(): ?string
    {
        return $this->currentArray()['name'] ?? null;
    }

    public function moduleKey(): ?string
    {
        return $this->currentArray()['module_key'] ?? null;
    }

    public function moduleNamespace(): ?string
    {
        return $this->currentArray()['namespace'] ?? null;
    }

    /**
     * Get module context as a plain array by default.
     */
    public function currentArray(): array
    {
        if (static::$testContext !== null) {
            return static::$testContext;
        }

        $moduleKey = Helper::getPathSegment('module');

        if ($moduleKey) {
            $module = CreatedRegistry::get($moduleKey);

            if ($module) {
                $name = $module['name'] ?? ucfirst(str_replace(['.', '_', '-'], ' ', $moduleKey));

                return [
                    'name'          => $name,
                    'namespace'     => $module['namespace'] ?? null,
                    'module_key'    => $module['module_key'] ?? null,
                    'manifest_path' => $module['manifest'] ?? null,
                    'path'          => $module['path'] ?? null,
                ];
            }
        }

        return $this->defaultContextArray($moduleKey);
    }

    /**
     * Resolve and return current module context.
     * Pass $asArray = true for plain arrays (ideal for queues and view context).
     */
    public function current(bool $asArray = false): array|ContextData
    {
        $data = $this->currentArray();

        return $asArray ? $data : ContextData::make($data);
    }

    protected function defaultContextArray(?string $moduleKey = null): array
    {
        return [
            'name'          => 'Global System Context',
            'namespace'     => 'App',
            'module_key'    => $moduleKey ?? 'app',
            'manifest_path' => null,
            'path'          => null,
        ];
    }
}
