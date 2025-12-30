<?php

namespace SchoolPalm\ModuleBridge\Core;

use SchoolPalm\ModuleBridge\Contracts\ModuleContract;

/**
 * Class Module
 *
 * Bridge / delegating base class for all SchoolPalm modules.
 *
 * PURPOSE:
 * - Acts as a "bridge" between the host application and module implementations.
 * - Satisfies IDEs and static analyzers by implementing ModuleContract.
 * - Delegates actual logic to the host application's BaseModule via Bridge::bind().
 *
 * IMPORTANT:
 * - This class is NOT intended for runtime usage.
 * - Any runtime execution is handled by the bound BaseModule.
 * - Modules extending this class will transparently use BaseModule at runtime.
 *
 * HOW TO USE:
 * 1. The host application calls:
 *      Bridge::bind(App\Core\BaseModule::class);
 * 2. Modules extend this class:
 *      class Main extends Module {}
 * 
 *
 * @package SchoolPalm\ModuleBridge\Core
 * @author  Hassan Mugabo <cybarox@gmail.com>
 * @license MIT
 * @link    https://www.github.com/codeparl
 * @date    2025-12-30
 */
class Module extends AbstractModule implements ModuleContract
{
    /**
     * Dummy implementation for IDE/static analysis.
     * Real logic exists in BaseModule.
     *
     * @return mixed
     */
    public function performAction(): mixed
    {
        return null;
    }

    /**
     * Dummy implementation for IDE/static analysis.
     *
     * @return string
     */
    public function componentPath(): string
    {
        return '';
    }

    /**
     * Dummy implementation for IDE/static analysis.
     *
     * @param string $path Optional subpath relative to module base.
     * @return string
     */
    public function moduleComponentPath(string $path = ''): string
    {
        return '';
    }

    /**
     * Dummy implementation for IDE/static analysis.
     *
     * @return string
     */
    public function refererComponent(): string
    {
        return '';
    }

    /**
     * Dummy implementation for IDE/static analysis.
     * Real logic exists in BaseModule.
     *
     * @return void
     */
    protected function loadModules(): void
    {
        // No-op for dummy implementation
    }
}
