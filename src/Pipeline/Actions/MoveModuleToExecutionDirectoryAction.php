<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use SchoolPalm\ModuleBridge\Packaging\ModulePackager;
use Illuminate\Support\Facades\File;
use SchoolPalm\ModuleBridge\Facades\ModuleTransit;

class MoveModuleToExecutionDirectoryAction extends AbstractInstallerAction
{
    protected string $phase = 'installation';
    protected bool $reversible = true;

    public function key(): string
    {
        return 'deploy_backend_to_execution_directory';
    }

    public function execute(InstallContext $context): void
    {
        $module = $context->module;
        $transit =  ModuleTransit::find($module->info()->namespace());
        $backendBasePath = $context->moduleBackendExecutionPath;

     
        if (!$transit) {
            $this->fail($context, "Missing module in transit");
        }

        $this->log($context, "Deploying backend...");

        ModulePackager::deployBackend($transit, $backendBasePath);
        $this->success($context, "Backend deployed to execution directory");
    }

    public function rollback(InstallContext $context): void
    {
        $path = $context->moduleBackendExecutionPath
        .'/'.$context->module->root();

        if ($path && File::exists($path)) {
            File::deleteDirectory($path);
            $this->log($context, "Rolled back backend");
        }
    }
}