<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Contracts\Host;

use SchoolPalm\ModuleBridge\Support\ContextData;

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
     * Get the current tenant context.
     * Pass $asArray = true for background queues and view context data.
     */
    public function current(bool $asArray = false): null|array|ContextData;

    /**
     * Get the current tenant school context.
     * Pass $asArray = true for background queues and view context data.
     */
    public function currentSchool(bool $asArray = false): null|array|ContextData;

    /**
     * Get the tenant ID.
     */
    public function tenantId(): ?string;

    /**
     * Get the current school ID.
     */
    public function schoolId(): int|string|null;

    /**
     * Get the school code.
     */
    public function schoolCode(): int|string|null;
}
