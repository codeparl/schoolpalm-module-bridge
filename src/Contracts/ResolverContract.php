<?php

namespace SchoolPalm\ModuleBridge\Contracts;

/**
 * Interface ResolverContract
 *
 * Defines a contract for resolving **backend module structures**
 * within the SchoolPalm / Module Bridge system.
 *
 * Implementations provide a standardized way to locate:
 * - Module main classes
 * - Action directories and namespaces
 * - Action class resolution
 * - Module filesystem paths
 *
 * This ensures both SchoolPalm core and Module SDK
 * can resolve modules consistently without hardcoding
 * paths or namespaces.
 *
 * NOTE:
 * This contract is **backend-only**.
 * Frontend/UI components are resolved by the module frontend itself.
 *
 * @package SchoolPalm\ModuleBridge\Contracts
 */
interface ResolverContract
{
    /**
     * Resolve the main class of a module.
     *
     * @return string|null Fully-qualified class name.
     */
    public function resolveModuleMainClass(): ?string;

    /**
     * Resolve the directory path where module action classes are stored.
     *
     * @param 
     * @return string|null
     */
    public function resolveActionPath(): ?string;

    /**
     * Resolve the namespace for module action classes.
     *
     * @param 
     * @return string|null
     */
    public function resolveActionNamespace(): ?string;

    /**
     * Resolve a specific action class.
     *
     * @param 
     * @param string $action
     * @return string|null Fully-qualified action class.
     */
    public function resolveActionClass(string $action): ?string;

    /**
     * Resolve the filesystem path of the module root.
     *
     * @param 
     * @return string|null
     */
    public function resolveModulePath(): ?string;

    /**
     * Resolve the base namespace of a module.
     *
     * @param 
     * @return string|null
     */
    public function resolveModuleNamespace(): ?string;
}
