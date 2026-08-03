<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use Illuminate\Support\Facades\File;
use RuntimeException;
use SchoolPalm\ModuleBridge\Context\TenantContext;
use SchoolPalm\ModuleBridge\Database\RunMigration;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;

class RunModuleDBSeedersAction extends AbstractInstallerAction
{
      protected string $phase = 'installation';
    protected bool $reversible = true;

    public function key(): string
    {
        return 'run_module_DB_seeders';
    }

  public function execute(InstallContext $context): void
    {
        $module = $context->module;
        $migrationPath = $context->moduleBackendPath;

        $this->log($context, "Running module seeders for all tenants...");

        try {
            // No need to pass 'tenant' connection name; TenantContext handles the switch
            TenantContext::forEachTenant(function ($tenant) use ($migrationPath, $context) {
                // Inside this closure, we are already connected to the current $tenant's DB
                $this->log($context, "Seeding tenant ID: " . $tenant->getTenantKey());

               
                RunMigration::runSeeders($migrationPath,$context->module->info()->namespace());
            });

            $this->success($context, "Module seeders executed successfully for all tenants.");

        } catch (RuntimeException $e) {
            $this->fail($context, "Seeder execution failed: " . $e->getMessage());
        }
    }

     public function rollback(InstallContext $context): void
    {
        $module = $context->module;
        $migrationPath = $migrationPath = $context->moduleBackendPath;

        $this->log($context, "Rolling back module seeders for all tenants...");

        try {
            TenantContext::forEachTenant(function ($tenant) use ($migrationPath, $context) {
                $this->log($context, "Rolling back tenant ID: " . $tenant->getTenantKey());

                RunMigration::rollbackSeeders($migrationPath,$context->module->info()->namespace());
            });

            $this->log($context, "Module seeder rolled back successfully for all tenants.");

        } catch (RuntimeException $e) {
            $this->log($context, "Seeder rollback failed: " . $e->getMessage());
        }
    }
}
