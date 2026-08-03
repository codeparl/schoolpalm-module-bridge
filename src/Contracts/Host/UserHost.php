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
interface UserHost
{
    /**
     * Get the current user as a generic object.
     *
     * @return object|null Current authenticated user or null if not available.
     */
    public function current(): ?object;

    /**
     * Get the current user ID.
     *
     * @return int|null User identifier or null if no active user.
     */
    public function id(): ?int;

    /**
     * Get the user's email address.
     *
     * @return string|null Email or null if not available.
     */
    public function email(): ?string;

    /**
     * Get the user's roles.
     *
     * @return array List of role names assigned to the user.
     */
    public function roles(): array;

    /**
     * Get the current active portal (e.g. admin, teacher, student).
     *
     * @return string|null Active portal identifier.
     */
    public function currentPortal(): ?string;
}