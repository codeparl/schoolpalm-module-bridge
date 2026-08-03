<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use Composer\Semver\Semver;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use RuntimeException;
use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Platform\SdkCompatibility;
use SchoolPalm\ModuleBridge\Support\SdkChecker;

final class VerifySdkCompatibilityAction extends AbstractInstallerAction
{
    protected string $phase = 'verification';
    protected bool $reversible = false;

    public function key(): string
    {
        return 'verify_sdk_compatibility';
    }
    public function execute(InstallContext $context): void
    {

        $version = $context->module->sdk->version();


        if (!$version) {
            $this->fail($context, 'SDK version not defined in manifest');
        }

        if (!SdkCompatibility::isCompatible($version)) {
            $this->fail(
                $context,
                "SDK version {$version} is NOT compatible with current system"
            );

      
        }

              $this->success($context, "SDK version {$version} is compatible");
    }
}
