<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Contracts\Host;

use SchoolPalm\ModuleBridge\Support\ContextData;

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
     * Get current user context array representation.
     */
    public function currentArray(): ?array;

    /**
     * Get the current user context.
     * Pass $asArray = true for background queues and view context data.
     */
    public function current(bool $asArray = false): null|array|ContextData;

    /**
     * Get the current user ID.
     */
    public function id(): int|string|null;

    /**
     * Get the user's email address.
     */
    public function email(): ?string;

    /**
     * Get the user's roles.
     *
     * @return array<string> List of role names assigned to the user.
     */
    public function roles(): array;

    /**
     * Get the current active portal (e.g. admin, teacher, student).
     */
    public function currentPortal(): ?string;
}
