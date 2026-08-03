<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Contracts\Host;

/**
 * Interface UserHost
 *
 * Provides the currently authenticated user context
 * to modules in a framework-agnostic way.
 *
 * In SchoolPalm runtime, this maps to the authenticated user
 * within the active school context.
 *
 * In SDK runtime, this is resolved from fake JSON data.
 */
interface ModuleHost
{
    /**
     * Get the current user as a generic object.
     *
     * @return object|null Current authenticated user or null if not available.
     */
    public function name(): ?string;

    /**
     * Get the module namespace for the current user.
     *
     * @return int|null User identifier or null if no active user.
     */
    public function moduleNamespace(): ?string;

    /**
     * Get the current user as a generic object.
     *
     * @return object|null Current authenticated user or null if not available.
     */
    public function current(): ?object;
}
