<?php
declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Contracts\Host;

/**
 * Interface TenantHost
 *
 * Provides host-managed tenant information to modules.
 *
 * This contract is framework-agnostic; implementations map the host's
 * tenancy model (e.g., current tenant, school context) into simple values.
 *
 * @package SchoolPalm\ModuleBridge\Contracts\Host
 */
interface TenantHost
{
    /**
     * Get the current tenant object if available.
     *
     * @return object|null|array Host tenant domain object, if one exists.
     */
    public function current(): ?object;

    /**
     * Get the tenant ID.
     *
     * @return string|null Tenant identifier or null when not in tenant scope.
     */
    public function tenantId(): ?string;

    /**
     * Get the current school ID.
     *
     * @return int|null School identifier or null when not available.
     */
    public function schoolId(): ?int;

    /**
     * Get the current tenant school object if available.
     *
     * @return object|null|array Host tenant domain object, if one exists.
     */
    public function currentSchool(): ?object;
}
