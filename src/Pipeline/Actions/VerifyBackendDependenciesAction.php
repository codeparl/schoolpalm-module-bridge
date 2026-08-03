<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use Composer\Semver\Semver;
use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use SchoolPalm\ModuleBridge\Platform\ModuleDependencyResolver;

class VerifyBackendDependenciesAction extends AbstractInstallerAction
{
    protected string $phase = 'verification';
    protected bool $reversible = false;

    public function key(): string
    {
        return 'verify_backend_dependencies';
    }

    public function execute(InstallContext $context): void
    {

    
        $depResolver = new ModuleDependencyResolver($context, $this);
        $depResolver->resolve();

        $this->success($context);
    }

    public function rollback(InstallContext $context): void
    {
        // No rollback needed for verification
    }

  

  
}
