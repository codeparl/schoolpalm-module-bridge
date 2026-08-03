<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Services\Host;

use SchoolPalm\ModuleBridge\Contracts\Host\TenantHost;
use SchoolPalm\ModuleBridge\Support\Helper;
use App\Models\Tenant;

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
     * Cached SDK data
     */
    protected ?array $sdkTenant = null;

    /**
     * Get current tenant object
     * @return object|null|array
     */
    public function current(): ?object
    {
        if (Helper::isSdkRuntime()) {
            return (object) $this->sdkTenant();
        }

        return $this->realTenant();
    }

      /**
     * Get current tenant object
     * @return object|null|array
     */
    public function currentSchool(): ?object
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkTenant();
        }

        return currentSchool();
    }

    /**
     * Get tenant ID
     */
    public function tenantId(): ?string
    {
        if (Helper::isSdkRuntime()) {
            return  $this->sdkTenant()['id'] ?? 1;
        }

        return  tenant()?->id ?? null;
    }

    /**
     * Get current school ID
     *
     * In SchoolPalm: resolved from tenant context or active school
     * In SDK: fake value from JSON
     */
    public function schoolId(): ?int
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkTenant()['school_id'] ?? 1;
        }

        return currentSchool()->id;
    }

    /**
     * Real tenant (SchoolPalm runtime)
     */
    protected function realTenant(): ?object
    {
        $tenant = tenant();

        return $tenant ? (object) [
            'id' => $tenant->id,
            'tenant_name' => $tenant->tenant_name,
            'tenant_code' => $tenant->tenant_code ?? null,
            'plan_id' => $tenant->plan_id ?? null,
        ] : null;
    }

    /**
     * SDK tenant (fake data source)
     */
    protected function sdkTenant(): array|object
    {
        if ($this->sdkTenant !== null) {
            return $this->sdkTenant;
        }

        $path = Helper::dataFolder('tenants/demo_tenant/data.json');

        if (!file_exists($path)) {
            return $this->sdkTenant = [
                'id' => 1,
                'tenant_name' => 'SDK Demo Tenant',
                'school_id' => 1,
            ];
        }

        return $this->sdkTenant = Helper::loadJson($path);
    }
}
