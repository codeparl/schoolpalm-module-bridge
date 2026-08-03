<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use Illuminate\Support\Facades\File;
use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;

class VerifyModuleStructureAction extends AbstractInstallerAction
{
    protected string $phase = 'verification';
    protected bool $reversible = false;

    public function key(): string
    {
        return 'verify_module_structure';
    }

    public function execute(InstallContext $context): void
    {
        $module = $context->module;

        $moduleRootPath = $context->transit_path.'/'. $module->root();

        $this->log($context, "Verifying module structure...");
        if (!File::exists($moduleRootPath)) {
            $this->fail($context, "Module root folder not found");
        }

        // Required structure
        $backendPath  = $moduleRootPath . '/Backend';
        $frontendPath = $moduleRootPath . '/Frontend';
        $manifestPath = $moduleRootPath . '/manifest.json';

        $missing = [];

        if (!File::exists($backendPath)) {
            $missing[] = 'Backend folder';
        }

        if (!File::exists($frontendPath)) {
            $missing[] = 'Frontend folder';
        }

        if (!File::exists($manifestPath)) {
            $missing[] = 'manifest.json';
        }

        // Optional: module entry integrity checks (safe file existence check only)
        $backendProvider = $backendPath . '/Providers/'. $module->entry->providerFile();
        $backendActionEntry = $backendPath .'/'. $module->moduleEntry();

        if (!File::exists($backendProvider)) {
            $missing[] = 'Backend Service Provider';
        }

        if (!File::exists($backendActionEntry)) {
            $missing[] = 'Backend Action Entry';
        }

        if (!empty($missing)) {
            $this->fail(
                $context,
                "Invalid module structure. Missing: " . implode(', ', $missing)
            );
        }

        $this->success($context, "Module structure verified successfully.");
    }

    public function rollback(InstallContext $context): void
    {
        // Verification has no rollback
    }
}