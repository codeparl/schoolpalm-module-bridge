<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Contracts\Host;

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
     * Get the current school as a generic object.
     *
     * @return object|null Current school or null if not available.
     */
    public function current(): ?object;

    /**
     * Get the school ID.
     *
     * @return int|null School identifier.
     */
    public function id(): ?int;

    /**
     * Get the school name.
     *
     * @return string|null School name.
     */
    public function name(): ?string;

    /**
     * Get the school code (unique identifier).
     *
     * @return string|null School code.
     */
    public function code(): ?string;

    /**
     * Get the school's academic level (primary, secondary, etc.).
     *
     * @return string|null Academic level.
     */
    public function academicLevel(): ?string;

    /**
     * Get school status (active, suspended, etc.).
     *
     * @return string|null Status of the school.
     */
    public function status(): ?string;

    /**
     * Get raw metadata attached to the school.
     *
     * @return array Arbitrary metadata key-value pairs.
     */
    public function metadata(): array;
}