<?php

namespace SchoolPalm\ModuleBridge\Facades;

use Illuminate\Support\Facades\Facade;

class ModuleAutoload extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'module.autoload';
    }
}