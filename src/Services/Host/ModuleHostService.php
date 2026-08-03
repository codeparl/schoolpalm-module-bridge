<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Services\Host;

use SchoolPalm\ModuleBridge\Contracts\Host\UserHost;
use SchoolPalm\ModuleBridge\Support\Helper;
use App\Models\User;
use SchoolPalm\ModuleBridge\Contracts\Host\ModuleHost;

/**
 * UserHostService
 *
 * Resolves the current user context for:
 * - SchoolPalm runtime (auth + tenancy)
 * - SDK runtime (fake JSON user data)
 */
class ModuleHostService implements ModuleHost
{


    public function name(): ?string
    {
        // check module name from request if available, otherwise fallback to user name
        //$module  =   Helper::getPathSegment('module');
        return 'module name';
    }

    public function moduleNamespace(): ?string
    {

        return 'module namespace ';
    }

    public function current(): ?object
    {
        // check module name from request if available, otherwise fallback to user name
        //$module  =   Helper::getPathSegment('module');
        return (object)[
            'name' => 'module name',
            'namespace' => 'module namespace',
        ];
    }
}
