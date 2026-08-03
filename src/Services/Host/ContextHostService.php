<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Services\Host;

use SchoolPalm\ModuleBridge\Contracts\Host\ContextHost;
use SchoolPalm\ModuleBridge\Contracts\Host\ModuleHost;
use SchoolPalm\ModuleBridge\Contracts\Host\TenantHost;
use SchoolPalm\ModuleBridge\Contracts\Host\SchoolHost;
use SchoolPalm\ModuleBridge\Contracts\Host\UserHost;

/**
 * ContextHostService
 *
 * Orchestrates tenant, school, and user into a single
 * consistent runtime context for modules.
 */
class ContextHostService implements ContextHost
{
    public function __construct(
        protected TenantHost $tenant,
        protected SchoolHost $school,
        protected UserHost $user,
        protected ModuleHost $module,

    ) {}

    /**
     * Get current tenant
     */
    public function tenant(): ?object
    {
        return $this->tenant->current();
    }

    /**
     * Get current school
     */
    public function school(): ?object
    {
        return $this->school->current();
    }

    /**
     * Get current user
     */
    public function user(): ?object
    {
        return $this->user->current();
    }

    /**
     * Get current user
     */
    public function module(): ?object
    {
        return $this->module->current();
    }
}
