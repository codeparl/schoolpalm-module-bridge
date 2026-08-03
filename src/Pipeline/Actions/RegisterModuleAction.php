<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use SchoolPalm\ModuleBridge\Context\TenantContext;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;

class RegisterModuleAction extends AbstractInstallerAction
{
    protected string $phase = 'installation';
    protected bool $reversible = true;

    public function key(): string
    {
        return 'register_module_for_execution';
    }

    public function execute(InstallContext $context): void
    {
        /** @var ModuleManifest $module */
        $module = $context->module;

        $this->log($context, "Registering module...");

        try {
            // Store in DB (central)
            TenantContext::storeModule($module->raw());

            // Register in runtime cache
            TenantContext::registerModuleToCache(
                $module->raw(),
                $context->moduleBackendPath
            );

            $this->success($context, "Module registered successfully.");

        } catch (\Throwable $e) {

            $this->fail(
                $context,
                "Module registration failed: " . $e->getMessage()
            );
        }
    }

    public function rollback(InstallContext $context): void
    {
        /** @var ModuleManifest $module */
        $module = $context->module;

        $this->log($context, "Rolling back module registration...");

        try {
            // Remove from cache
            TenantContext::unregisterModuleFromCache(
                $module->raw()['context'],
                strtolower($module->raw()['module_key'])
            );

            // Remove from DB
            TenantContext::removeStoredModule(
                strtolower($module->raw()['module_key'])
            );

            $this->log($context, "Module registration rollback completed.");

        } catch (\Throwable $e) {
            // rollback should NOT break pipeline
            $this->log(
                $context,
                "Module rollback failed: " . $e->getMessage()
            );
        }
    }
}