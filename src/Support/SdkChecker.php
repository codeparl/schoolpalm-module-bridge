<?php

namespace SchoolPalm\ModuleBridge\Support;

use Composer\InstalledVersions;

final class SdkChecker
{
    public static function isInstalled(string $name): bool
    {
        return true;
        //InstalledVersions::isInstalled($name);
    }

    public static function getVersion(string $name): ?string
    {
        return '4.0.0';
        //InstalledVersions::getVersion($name);
    }
}
