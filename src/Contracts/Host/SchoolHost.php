<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Contracts\Host;

use SchoolPalm\ModuleBridge\Support\ContextData;

/**
 * Interface SchoolHost
 *
 * Provides the current school context to modules.
 *
 * A school always belongs to a tenant and represents
 * the operational institution context.
 *
 * In SDK runtime, this is resolved from fake JSON data.
 */
interface SchoolHost
{
    /**
     * Get current school context array representation.
     */
    public function currentArray(): ?array;

    /**
     * Get the current school context.
     * Pass $asArray = true for background queues and view context data.
     */
    public function current(bool $asArray = false): null|array|ContextData;

    /**
     * Get the school ID.
     */
    public function id(): int|string|null;

    /**
     * Get the school name.
     */
    public function name(): ?string;

    /**
     * Get the school code (unique identifier).
     */
    public function code(): ?string;

    /**
     * Get the school's academic level (primary, secondary, etc.).
     */
    public function academicLevel(): ?string;

    /**
     * Get school status (active, suspended, etc.).
     */
    public function status(): ?string;

    /**
     * Get raw metadata attached to the school.
     */
    public function metadata(): array;
}
