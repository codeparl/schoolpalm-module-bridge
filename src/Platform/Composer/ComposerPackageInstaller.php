<?php

namespace SchoolPalm\ModuleBridge\Platform\Composer;

use Illuminate\Support\Facades\File;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Support\Helper;

class ComposerPackageInstaller
{
    public function __construct(
        private InstallContext $context,
        private AbstractInstallerAction $action
    ) {}

    /**
     * Install required composer packages
     */
    public function install(array &$packages): void
    {
        if (empty($packages)) {
            $this->action->log($this->context, "No composer packages to install.");
            return;
        }

        $this->action->log($this->context, 'Installing packages: ' . json_encode($packages));

        // ✅ STEP 1: capture existing state BEFORE install
        $baseline = $this->captureBaseline($packages);

        $installedByThisModule = [];

        foreach ($packages as $package => $version) {

            $installedPackage = $this->installPackage($package, $version);

            unset($packages[$installedPackage]);

            // if it was NOT in baseline → it belongs to this module
            if (!in_array($installedPackage, $baseline, true)) {
                $installedByThisModule[] = $installedPackage;
            }
        }

        $this->storeModuleInstalledPackages($installedByThisModule);
    }

    private function storeModuleInstalledPackages(array $packages): void
    {
        if (empty($packages)) {
            return;
        }
        $module  =  $this->context->module;
        $path  =  $this->context->transit_path
            . '/' . $module->root() . '/composer.installed_by_modul.json';

        File::ensureDirectoryExists(dirname($path), 0777);
        Helper::storeJson($path, $packages);
    }
    /**
     * Install a single package
     */
    private function installPackage(string $package, string $version): string
    {
        if ($this->isInstalled($package)) {
            $this->action->log($this->context, "Already installed: {$package}");
            return $package;
        }

        $this->action->log($this->context, "Installing composer package: {$package}:{$version}");

        $command = "composer require {$package}:{$version} --no-interaction --prefer-dist";

        if ($this->context->dryRun ?? false) {
            $this->action->log($this->context, "[DRY RUN] {$command}");
            return $package;
        }

        $output = $this->runCommand($command);


        if (!$output['success']) {
            $this->action->fail(
                $this->context,
                "Failed installing {$package}: " . $output['output']
            );
        }

        return $package;
    }

    private function captureBaseline(array $packages): array
    {
        $baseline = [];

        foreach ($packages as $package => $version) {
            if ($this->isInstalled($package)) {
                $baseline[] = $package;
            }
        }

        return $baseline;
    }

    /**
     * Check installed packages
     */
    private function isInstalled(string $package): bool
    {
        static $installed = null;

        if ($installed === null) {
            $path = base_path('composer.lock');

            if (!file_exists($path)) {
                return false;
            }

            $installed = Helper::loadJson($path);
        }

        foreach ($installed['packages'] ?? [] as $pkg) {
            if (($pkg['name'] ?? '') === $package) {
                return true;
            }
        }

        return false;
    }

    /**
     * Run system command
     */
    private function runCommand(string $command): array
    {
        $output = [];
        $code = 0;

        $originalDir = getcwd();
        $targetDir = base_path();

        // Change to project root
        chdir($targetDir);

        exec($command . ' 2>&1', $output, $code);

        // Restore original directory (VERY important)
        chdir($originalDir);

        return [
            'success' => $code === 0,
            'output' => implode("\n", $output),
        ];
    }


public function rollbackModulePackages(): void
{
    $module  = $this->context->module;

    $path = $this->context->transit_path
        . '/' . $module->root() . '/composer.installed_by_module.json';

    if (!File::exists($path)) {
        $this->action->log($this->context, "No module composer package record found.");
        return;
    }

    $packages = Helper::loadJson($path);

    if (empty($packages)) {
        $this->action->log($this->context, "No module composer packages to rollback.");
        return;
    }

    $this->action->log(
        $this->context,
        "Rolling back module composer packages: " . json_encode($packages)
    );

    foreach (array_reverse($packages) as $package) {

        $command = "composer remove {$package} --no-interaction";

        if ($this->context->dryRun ?? false) {
            $this->action->log($this->context, "[DRY RUN] {$command}");
            continue;
        }

        $output = $this->runCommand($command);

        if (!$output['success']) {
            // Do NOT fail hard during rollback
            $this->action->log(
                $this->context,
                "Failed removing {$package}: " . $output['output']
            );
            continue;
        }

        $this->action->log($this->context, "Removed module package: {$package}");
    }

    File::delete($path);
    $this->action->log($this->context, "Module composer rollback completed.");
}
}
