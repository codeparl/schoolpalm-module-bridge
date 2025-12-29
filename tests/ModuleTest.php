<?php

use PHPUnit\Framework\TestCase;
use SchoolPalm\ModuleBridge\Core\AbstractModule;

/**
 * Dummy module for testing
 */
class DummyModule extends AbstractModule
{
    public bool $actionPerformed = false;

    /**
     * Perform an action (void as per ModuleContract)
     */
    public function performAction(): void
    {
        // Example side effect to test execution
        $this->actionPerformed = true;

        // Optional: echo for output test
        echo "dummy action";
    }

    protected function loadModules(): void {}

    public function componentPath(): string
    {
        return 'DummyModule/Index';
    }

    public function moduleComponentPath(string $path = ''): string
    {
        return 'DummyModule/' . $path;
    }

    public function refererComponent(): string
    {
        return '';
    }
}

/**
 * ModuleTest PHPUnit tests
 */
class ModuleTest extends TestCase
{
    public function testModuleActionExecution()
    {
        $module = new DummyModule();

        // Set context as normally done by the framework
        $module->setContext([
            'portal' => 'admin',
            'moduleName' => 'students',
            'action' => 'list',
            'id' => 123
        ]);

        // Capture output (since performAction is void)
        $this->expectOutputString('dummy action');
        $module->performAction();

        // Test side effect
        $this->assertTrue($module->actionPerformed);

        // Test that context is correctly set
        $this->assertEquals('admin', $module->getPortal());
        $this->assertEquals('students', $module->getModuleName());
        $this->assertEquals('list', $module->getAction());
        $this->assertEquals(123, $module->getId());
    }

    public function testModuleComponentPaths()
    {
        $module = new DummyModule();

        $this->assertEquals('DummyModule/Index', $module->componentPath());
        $this->assertEquals('DummyModule/custom', $module->moduleComponentPath('custom'));
        $this->assertEquals('', $module->refererComponent());
    }
}
