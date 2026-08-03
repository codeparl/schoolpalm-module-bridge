<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use RuntimeException;
use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use SchoolPalm\ModuleBridge\Context\TenantContext;
use SchoolPalm\ModuleBridge\Database\RunMigration;

class RunModuleMigrationsAction extends AbstractInstallerAction
{
    protected string $phase = 'installation';
    protected bool $reversible = false;

    public function key(): string
    {
        return 'run_migrations';
    }

    public function execute(InstallContext $context): void
    {
        $module = $context->module;
        $migrationPath = $context->moduleBackendPath . $module->migrations->path();

        $this->log($context, "Running module migrations for all tenants...");

        try {
            // No need to pass 'tenant' connection name; TenantContext handles the switch
            TenantContext::forEachTenant(function ($tenant) use ($migrationPath, $context) {
                // Inside this closure, we are already connected to the current $tenant's DB
                $this->log($context, "Migrating tenant ID: " . $tenant->getTenantKey());

                RunMigration::execute($migrationPath);
            });

            $this->success($context, "Module migrations executed successfully for all tenants.");

        } catch (RuntimeException $e) {
            $this->fail($context, "Migration execution failed: " . $e->getMessage());
        }
    }

    public function rollback(InstallContext $context): void
    {
        $module = $context->module;
        $migrationPath = $context->moduleBackendPath . $module->migrations->path();

        $this->log($context, "Rolling back module migrations for all tenants...");

        try {
            TenantContext::forEachTenant(function ($tenant) use ($migrationPath, $context) {
                $this->log($context, "Rolling back tenant ID: " . $tenant->getTenantKey());

                RunMigration::rollbackAll($migrationPath);
            });

            $this->log($context, "Module migrations rolled back successfully for all tenants.");

        } catch (RuntimeException $e) {
            $this->log($context, "Migration rollback failed: " . $e->getMessage());
        }
    }
}
