<?php

namespace SchoolPalm\ModuleBridge\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array all()
 * @method static array getContext(string $context)
 * @method static array|null get(string $context, string $module)
 * @method static void set(string $context, string $module, array $data)
 * @method static void remove(string $context, string $module)
 * @method static bool has(string $context, string $module)
 * @method static void save()
 *
 * @see \App\Services\ModuleRegistry
 */
class ModuleRegistry extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'module.registry';
    }
}
