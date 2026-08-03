<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Facades\Host;

use Illuminate\Support\Facades\Facade;
use SchoolPalm\ModuleBridge\Adapters\CacheAdapter;

/**
 * @method static CacheAdapter driver(?string $driver)
 * @method static CacheAdapter store(?string $store)
 * @method static CacheAdapter tags(array|string $tags)
 * @method static CacheAdapter forSchool(?string $schoolId = null)
 * @method static CacheAdapter forTenant(?string $tenantId = null)
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool put(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null)
 * @method static bool forever(string $key, mixed $value)
 * @method static bool add(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null)
 * @method static bool forget(string $key)
 * @method static bool flush()
 * @method static mixed remember(string $key, \DateTimeInterface|\DateInterval|int|null $ttl, \Closure $callback)
 * @method static mixed rememberForever(string $key, \Closure $callback)
 * @method static int increment(string $key, int $value = 1)
 * @method static int decrement(string $key, int $value = 1)
 * @method static array many(array $keys)
 * @method static bool putMany(array $values, \DateTimeInterface|\DateInterval|int|null $ttl = null)
 * @method static mixed pull(string $key, mixed $default = null)
 * @method static \Illuminate\Cache\CacheLock lock(string $key, int $seconds)
 *
 * @see \SchoolPalm\ModuleBridge\Adapters\CacheAdapter
 */
class CacheHost extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CacheAdapter::class;
    }
}
