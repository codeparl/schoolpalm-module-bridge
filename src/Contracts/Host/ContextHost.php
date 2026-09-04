<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Contracts\Host;

use SchoolPalm\ModuleBridge\Support\ContextData;

interface ContextHost
{
    /**
     * Get current tenant
     */
    public function tenant(bool $asArray = false): null|array|ContextData;

    /**
     * Get current school
     */
    public function school(bool $asArray = false): null|array|ContextData;

    /**
     * Get current user
     */
    public function user(bool $asArray = false): null|array|ContextData;

    /**
     * Get current module
     */
    public function module(bool $asArray = false): null|array|ContextData;

    /**
     * Export full context as plain array
     */
    public function toArray(): array;
}
