<?php

namespace SchoolPalm\ModuleBridge\Core;

/**
 * Class Module
 *
 * IDE-facing base class for all modules.
 *
 * IMPORTANT:
 * - This class contains NO implementation
 * - Concrete behavior is provided by the host runtime
 *   (SchoolPalm Core or Module SDK) via Bridge::bind()
 *
 * Purpose:
 * - Gives modules a stable class to extend
 * - Keeps modules runtime-agnostic
 * - Satisfies static analysis tools (IDE, PHPStan, Psalm)
 */
abstract class Module extends AbstractModule
{
    /**
     * Execute the resolved module action.
     *
     * Implemented by the runtime BaseModule.
     */
    abstract public function performAction();

    /**
     * Resolve the Inertia / UI component path.
     */
    abstract public function componentPath(): string;

    /**
     * Resolve a module-relative component path.
     */
    abstract public function moduleComponentPath(string $path = ''): string;

    /**
     * Optional referer component path.
     */
    abstract public function refererComponent(): string;
}
