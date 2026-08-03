<?php

namespace SchoolPalm\ModuleBridge\Context;

use SchoolPalm\ModuleBridge\Facades\ModuleTransit;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;

/**
 * Class ModuleRuntimeContext
 *
 * Represents a lightweight, runtime-safe context for a module
 * AFTER it has been successfully installed.
 *
 *  Does NOT include:
 * - Installation pipeline state
 * - Transit paths
 * - Locking or temporary data
 *
 *  Includes only execution-ready data:
 * - Backend execution path
 * - Frontend execution path
 * - Cache path
 * - Module manifest (now resolved from execution directory)
 *
 * This context is intended for:
 * - Event dispatching (e.g. ModuleInstalled)
 * - Runtime module bootstrapping
 * - Integration with registry / loaders
 *
 * It acts as the bridge between:
 * Installation phase → Runtime phase
 */
class ModuleRuntimeContext
{
    /**
     * Absolute path to module backend execution directory.
     * Example: /modules/Blog/Backend/
     */
    public ?string $backendPath = null;

    /**
     * Absolute path to module frontend execution directory.
     * Example: /public/modules/blog/
     */
    public ?string $frontendPath = null;

    /**
     * Path to module cache storage (external cache or compiled assets).
     */
    public ?string $moduleCachePath =null;
    public ?string $snapshot_root =null;
    /**
     * Module manifest instance.
     *
     * NOTE:
     * At runtime, this should resolve from the EXECUTION directory,
     * not transit.
     */
    public ModuleManifest $manifest;
    public ?string $module_checksum = null;


    public  ?array $plans=[];
    /**
     * Constructor.
     *
     * @param string $backendPath
     * @param string $frontendPath
     * @param string $moduleCachePath
     * @param ModuleManifest $manifest
     */
    public function __construct(
        ModuleManifest $manifest,
        ?string $backendPath =null,
        ?string $frontendPath=null,
        ?string $moduleCachePath=null,
        ?array $plans =[],
        ?string $checksum = null
    ) {
        $this->manifest = $manifest;
        $this->plans  =  $plans;
        $this->snapshot_root = config('sdk.snapshot.path');
        $this->moduleCachePath = $moduleCachePath ? $moduleCachePath  :  config('sdk.module.cache.external');
        $this->backendPath = $backendPath ? $backendPath : config('sdk.module.exec_dir.backend');
        $this->backendPath .= '/' . $this->manifest->root() . '/Backend/';
        $this->frontendPath = $frontendPath ? $frontendPath : config('sdk.module.exec_dir.frontend');
        $this->module_checksum =  $checksum;

    }

    /**
     * Build runtime context from InstallContext.
     *
     * This method extracts ONLY the necessary runtime data
     * and discards all installation-related state.
     *
     * @param InstallContext $context
     * @return self
     */
    public static function fromInstallContext(InstallContext $context): self
    {
        return new self(
            manifest: new ModuleManifest($context->moduleBackendRootPath.'/manifest.json'),
            backendPath: $context->moduleBackendPath,
            frontendPath: $context->moduleFrontendExecutionPath,
            moduleCachePath: $context->moduleCachePath,
            plans: ModuleTransit::find($context->module->info()->namespace())['plans'] ??[]
        );
    }
}
