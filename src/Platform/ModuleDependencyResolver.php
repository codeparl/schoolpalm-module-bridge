<?php

namespace SchoolPalm\ModuleBridge\Platform;

use SchoolPalm\ModuleBridge\Context\InstallContext;
use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Platform\Composer\ComposerPackageInstaller;
use SchoolPalm\ModuleBridge\Support\Helper;

class ModuleDependencyResolver
{
    public function __construct(
        private InstallContext $context,
        private AbstractInstallerAction $action
    ) {}

    /**
     * MAIN ENTRY
     */
    public function resolve(): void
    {
        $this->checkPhpVersion();
       $this->checkExtensions();
        $this->checkPackages(false);
    }

    /**
     * -------------------------------------------------
     * PHP VERSION CHECK (STRICT)
     * -------------------------------------------------
     */
    public function checkPhpVersion(): void
    {
        $required = $this->context->module->dependencies->php();
        if (!$required) {
            return;
        }

        $system = PHP_VERSION;

        if (!$this->versionSatisfies($system, $required)) {
            $this->action->fail(
                $this->context,
                "PHP version mismatch. Required: {$required}, Current: {$system}"
            );
        }
    }

    /**
     * Simple version constraint check (safe fallback)
     */
    public function versionSatisfies(string $current, string $constraint): bool
    {
        // fallback to composer semver-style handling
        // you can replace later with Composer\Semver if needed

        if (str_starts_with($constraint, '^')) {
            $min = substr($constraint, 1);
            return version_compare($current, $min, '>=');
        }

        if (str_starts_with($constraint, '>=')) {
            return version_compare($current, substr($constraint, 2), '>=');
        }

        return $current === $constraint;
    }

    /**
     * -------------------------------------------------
     * EXTENSIONS CHECK
     * -------------------------------------------------
     */
    public function checkExtensions(): void
    {
        $extensions = $this->context->module->dependencies->extensions();

        if (empty($extensions)) {
            return;
        }

        $missing = [];

        foreach ($extensions as $ext => $constraint) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        if (!empty($missing)) {
            $this->action->fail(
                $this->context,
                "Missing PHP extensions: " . implode(', ', $missing)
            );
        }
    }

    /**
     * -------------------------------------------------
     * COMPOSER PACKAGES
     * -------------------------------------------------
     */
    public function checkPackages(bool $install=true): bool
    {
        $packages = $this->context->module->dependencies->packages() ;

        if (empty($packages)) {
            return true;
        }

        $missing = [];

        foreach ($packages as $package => $version) {
            if (!$this->isPackageInstalled($package)) {
                $missing[$package] = $version;
            }
        }

        if (!empty($missing)  && $install) {
            $installer = new ComposerPackageInstaller($this->context, $this->action);
            $installer->install($missing);
        }

        return empty($missing);
    }

    /**
     * -------------------------------------------------
     * PACKAGE DETECTION
     * -------------------------------------------------
     */
    private function isPackageInstalled(string $package): bool
    {
        static $installed = null;

        if ($installed === null) {
            $path = base_path('vendor/composer/installed.json');

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

    public function getMissingPackages(): array
{
    $packages = $this->context->module->dependencies->packages();

    if (empty($packages)) {
        return [];
    }

    $missing = [];

    foreach ($packages as $package => $version) {
        if (!$this->isPackageInstalled($package)) {
            $missing[$package] = $version;
        }
    }

    return $missing;
}
}