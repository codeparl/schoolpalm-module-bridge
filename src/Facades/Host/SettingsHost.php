<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Facades\Host;

use Illuminate\Support\Facades\Facade;
use SchoolPalm\ModuleBridge\Adapters\SettingsAdapter;
use SchoolPalm\ModuleBridge\Support\MessageDeliverySeeder;

/**
 * Settings Adapter with Automated Context Scope Resolution
 *
 * --- Retrieval ---
 * @method static mixed get(string $key, mixed $default = null)
 * @method static array all()
 * @method static bool has(string $key)
 *
 * --- Mutation ---
 * @method static bool set(string $key, mixed $value)
 * @method static bool forget(string $key)
 *
 * --- Scope Fluent Chaining ---
 * @method static SettingsAdapter forTenant(?string $tenantId = null)
 * @method static SettingsAdapter forSchool(?string $schoolId = null)
 * @method static SettingsAdapter forUser(?string $userId = null)
 * @method static mixed settings(?string $path = null, mixed $default = null): mixed
 * @mixin SettingsAdapter
 */
class SettingsHost extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsAdapter::class;
    }
}
