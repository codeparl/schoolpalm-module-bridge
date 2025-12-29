<?php

use PHPUnit\Framework\TestCase;
use SchoolPalm\ModuleBridge\Support\Bridge;
use SchoolPalm\ModuleBridge\Core\AbstractModule;

/**
 * Dummy module for testing
 */
class TestModule extends AbstractModule
{
    public bool $actionPerformed = false;

    /**
     * Perform an action.
     * Must match ModuleContract: void
     */
    public function performAction(): void
    {
        // Example side effect
        $this->actionPerformed = true;

        // Optional: echo output for testing
        echo "action executed";
    }

    protected function loadModules(): void {}

    public function componentPath(): string
    {
        return 'TestModule/Index';
    }

    public function moduleComponentPath(string $path = ''): string
    {
        return 'TestModule/' . $path;
    }

    public function refererComponent(): string
    {
        return '';
    }
}

/**
 * Tests for the Bridge functionality
 */
class BridgeTest extends TestCase
{
    public function testBridgeBindingAndExecution()
    {
        Bridge::bind(TestModule::class);

        $module = new \SchoolPalm\ModuleBridge\Core\Module();

        // Capture output since performAction() is void
        $this->expectOutputString('action executed');
        $module->performAction();

        // Also test side effect
        $this->assertTrue($module->actionPerformed);
    }

    public function testBridgeBindingOnlyOnce()
    {
        // Second bind should not throw exception
        Bridge::bind(TestModule::class);
        $this->assertTrue(true);
    }

    public function testModuleComponentPaths()
    {
        $module = new TestModule();

        $this->assertEquals('TestModule/Index', $module->componentPath());
        $this->assertEquals('TestModule/custom', $module->moduleComponentPath('custom'));
        $this->assertEquals('', $module->refererComponent());
    }
}
