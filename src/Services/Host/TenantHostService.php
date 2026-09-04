<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Services\Host;

use SchoolPalm\ModuleBridge\Contracts\Host\TenantHost;
use SchoolPalm\ModuleBridge\Support\ContextData;
use SchoolPalm\ModuleBridge\Support\Helper;

/**
 * TenantHostService
 *
 * Resolves tenant context for both:
 * - SchoolPalm runtime (real tenancy)
 * - SDK runtime (fake JSON data)
 */
class TenantHostService implements TenantHost
{
    /**
     * Cached SDK data array
     */
    protected ?array $sdkTenant = null;

    /**
     * Get current tenant data.
     * Pass $asArray = true for queue job payloads and Blade view parameters.
     */
    public function current(bool $asArray = false): null|array|ContextData
    {
        $data = Helper::isSdkRuntime()
            ? $this->sdkTenantArray()
            : $this->realTenantArray();

        if ($data === null) {
            return null;
        }

        return $asArray ? $data : ContextData::make($data);
    }

    /**
     * Get current school context.
     */
    public function currentSchool(bool $asArray = false): null|array|ContextData
    {
        if (Helper::isSdkRuntime()) {
            $data = $this->sdkTenantArray();
            return $asArray ? $data : ContextData::make($data);
        }

        $school = currentSchool();
        if (!$school) {
            return null;
        }

        $data = is_array($school) ? $school : (array) $school;

        return $asArray ? $data : ContextData::make($data);
    }

    /**
     * Get tenant ID
     */
    public function tenantId(): ?string
    {
        if (Helper::isSdkRuntime()) {
            return (string) ($this->sdkTenantArray()['id'] ?? 'sdk_demo_tenant');
        }

        return tenant()?->id !== null ? (string) tenant()->id : null;
    }

    /**
     * Get current school ID
     */
    public function schoolId(): null|int|string
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkTenantArray()['school_id'] ?? 'sdk_demo_school';
        }

        return currentSchool()?->id ?? null;
    }

    /**
     * Get school code
     */
    public function schoolCode(): null|int|string
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkTenantArray()['school_code'] ?? 'sdk_demo_school_001';
        }

        return currentSchool()?->school_code ?? null;
    }

    /**
     * Real tenant array representation
     */
    protected function realTenantArray(): ?array
    {
        $tenant = tenant();

        if (!$tenant) {
            return null;
        }

        return [
            'id'          => $tenant->id,
            'tenant_name' => $tenant->tenant_name ?? null,
            'tenant_code' => $tenant->tenant_code ?? null,
            'plan_id'     => $tenant->plan_id ?? null,
        ];
    }

    /**
     * SDK tenant array representation
     */
    protected function sdkTenantArray(): array
    {
        if ($this->sdkTenant !== null) {
            return $this->sdkTenant;
        }

        $path = Helper::dataFolder('/tenants/demo_tenant/data.json');

        if (!file_exists($path)) {
            return $this->sdkTenant = [
                'id'          => 'sdk_demo_tenant',
                'tenant_id'   => 'sdk_demo_tenant',
                'tenant_name' => 'SDK Demo Tenant',
                'school_id'   => 'sdk_demo_school',
                'school_code' => 'sdk_demo_school_001',
            ];
        }

        return $this->sdkTenant = Helper::loadJson($path) ?? [];
    }
}
