<?php

namespace SchoolPalm\ModuleBridge\Contracts;

/**
 * Interface ModuleContract
 *
 * Defines the **contract for all modules** in the SchoolPalm / Module Bridge system.
 *
 * Any concrete module implementation (in SchoolPalm core or module-sdk) must implement this interface.
 * This ensures a consistent module lifecycle and UI resolution strategy.
 *
 * ─────────────────────────────────────────────────────────────
 * PURPOSE
 * ─────────────────────────────────────────────────────────────
 * - Standardizes module execution across different contexts (core app vs. SDK)
 * - Provides hooks for action execution and UI / component resolution
 * - Allows the framework to dynamically interact with modules without knowing their internal details
 *
 * @package SchoolPalm\ModuleBridge\Contracts
 */
interface ModuleContract
{
    /**
     * Execute the resolved action for this module.
     *
     * Modules are expected to implement their business logic here,
     * based on the current context (portal, action, ID, etc.).
     *
     * @return mixed
     */
    public function performAction();

    /**
     * Resolve the Inertia / UI component path for the module.
     *
     * This method should return the fully-qualified path to the module's
     * front-end component so the framework or SDK can render it.
     *
     * @return string
     */
    public function componentPath(): string;

    /**
     * Resolve a module-relative component path.
     *
     * Useful for loading subcomponents or nested views inside a module.
     *
     * @param string $path Optional subpath relative to the module's base component directory.
     * @return string
     */
    public function moduleComponentPath(string $path = ''): string;

    /**
     * Optional referer component path.
     *
     * Returns the component path to redirect or refer to, if applicable.
     * Default implementation can return an empty string if not needed.
     *
     * @return string
     */
    public function refererComponent(): string;
}
