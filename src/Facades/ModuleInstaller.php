<?php

namespace SchoolPalm\ModuleBridge\Facades;

use Illuminate\Support\Facades\Facade;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;
use SchoolPalm\ModuleBridge\Pipeline\ModuleInstaller as ModuleInstallerPipelineFacade;

/**
 * @method static ModuleInstallerPipelineFacade make(ModuleManifest|string|array $manifest)
 */
class ModuleInstaller extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'module.installer';
    }
}

