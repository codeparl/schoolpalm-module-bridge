<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use Illuminate\Support\Facades\File;
use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use SchoolPalm\ModuleBridge\Context\ModuleRuntimeContext;
use SchoolPalm\ModuleBridge\Events\ModuleCleanupCompletedEvent;
use SchoolPalm\ModuleBridge\Facades\ModuleTransit;

class CleanupTransitModuleAction extends AbstractInstallerAction
{
    protected string $phase = 'finalization';
    protected bool $reversible = false;

    public function key(): string
    {
        return 'cleanup_transit_module';
    }

  public function execute(InstallContext $context): void
{
    $this->log($context, "Cleaning up transit module...");

    try {
        $transitPath = $context->transit_path;
        $modulePath = $transitPath . '/' . $context->module->root();

        $fileDeleted = false;
        $registryRemoved = false;

        // 1. Remove filesystem module
        if (File::exists($modulePath)) {
            $fileDeleted = File::deleteDirectory($modulePath);
        }

        // 2. Remove from transit registry
        $namespace = $context->module->info()->namespace();

        if ($fileDeleted) {
            ModuleTransit::remove($namespace);
        }

        $registryRemoved = ModuleTransit::find($namespace) === null;

        // 3. Final runtime context
        $runtime = ModuleRuntimeContext::fromInstallContext($context);

        // 4. Final computed state
        $fullyCleaned = $fileDeleted && $registryRemoved;

        event(new ModuleCleanupCompletedEvent(
            context: $runtime,
            moduleRemoved: $fullyCleaned,
            transitExists: File::exists($transitPath),
            modulePath: $modulePath,
            message: $fullyCleaned
                ? "Module fully cleaned from transit"
                : "Module partially cleaned (check registry or filesystem)"
        ));

        $this->success($context, "Transit module cleanup completed.");

    } catch (\Throwable $e) {
        $this->log(
            $context,
            "Transit cleanup failed: " . $e->getMessage()
        );
    }
}

    public function rollback(InstallContext $context): void
    {
        // Cleanup is irreversible
    }
}