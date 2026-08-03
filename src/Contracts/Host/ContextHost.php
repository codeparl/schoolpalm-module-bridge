<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Contracts\Host;

/**
 * Interface ContextHost
 *
 * Provides a unified runtime context combining:
 * - Tenant (SaaS boundary)
 * - School (institution)
 * - User (actor)
 *
 * This ensures all module operations run within a consistent scope.
 */
interface ContextHost
{
    public function tenant(): ?object;

    public function school(): ?object;

    public function user(): ?object;
    public function module(): ?object;
}
