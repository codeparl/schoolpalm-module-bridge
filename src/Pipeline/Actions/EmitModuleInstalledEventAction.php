<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use SchoolPalm\ModuleBridge\Context\ModuleRuntimeContext;
use SchoolPalm\ModuleBridge\Events\ModuleInstalled;

class EmitModuleInstalledEventAction extends AbstractInstallerAction
{
    protected string $phase = 'finalization';
    protected bool $reversible = false;

    public function key(): string
    {
        return 'emit_module_installation_event';
    }

    public function execute(InstallContext $context): void
{
    $this->log($context, "Emitting module installed event...");

    try {
       $runtime = ModuleRuntimeContext::fromInstallContext($context);
        event(new ModuleInstalled($runtime));
        $this->success($context, "ModuleInstalled event dispatched.");
    } catch (\Throwable $e) {
        $this->fail(
            $context,
            "Failed to emit event: " . $e->getMessage()
        );
    }
}

    public function rollback(InstallContext $context): void
    {
        // No rollback needed
    }
}