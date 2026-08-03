<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;

class PrepareAutoloadRegistryAction extends AbstractInstallerAction
{
    protected string $phase = 'Host Preparation';

    public function execute(InstallContext $context): void
    {
        // Ensure autoload registry is initialized
        if (!isset($context->autoloadRegistry)) {
            throw new \RuntimeException("Autoload registry is not set in context.");
        }

        if (!$this->silent) {
            echo "[OK] Autoload registry is ready.\n";
        }
    }
}
