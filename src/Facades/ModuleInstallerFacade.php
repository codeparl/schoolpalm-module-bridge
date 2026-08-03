<?php

namespace SchoolPalm\ModuleBridge\Facades;

use Illuminate\Support\Facades\Facade;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;
use SchoolPalm\ModuleBridge\Pipeline\ModuleInstaller;

/**
 * @method static ModuleInstaller make(ModuleManifest|string|array $manifest)
 */
class ModuleInstallerFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'module.installer';
    }
}

