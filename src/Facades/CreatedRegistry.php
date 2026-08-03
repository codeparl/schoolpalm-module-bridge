<?php

namespace SchoolPalm\ModuleBridge\Facades;

use Illuminate\Support\Facades\Facade;
use SchoolPalm\ModuleBridge\Contracts\ModuleRegistryContract;

/**
 * Facade for the active Module Registry implementation.
 *
 * The underlying registry may be:
 * - Autoload-based (filesystem discovery, always installed)
 * - Database-based (stateful install/uninstall lifecycle)
 *
 * Method semantics may vary slightly by registry type,
 * but the contract remains stable.
 *
 * @method static void load()
 * @method static void save()
 *
 * @method static array all()
 * @method static array list()
 *
 * @method static array|null get(
 *     string $moduleKey,
 *     string|null $group = 'common',
 *     string|null $vendor = null
 * )
 *
 * @method static bool exists(
 *     string $moduleKey,
 *     string|null $group = 'common',
 *     string|null $vendor = null
 * )
 *
 * @method static void register(
 *     array $module,
 *     string|null $group = 'common',
 *     string|null $vendor = null
 * )
 *
 * @method static void update(
 *     string $moduleKey,
 *     array $attributes,
 *     string|null $group = 'common',
 *     string|null $vendor = null
 * )
 *
 * @method static void remove(
 *     string $moduleKey,
 *     string|null $group = 'common',
 *     string|null $vendor = null
 * )
 *
 * @method static void install(string $moduleKey)
 * @method static void uninstall(string $moduleKey)
 *
 * @method static bool isInstalled(
 *     string $moduleKey,
 *     string|null $group = 'common',
 *     string|null $vendor = null
 * )
 *
 * @method static array filterByStatus(string $status)
 *
 * @method static int count()
 * @method static void clear()
 * @method static void refresh()
 * @method  static void forgetKey(string $moduleKey, string $key)
 * @see ModuleRegistryContract
 */
class CreatedRegistry extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'created.registry';
    }
}
