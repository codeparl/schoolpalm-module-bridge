<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Facades\Host;

use Illuminate\Support\Facades\Facade;
use SchoolPalm\ModuleBridge\Contracts\Host\ContextHost;

/**
 * Class Host
 *
 * Facade for accessing the current runtime context:
 * tenant, school, and user.
 *
 * This provides a clean static API for modules:
 *
 * Host::context()->user();
 * Host::context()->school();
 * Host::context()->tenant();
 */
class Host extends Facade
{
    /**
     * Get the service container binding key
     */
    protected static function getFacadeAccessor(): string
    {
        return ContextHost::class;
    }
}