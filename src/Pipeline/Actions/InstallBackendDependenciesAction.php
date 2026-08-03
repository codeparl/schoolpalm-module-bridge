<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use Illuminate\Support\Facades\Process;
use Composer\Semver\Semver;
use Illuminate\Foundation\Application;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;
use SchoolPalm\ModuleBridge\Platform\Composer\ComposerPackageInstaller;
use SchoolPalm\ModuleBridge\Platform\ModuleDependencyResolver;

class InstallBackendDependenciesAction extends AbstractInstallerAction
{
    protected string $phase = 'installation';
    protected bool $reversible = true;

    public function key(): string
    {
        return 'install_backend_dependencies';
    }

    public function execute(InstallContext $context): void
    {
        /** @var ModuleManifest $manifest */
       $depResolver = new ModuleDependencyResolver($context, $this);
       $done  = $depResolver->checkPackages();
       if($done)
        $this->success($context, 'All composer packages have been installed successfully');

    }

 

    public function rollback(InstallContext $context): void
    {
       $installer = new ComposerPackageInstaller($context, $this);
       $installer->rollbackModulePackages();
            
    }
}
