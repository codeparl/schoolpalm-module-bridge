<?php

namespace SchoolPalm\ModuleBridge\Contracts;

/**
 * Contract for a persistent module registry.
 *
 * The registry is responsible for:
 * - Tracking all discovered / registered modules
 * - Persisting module metadata to storage
 * - Managing install state (installed / not installed)
 * - Providing lookup and filtering capabilities
 *
 * Implementations MUST treat the registry as a keyed map:
 *   [module_key => module_data]
 *
 * Where `module_key` is a canonical identifier
 * (e.g. "vendora.students").
 */
interface ModuleRegistryContract
{
    /**
     * Load the registry data from persistent storage into memory.
     *
     * Implementations should:
     * - Read from the configured registry storage (file, DB, etc.)
     * - Normalize module entries if needed
     * - Initialize an empty registry if storage does not exist
     *
     * This method SHOULD be idempotent.
     */
    public function load(): void;

    /**
     * Persist the current in-memory registry state to storage.
     *
     * Implementations should:
     * - Write to the configured registry path
     * - Ensure atomic and consistent writes where possible
     * - Clear any cached registry data after saving
     */
    public function save(): void;

    /**
     * Get all registered modules.
     *
     * @return array<string, array> An associative array keyed by module_key.
     */
    public function all(): array;

    /**
     * Retrieve a single module entry by its module key.
     *
     * @param string $moduleKey Canonical module identifier.
     * @return array|null The module data if found, otherwise null.
     */
    public function get(string $moduleKey): ?array;

    /**
     * Register a module in the registry.
     *
     * Implementations should:
     * - Normalize module metadata (vendor, module, role, etc.)
     * - Prevent duplicate registrations for the same module_key
     * - Persist the updated registry state
     *
     * @param array $module Raw module metadata.
     */
    public function register(array $module): void;

    /**
     * Remove a module from the registry.
     *
     * Implementations should:
     * - Remove the module entry completely
     * - Persist the updated registry state
     *
     * @param string $moduleKey Canonical module identifier.
     */
    public function remove(string $moduleKey): void;

    /**
     * Check whether a module exists in the registry.
     *
     * @param string $moduleKey Canonical module identifier.
     * @return bool True if the module is registered, false otherwise.
     */
    public function exists(string $moduleKey): bool;

    /**
     * Mark a module as installed.
     *
     * Implementations should:
     * - Set the module's installed flag to true
     * - Persist the updated registry state
     *
     * @param string $moduleKey Canonical module identifier.
     */
    public function install(string $moduleKey): void;

    /**
     * Mark a module as not installed.
     *
     * Implementations should:
     * - Set the module's installed flag to false
     * - Persist the updated registry state
     *
     * @param string $moduleKey Canonical module identifier.
     */
    public function uninstall(string $moduleKey): void;

    /**
     * Filter modules by installation status.
     *
     * Supported statuses:
     * - "installed"       → only installed modules
     * - "not_installed"   → only uninstalled modules
     * - "all"             → all modules
     *
     * @param string $status Filter status.
     * @return array<int, array> A list of matching module entries.
     */
    public function filterByStatus(string $status): array;

    /**
     * Determine whether a module is currently installed.
     *
     * @param string $moduleKey Canonical module identifier.
     * @return bool True if installed, false otherwise.
     */
    public function isInstalled(string $moduleKey): bool;

    /**
     * Get the total number of registered modules.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Remove all modules from the registry.
     *
     * Implementations should:
     * - Clear the in-memory registry
     * - Persist the cleared state
     */
    public function clear(): void;

    /**
     * Get all modules as a numeric list.
     *
     * Useful for iterating or returning modules as a simple list.
     *
     * @return array<int, array>
     */
    public function list(): array;

    /**
     * Reload the registry from persistent storage.
     *
     * Useful when the registry might have changed outside
     * of the current process.
     */
    public function refresh(): void;

    /**
     * Update an existing module entry in the registry.
     *
     * Implementations should:
     * - Merge or replace attributes for the given module_key
     * - Persist the updated registry state
     *
     * @param string $moduleKey Canonical module identifier.
     * @param array  $attributes Attributes to update.
     */
    public function update(string $moduleKey, array $attributes): void;


    /**
     * load manifest file for this module
     *
     * @param string $moduleKey Canonical module identifier.
     */
    public function loadManifest(string $moduleKey):array;

      /**
 * Read module setup pipeline by module key.
 *
 * Loads the pipeline JSON from the module path.
 *
 * @param string $moduleKey Canonical module identifier.
 * @return array Pipeline steps as an array
 */
    public function getSetupPipeline(string $moduleKey):array;
}
