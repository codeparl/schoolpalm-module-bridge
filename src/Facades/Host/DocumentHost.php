<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Facades\Host;

use Illuminate\Support\Facades\Facade;
use SchoolPalm\ModuleBridge\Adapters\DocumentBuilderAdapter;

/**
 * @method static DocumentBuilderAdapter pdf()
 * @method static DocumentBuilderAdapter excel()
 * @method static DocumentBuilderAdapter word()
 * @method static DocumentBuilderAdapter csv()
 * @method static DocumentBuilderAdapter image()
 */
final class DocumentHost extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \SchoolPalm\ModuleBridge\Adapters\DocumentHost::class;
    }
}
