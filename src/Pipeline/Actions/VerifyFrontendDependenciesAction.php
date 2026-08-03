<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use Illuminate\Support\Facades\File;

class VerifyFrontendDependenciesAction extends AbstractInstallerAction
{
    protected string $phase = 'verification';
    protected bool $reversible = false;

    public function key(): string
    {
        return 'verify_frontend_resources';
    }

    public function execute(InstallContext $context): void
    {
        $module = $context->module;

        // Validate frontend config existence
        if (empty($module->raw()['frontend'])) {
            $this->log($context, "No frontend configuration found. Skipping frontend verification.");
            return;
        }

        $basePath = $context->transit_path . '/' . $module->root() . '/Frontend/dist/';

        $this->log($context, "Verifying frontend assets in: {$basePath}");

        // Resolve expected files
        $jsFile = $module->frontend->mainJs_path();
        $cssFile = $module->frontend->mainCss_path();

        if (empty($jsFile) && empty($cssFile)) {
            $this->log($context, "No JS or CSS entry points defined. Skipping verification.");
            return;
        }

        $jsMainPath  = $jsFile ? $basePath . basename($jsFile) : null;
        $cssMainPath = $cssFile ? $basePath . basename($cssFile) : null;

        // Track issues
        $missing = [];

        // Check JS
        if ($jsMainPath) {
            if (!File::exists($jsMainPath)) {
                $missing[] = "JS file missing: {$jsMainPath}";
            } else {
                $this->log($context, "✔ JS file found: {$jsMainPath}");
            }
        }

        // Check CSS
        if ($cssMainPath) {
            if (!File::exists($cssMainPath)) {
                $missing[] = "CSS file missing: {$cssMainPath}";
            } else {
                $this->log($context, "✔ CSS file found: {$cssMainPath}");
            }
        }

        // Final decision
        if (!empty($missing)) {
            $this->fail(
                $context,
                "Frontend asset verification failed:\n" . implode("\n", $missing)
            );
        }

        $this->success($context, "Frontend assets verified successfully.");
    }

    public function rollback(InstallContext $context): void
    {
        // No rollback needed for verification
    }
}