<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Services;

use SchoolPalm\AppLogger\Context\AppContext;
use SchoolPalm\CacheStore\Contracts\CacheContextResolver;
use SchoolPalm\ModuleBridge\Contracts\Host\ContextHost;
use UnnovateBrains\DocumentBuilder\Contracts\DocumentContextResolver;

final class ContextResolver implements DocumentContextResolver, CacheContextResolver
{
    private ?string $explicitTenantId = null;
    private ?string $explicitSchoolId = null;

    public function __construct(
        private readonly ContextHost $contextHost
    ) {}

    /*
    |--------------------------------------------------------------------------
    | General Module Context Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve full runtime context payload.
     * Renamed to avoid collision with CacheContextResolver::resolve(string $key).
     *
     * @return array<string, mixed>
     */
    public function resolvePayload(): array
    {
        $tenant = $this->contextHost->tenant();
        $school = $this->contextHost->school();
        $user   = $this->contextHost->user();
        $module = $this->contextHost->module();

        return [
            'tenant'    => $tenant,
            'tenant_id' => $tenant?->id,
            'school'    => $school,
            'school_id' => $school?->school_code,
            'user'      => $user,
            'user_id'   => $user?->id,
            'channel'   => $module?->name,
            'locale'    => app()->getLocale(),
            'timezone'  => config('app.timezone'),
        ];
    }

    public function settingsScope(): array
    {
        return [
            'tenant_id' => $this->tenantId() ?: null,
            'school_id' => $this->schoolId() ?: null,
            'user_id'   => $this->userId() ?: null,
        ];
    }

    public function identifiers(): array
    {
        $context = $this->resolvePayload();

        return [
            'tenant_id' => $context['tenant_id'],
            'school_id' => $context['school_id'],
            'channel'   => $context['channel'],
        ];
    }

    public function channel(): string
    {
        if (method_exists($this->contextHost, 'module') && $this->contextHost->module() !== null) {
            return (string) $this->contextHost->module()->name;
        }

        return (string) config('module-bridge.default_channel', 'module_bridge');
    }

    public function appContext(): AppContext
    {
        return new AppContext($this->resolvePayload());
    }

    /*
    |--------------------------------------------------------------------------
    | CacheContextResolver Implementation
    |--------------------------------------------------------------------------
    */

    public function forContext(?string $tenantId, ?string $schoolId): static
    {
        $this->explicitTenantId = $tenantId;
        $this->explicitSchoolId = $schoolId;

        return $this;
    }

    /**
     * CacheContextResolver Contract: Resolves key to context-prefixed key.
     */
    public function resolve(string $key): string
    {
        $tenantId = $this->tenantId();
        $schoolId = $this->schoolId();
        $prefix = (string) config('cache-store.prefix', 'schoolpalm');
        $separator = (string) config('cache-store.key_separator', ':');

        $parts = [$prefix];

        if ($tenantId !== '' && config('cache-store.context.tenant', true)) {
            $parts[] = 'tenant';
            $parts[] = $tenantId;
        }

        if ($schoolId !== '' && config('cache-store.context.school', true)) {
            $parts[] = 'school';
            $parts[] = $schoolId;
        }

        $parts[] = $key;

        return implode($separator, array_filter($parts));
    }

    public function hasContext(): bool
    {
        return $this->tenantId() !== '' || $this->schoolId() !== '';
    }

    public function tenantId(): string
    {
        return $this->explicitTenantId ?? (string) $this->contextHost->tenant()?->id;
    }

    public function schoolCode(): string
    {
        return (string) $this->contextHost->school()?->school_code;
    }

    public function schoolId(bool $id = false): string
    {
        if ($this->explicitSchoolId !== null) {
            return $this->explicitSchoolId;
        }

        if ($id) {
            return (string) $this->contextHost->school()?->id;
        }

        return (string) $this->contextHost->school()?->school_code;
    }

    public function userId(): string
    {
        return (string) $this->contextHost->user()?->id;
    }

    /*
    |--------------------------------------------------------------------------
    | Context Restoration & Reset
    |--------------------------------------------------------------------------
    */

    public function initializeTenant(string|int $tenantId): void
    {
        if (! function_exists('tenancy')) {
            return;
        }

        $tenant = \App\Models\Tenant::findOrFail($tenantId);
        tenancy()->initialize($tenant);
    }

    public function initializeSchool(string|int $schoolId): void
    {
        if (app()->bound('school.context')) {
            app('school.context')->set($schoolId);
        }
    }

    public function clear(): void
    {
        $this->explicitTenantId = null;
        $this->explicitSchoolId = null;

        if (function_exists('tenancy')) {
            tenancy()->end();
        }

        if (app()->bound('school.context')) {
            app('school.context')->clear();
        }
    }
}
