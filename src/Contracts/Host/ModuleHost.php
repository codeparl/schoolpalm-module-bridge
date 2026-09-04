<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Contracts\Host;

use SchoolPalm\ModuleBridge\Support\ContextData;

/**
 * Interface ModuleHost
 *
 * Provides the current module context to modules in a framework-agnostic way.
 *
 * In SchoolPalm runtime, this resolves from active route/request segments.
 * In SDK runtime, this resolves from test mocks or module metadata.
 */
interface ModuleHost
{
    /**
     * Get the name of the current module context.
     */
    public function name(): ?string;

    /**
     * Get the dot-notation key / slug of the current module context.
     */
    public function moduleKey(): ?string;

    /**
     * Get the root namespace of the current module context.
     */
    public function moduleNamespace(): ?string;

    /**
     * Resolve and return current module context array.
     */
    public function currentArray(): ?array;

    /**
     * Resolve and return current module context.
     * Pass $asArray = true for background queues and view context data.
     */
    public function current(bool $asArray = false): null|array|ContextData;
}
