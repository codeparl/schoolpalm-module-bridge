<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use Illuminate\Support\Facades\File;
use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use Illuminate\Support\Facades\Process;
use SchoolPalm\ModuleBridge\Facades\ModuleTransit;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;
use SchoolPalm\ModuleBridge\Packaging\ModulePackager;

class InstallFrontendDependenciesAction extends AbstractInstallerAction
{
     protected string $phase = 'installation';
    protected bool $reversible = true;

    public function key(): string
    {
        return 'deploy_frontend_to_execution_directory';
    }

 

public function execute(InstallContext $context): void
{
    $module = $context->module;
    $transit = ModuleTransit::find($module->info()->namespace());
    $frontendBasePath = $context->moduleFrontendExecutionPath;
    if (!$transit) {
        $this->fail($context, "Missing module in transit");
    }

    if (!$frontendBasePath || !is_dir($frontendBasePath)) {
        $this->fail($context, "No frontend execution dir set.");
    
    }

    $this->log($context, "Deploying frontend assets...");

     ModulePackager::deployFrontend($transit, $frontendBasePath);
    $this->success($context, "Frontend deployed to execution directory");
}
    public function rollback(InstallContext $context): void
    {
          $path = $context->moduleFrontendExecutionPath
        .'/'.$context->module->root();

        if ($path && File::exists($path)) {
            File::deleteDirectory($path);
            $this->log($context, "Rolled back frontend");
        }
    }
}
