<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;

class RegisterInAutoloadRegistryAction extends AbstractInstallerAction
{
    protected string $phase = 'Registration & Finalization';
    protected bool $reversible = true;

    public function execute(InstallContext $context): void
    {
        $moduleKey = $context->moduleKey;
        /** @var ModuleManifest $module */
        $module = $context->module;

        if (!$module) {
            throw new \RuntimeException("No module selected for autoload registry registration.");
        }

        $info = $module->info();

        // SDK host or dry-run may skip actual registration
        if ($this->dryRun) {
            if (!$this->silent) {
                echo "[DryRun] Would register module '{$moduleKey}' in autoload registry.\n";
            }
            return;
        }

        $folder = $info->folder ?? 'Default';
        $vendor = $info->vendor ?? 'unknown';
        $name   = $info->module ?? 'unnamed';

        $context->autoloadRegistry->register([
            'vendor'     => $vendor,
            'module'     => $name,
            'name'       => $name,
            'folder'     => $folder,
            'module_key' => $moduleKey,
            'type'       => $info->type ?? 'custom',
            'icon'       => $info->icon ?? null,
            'is_common'  => $module->isCommon(),
            'path'       => $module->path,
            'installed'  => true,
        ]);

        if (!$this->silent) {
            echo "[OK] Module '{$moduleKey}' registered in autoload registry.\n";
        }
    }

    public function rollback(InstallContext $context): void
    {
        $moduleKey = $context->moduleKey;

        if ($context->autoloadRegistry->exists($moduleKey)) {
            $context->autoloadRegistry->remove($moduleKey);

            if (!$this->silent) {
                echo "[Rollback] Module '{$moduleKey}' removed from autoload registry.\n";
            }
        }
    }
}
