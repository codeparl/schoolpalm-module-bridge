<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use Composer\Semver\Semver;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;

class VerifyDependentModulesAction extends AbstractInstallerAction
{
    protected string $phase = 'Preflight & Verification';

    public function execute(InstallContext $context): void
    {
        /** @var ModuleManifest $module */
        $module = $context->module;

        // Extract module dependencies from manifest
        $dependencies = $module->requires->modules() ?? [];

        foreach ($dependencies as $depKey => $versionConstraint) {
            // 1️⃣ Check if dependency exists in autoload registry
            if (!$context->autoloadRegistry->exists($depKey)) {
                throw new \RuntimeException(
                    "Dependent module '{$depKey}' is missing in autoload registry."
                );
            }

            $depModule = $context->autoloadRegistry->get($depKey);
            $depVersion = $depModule['version'] ?? '0.0.0';

            // 2️⃣ Validate version using composer/semver
            if (!empty($versionConstraint) && !Semver::satisfies($depVersion, $versionConstraint)) {
                throw new \RuntimeException(
                    "Dependent module '{$depKey}' version '{$depVersion}' does not satisfy required '{$versionConstraint}'."
                );
            }
        }

        if (!$this->silent) {
            echo "[OK] All dependent modules are installed and meet version constraints.\n";
        }
    }

    public function rollback(InstallContext $context): void
    {
        // No rollback needed for dependency verification
        if (!$this->silent) {
            echo "[Rollback] No rollback implemented for dependent module verification.\n";
        }
    }
}
