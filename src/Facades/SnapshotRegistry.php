<?php

namespace SchoolPalm\ModuleBridge\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array all()
 * @method static array|null get(string $moduleKey, string $version)
 * @method static void add(string $moduleKey, string $version, array $snapshot)
 * @method static void remove(string $moduleKey, string $version)
 * @method static void make(string $path)
 *
 * @see \SchoolPalm\ModuleBridge\Snapshot\SnapshotRegistry
 */
class SnapshotRegistry extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \SchoolPalm\ModuleBridge\Snapshot\SnapshotRegistry::class;
    }
}