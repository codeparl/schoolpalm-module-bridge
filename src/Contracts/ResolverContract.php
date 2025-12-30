<?php

namespace SchoolPalm\ModuleBridge\Contracts;

/**
 * Interface ResolverContract
 *
 * Defines a **contract for resolving module classes, actions, and components**
 * within the SchoolPalm / Module Bridge system.
 *
 * Implementations of this interface provide a standardized way to locate:
 * - Module main classes
 * - Action directories and namespaces
 * - Frontend components (Inertia or other UI components)
 *
 * This ensures both **SchoolPalm core** and **module-sdk** can resolve modules
 * consistently without hardcoding paths or namespaces.
 *
 * @package SchoolPalm\ModuleBridge\Contracts
 */
interface ResolverContract
{
    /**
     * Resolve the main class of a module.
     *
     * @param string $module The module name or identifier.
     * @return string|null Fully-qualified class name of the module, or null if not found.
     */
    public function resolveModuleMainClass(string $module): ?string;

    /**
     * Resolve the directory path where module action classes are stored.
     *
     * @param string $module The module name or identifier.
     * @return string|null Path to the actions directory, or null if not found.
     */
    public function resolveActionPath(string $module): ?string;

    /**
     * Resolve the namespace for module action classes.
     *
     * @param string $module The module name or identifier.
     * @return string|null Fully-qualified namespace for module actions, or null if not found.
     */
    public function resolveActionNamespace(string $module): ?string;

    /**
     * Resolve the Inertia / UI component path for a specific action.
     *
     * @param string $module The module name or identifier.
     * @param string $action The action name within the module.
     * @return string Fully-qualified component path.
     */
    public function resolveComponent(string $module, string $action): string;

    /**
     * Resolve the base component path for a module.
     *
     * Useful for loading subcomponents or nested module views.
     *
     * @param string $module The module name or identifier.
     * @return string Base path for the module's components.
     */
    public function resolveModuleComponentBase(string $module): string;

    /**
 * Resolve the default dashboard component path.
 *
 * This is used when the framework or SDK needs a module-independent dashboard view.
 *
 * @return string Fully-qualified path to the dashboard component.
 */
public function resolveDashboard(): string;

}
