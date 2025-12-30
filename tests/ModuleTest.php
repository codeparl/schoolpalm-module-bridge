<?php

use PHPUnit\Framework\TestCase;
use SchoolPalm\ModuleBridge\Support\Bridge;
use SchoolPalm\ModuleBridge\Core\AbstractModule;
use SchoolPalm\ModuleBridge\Core\Module;

/**
 * Concrete runtime BaseModule
 * (simulates SDK or SchoolPalm runtime)
 */
class DummyBaseModule extends AbstractModule
{
    protected function loadModules(): void
    {
        // no-op
    }

    public function performAction(): mixed
    {
        return 'action executed';
    }

    public function componentPath(): string
    {
        return 'DummyModule/Index';
    }

    public function moduleComponentPath(string $path = ''): string
    {
        return 'DummyModule/' . ltrim($path, '/');
    }

    public function refererComponent(): string
    {
        return '';
    }
}

/**
 * Tests for Module bridge
 */
class ModuleTest extends TestCase
{
    protected function setUp(): void
    {
        /**
         * Bind the runtime BaseModule
         * This simulates what SDK or SchoolPalm does in its ServiceProvider
         */
        Bridge::bind(DummyBaseModule::class);
    }

    public function testComponentPathsDelegation()
    {
        /**
         * This simulates:
         * class Main extends Module {}
         */
        $main = new class extends Module {};

        $this->assertEquals(
            'DummyModule/Index',
            $main->componentPath()
        );

        $this->assertEquals(
            'DummyModule/custom',
            $main->moduleComponentPath('custom')
        );

        $this->assertEquals(
            '',
            $main->refererComponent()
        );
    }

    public function testActionDelegation()
    {
        $main = new class extends Module {};

        $this->assertEquals(
            'action executed',
            $main->performAction()
        );
    }
}
