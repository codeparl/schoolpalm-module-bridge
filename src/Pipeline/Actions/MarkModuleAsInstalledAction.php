<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;

class MarkModuleAsInstalledAction extends AbstractInstallerAction
{
    protected string $phase = 'Registration & Finalization';
    protected bool $reversible = true;

    public function execute(InstallContext $context): void
    {
        /** @var ModuleManifest $manifest */
        $manifest = $context->module;
        $moduleKey = $manifest->key();

        if ($context->createdRegistry->exists($moduleKey)) {
            $context->createdRegistry->install($moduleKey);

            if (!$this->silent) {
                echo "[OK] Module '{$moduleKey}' marked as installed in created registry.\n";
            }
        }
    }

    public function rollback(InstallContext $context): void
    {
        /** @var ModuleManifest $manifest */
        $manifest = $context->module;
        $moduleKey = $manifest->key();

        if ($context->createdRegistry->exists($moduleKey)) {
            $context->createdRegistry->uninstall($moduleKey);

            if (!$this->silent) {
                echo "[Rollback] Module '{$moduleKey}' marked as uninstalled.\n";
            }
        }
    }
}
