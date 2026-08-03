<?php

namespace SchoolPalm\ModuleBridge\Facades;

use Illuminate\Support\Facades\Facade;
use SchoolPalm\ModuleBridge\Context\InstallContext;

/**
 * @method static string getHost()
 * @method static string getModuleKey()
 * @method static bool hasErrors()
 * @method static array errors()
 * @method static void addError(string $message)
 *
 * @see InstallContext
 */
class InstallContextFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'install.context';
    }
}
