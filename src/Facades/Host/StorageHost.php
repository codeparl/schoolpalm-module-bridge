<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Facades\Host;

use Illuminate\Support\Facades\Facade;
use SchoolPalm\ModuleBridge\Adapters\StorageAdapter;


/**
 *  @method static \SchoolPalm\ModuleBridge\Adapters\StorageAdapter forContext(?string $tenantId, ?string $schoolId)
 * @method static string put(string $path, mixed $contents)
 * @method static string get(string $path)
 * @method static bool exists(string $path)
 * @method static bool delete(string $path)
 * @method static bool deleteDirectory(string $path)
 * @method static bool copy(string $from, string $to)
 * @method static bool move(string $from, string $to)
 * @method static ?string publicUrl(string $path)
 * @method static string resolvePath(string $path)
 */
final class StorageHost extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'module.storage';
    }
}
