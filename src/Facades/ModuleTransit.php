<?php

namespace SchoolPalm\ModuleBridge\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array read()
 * @method static void add(array $moduleData)
 * @method static void update(string $namespace, array $updateData)
 * @method static void remove(string $namespace)
 * @method static array|null find(string $namespace)
 *
 * @see \SchoolPalm\ModuleBridge\Packaging\ModuleTransit
 */
class ModuleTransit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'module-transit';
    }
}