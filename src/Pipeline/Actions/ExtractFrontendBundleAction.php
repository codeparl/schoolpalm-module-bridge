<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use Illuminate\Support\Facades\File;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;

class ExtractFrontendBundleAction extends AbstractInstallerAction
{
    protected string $phase = 'Integration & Placement';
    protected bool $reversible = true;

    protected string $sourcePath = '';
    protected string $destinationPath = '';

    public function canExecute(InstallContext $context): bool
    {
        /** @var ModuleManifest $manifest */
        $manifest = $context->module;
        return !empty($manifest->resources->ui->source_path ?? null);
    }

    public function execute(InstallContext $context): void
    {
        /** @var ModuleManifest $manifest */
        $manifest = $context->module;

        $ui = $manifest->resources->ui ?? null;

        if (!$ui || empty($ui->source_path)) {
            return;
        }

        $relativeSource = $ui->source_path;
        $modulePath = $context->moduleExecutionPath;
        $sourcePath = $modulePath . DIRECTORY_SEPARATOR . $relativeSource;
        $destinationPath = $context->frontendExecutionPath;

        if (!is_dir($sourcePath)) {
            throw new \RuntimeException(
                "Frontend bundle source not found: {$sourcePath}"
            );
        }

        if (is_dir($destinationPath) && !$context->overwrite) {
            throw new \RuntimeException(
                "Frontend execution directory already exists: {$destinationPath}"
            );
        }

        if ($this->dryRun) {
            if (!$this->silent) {
                echo "[DryRun] Would extract frontend bundle from {$sourcePath} to {$destinationPath}\n";
            }
            return;
        }

        // Clean destination if overwrite
        if (is_dir($destinationPath)) {
            File::deleteDirectory($destinationPath);
        }

        File::ensureDirectoryExists(dirname($destinationPath));
        File::copyDirectory($sourcePath, $destinationPath);

        $this->sourcePath = $sourcePath;
        $this->destinationPath = $destinationPath;

        if (!$this->silent) {
            echo "[OK] Frontend bundle extracted.\n";
        }
    }

    public function rollback(InstallContext $context): void
    {
        if (!$this->destinationPath || !is_dir($this->destinationPath)) {
            return;
        }

        if ($this->dryRun) {
            if (!$this->silent) {
                echo "[DryRun] Would rollback frontend extraction.\n";
            }
            return;
        }

        File::deleteDirectory($this->destinationPath);

        if (!$this->silent) {
            echo "[Rollback] Frontend bundle removed.\n";
        }
    }
}
