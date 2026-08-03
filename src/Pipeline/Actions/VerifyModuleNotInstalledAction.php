<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;

class VerifyModuleNotInstalledAction extends AbstractInstallerAction
{
    protected string $phase = 'verification';
    protected bool $reversible = false;

    public function key(): string
    {
        return 'verify_not_installed';
    }

    public function execute(InstallContext $context): void
    {
        // ------------------------------------
        // 1. Check file exists in filesystem
        // ------------------------------------
        $path = $context->module->modulePath();

        // ------------------------------------
        // 2. Check module exists in database
        // ------------------------------------
        $moduleKey = $context->module->key() ?? null;

        if ($context->moduleExistsInDb()) {
            $this->fail($context, "Module '{$moduleKey}' already exists in the system.");
            return;
        }

        // ------------------------------------
        // 2. Check module does not exists in execution path
        // ------------------------------------
        if (file_exists($context->module->moduleExecutionPath())) {
            $this->fail($context, "Module '{$moduleKey}' already installed.");
            return;
        }

        $this->success($context);
    }
}
