<?php

namespace SchoolPalm\ModuleBridge\Facades;

use Illuminate\Support\Facades\Facade;
use SchoolPalm\ModuleBridge\Contracts\ModuleRegistryContract;

/**
 * Facade for the Autoload Module Registry.
 *
 * This registry represents modules discovered at runtime
 * (filesystem / package autoload).
 *
 * Important semantics:
 * - Modules are considered ALWAYS installed
 * - `group` represents the academic folder / level (e.g. Common, PriSec)
 * - install/uninstall are effectively no-ops (state does not persist lifecycle)
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
 *
 * @see ModuleRegistryContract
 */
class AutoloadRegistry extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'autoload.registry';
    }
}
