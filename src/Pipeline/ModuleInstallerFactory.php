<?php

namespace SchoolPalm\ModuleBridge\Pipeline;

use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;

/**
 * Factory entry-point for facade/container usage.
 */
final class ModuleInstallerFactory
{
    public function make(ModuleManifest|string|array $manifest): ModuleInstaller
    {
        return ModuleInstaller::make($manifest);
    }
}

