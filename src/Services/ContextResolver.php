<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Services;

use SchoolPalm\AppLogger\Context\AppContext;
use SchoolPalm\CacheStore\Contracts\CacheContextResolver;
use SchoolPalm\MessageDelivery\Contracts\TenantProviderSettings;
use SchoolPalm\ModuleBridge\Contracts\Host\ContextHost;
use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;
use UnnovateBrains\DocumentBuilder\Contracts\DocumentContextResolver;

final class ContextResolver implements DocumentContextResolver, CacheContextResolver, TenantProviderSettings
{
    /**
     * Only used by cloned instances created through forContext().
     * The application singleton never changes these values.
     */
    private ?string $contextTenantId = null;
    private ?string $contextSchoolId = null;

    public function __construct(
        private readonly ContextHost $contextHost
    ) {}

    /**
     * Convert full host context to primitive array structure.
     * Guaranteed to pass array values for view proxies, mailers, and queue payloads.
     */
    public function toArray(): array
    {
        return [
            'tenant'           => $this->contextHost->tenant(true),
            'school'           => $this->contextHost->school(true),
            'user'             => $this->contextHost->user(true),
            'module'           => $this->contextHost->module(true),
            'tenant_id'        => $this->tenantId(),
            'school_id'        => $this->schoolId(true),
            'school_code'      => $this->schoolCode(),
            'user_id'          => $this->userId(),
            'module_key'       => $this->currentModuleKey(),
            'module_name'      => $this->currentModule(),
            'module_namespace' => $this->currentModuleNamespace(),
            'channel'          => $this->channel(),
            'locale'           => app()->getLocale(),
            'timezone'         => config('app.timezone'),
        ];
    }

    /**
     * Resolve runtime context payload.
     */
    public function resolvePayload(): array
    {
        return $this->toArray();
    }

    public function settingsScope(): array
    {
        return [
            'tenant_id' => $this->tenantId() ?: null,
            'school_id' => $this->schoolId(true) ?: null,
            'user_id'   => $this->userId() ?: null,
        ];
    }

    public function currentModuleKey(): ?string
    {
        return $this->contextHost->module()?->module_key;
    }

    public function currentModuleNamespace(): ?string
    {
        return $this->contextHost->module()?->namespace;
    }

    public function currentModule(): ?string
    {
        return $this->contextHost->module()?->name;
    }

    public function identifiers(): array
    {
        return [
            'tenant_id' => $this->tenantId(),
            'school_id' => $this->schoolId(true),
            'channel'   => $this->channel(),
        ];
    }

    public function channel(): string
    {
        return (string) (
            $this->contextHost->module()?->name
            ?? config('module-bridge.default_channel', 'module_bridge')
        );
    }

    public function appContext(): AppContext
    {
        return new AppContext($this->resolvePayload());
    }

    /*
    |--------------------------------------------------------------------------
    | TenantProviderSettings
    |--------------------------------------------------------------------------
    */

    public function providerFor(string $channel): ?string
    {

        return SettingsHost::group(
            "message_delivery.{$channel}"
        )->get('default_provider');
    }

    public function configurationFor(
        string $channel,
        string $provider
    ): array {
        $config = SettingsHost::group(
            "message_delivery.{$channel}.{$provider}"
        )->get('config', []);

        if (is_array($config)) {
            return $config;
        }

        if (is_string($config)) {
            $decoded = json_decode($config, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    public function enabled(
        string $channel,
        string $provider
    ): bool {
        return filter_var(
            SettingsHost::group(
                "message_delivery.{$channel}.{$provider}"
            )->get('enabled', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CacheContextResolver
    |--------------------------------------------------------------------------
    */

    /**
     * Required by CacheContextResolver contract.
     *
     * Does NOT mutate this resolver.
     */
    public function forContext(
        ?string $tenantId,
        ?string $schoolId
    ): static {
        $clone = clone $this;

        $clone->contextTenantId = $tenantId;
        $clone->contextSchoolId = $schoolId;

        return $clone;
    }

    public function resolve(string $key): string
    {
        $tenantId = $this->tenantId();
        $schoolId = $this->schoolId();

        $prefix = config(
            'cache-store.prefix',
            'schoolpalm'
        );

        $separator = config(
            'cache-store.key_separator',
            ':'
        );

        $parts = [$prefix];

        if ($tenantId !== '') {
            $parts[] = 'tenant';
            $parts[] = $tenantId;
        }

        if ($schoolId !== '') {
            $parts[] = 'school';
            $parts[] = $schoolId;
        }

        $parts[] = $key;

        return implode(
            $separator,
            $parts
        );
    }

    public function hasContext(): bool
    {
        return $this->tenantId() !== ''
            || $this->schoolId() !== '';
    }

    public function tenantId(): string
    {

        if ($this->contextTenantId !== null) {
            return $this->contextTenantId;
        }


        return (string) $this->contextHost
            ->tenant()?->id;
    }

    public function schoolCode(): string
    {
        return (string) $this->contextHost
            ->school()?->school_code;
    }

    public function schoolId(bool $id = false): string
    {
        if ($this->contextSchoolId !== null) {
            return $this->contextSchoolId;
        }

        $school = $this->contextHost->school();

        if (!$school) {
            return '';
        }

        return $id
            ? (string) $school->id
            : (string) $school->school_code;
    }

    public function userId(): string
    {
        return (string) $this->contextHost
            ->user()?->id;
    }

    public function initializeTenant(string|int $tenantId): void
    {
        if (!function_exists('tenancy')) {
            return;
        }

        tenancy()->initialize(
            \App\Models\Tenant::findOrFail($tenantId)
        );
    }

    public function initializeSchool(string|int $schoolId): void
    {
        if (app()->bound('school.context')) {
            app('school.context')->set($schoolId);
        }
    }

    public function clear(): void
    {
        if (function_exists('tenancy')) {
            tenancy()->end();
        }

        if (app()->bound('school.context')) {
            app('school.context')->clear();
        }
    }
}
