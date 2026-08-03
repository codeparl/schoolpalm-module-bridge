<?php

namespace SchoolPalm\ModuleBridge\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array all()
 * @method static array getConfig()
 * @method static array refresh()
 * @method static array find(string|int $value, string $field = 'code')
 * @method static array forLevel(int $levelId)
 * @method static array readCache(string|null $type = null)
 * @method static self type(?string $type)
 */
class SdkConfig extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sdk.config';
    }
}
