<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;

class VerifyDependencyContractsAction extends AbstractInstallerAction
{
    protected string $phase = 'verification';
    protected bool $reversible = false;

    public function key(): string
    {
        return 'verify_dependency_contracts';
    }
    public function execute(InstallContext $context): void
    {
        /** @var ModuleManifest $module */
        $module = $context->module;

        // Extract required modules from manifest
        $requiredModules = $module->requires->modules() ?? [];

        if(empty($requiredModules)){
            $this->success($context);
        }

       
    }

    public function rollback(InstallContext $context): void
    {
        // Clear stored dependency contracts on rollback
        $context->module->dependencyContracts = [];

        if (!$this->silent) {
            echo "[Rollback] Cleared dependency contracts from context.\n";
        }
    }
}
