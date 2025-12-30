<?php

namespace SchoolPalm\ModuleBridge\Core;

use SchoolPalm\ModuleBridge\Contracts\ModuleContract;

/**
 * Class AbstractModule
 *
 * This is the base abstract module class that all modules should extend
 * when using the SchoolPalm / Module Bridge architecture.
 *
 * ─────────────────────────────────────────────────────────────
 * PURPOSE
 * ─────────────────────────────────────────────────────────────
 * - Acts as the bridge between SchoolPalm core and module-sdk.
 * - Provides a consistent module lifecycle:
 *     1. Receive runtime context (portal, moduleName, action, id)
 *     2. Load submodules or action handlers
 *     3. Execute the assigned action
 * - Modules never interact with Laravel or HTTP requests directly.
 *
 * ─────────────────────────────────────────────────────────────
 * PROPERTIES
 * ─────────────────────────────────────────────────────────────
 * @property string $portal     Portal or area where the module runs (e.g., 'admin')
 * @property string $moduleName Unique module name (e.g., 'Students')
 * @property string $action     Current action to execute (e.g., 'add-student')
 * @property mixed  $id         Optional record ID related to the action
 * @property array  $modules    Loaded submodules or action handlers
 *
 * ─────────────────────────────────────────────────────────────
 * METHODS
 * ─────────────────────────────────────────────────────────────
 * - performAction() : void
 *      Executes the module's action lifecycle.
 *
 * - loadModules() : void
 *      Load action handler classes or submodules. To be implemented by concrete modules.
 *
 * - setContext(array $context) : void
 *      Injects runtime context into the module.
 *
 * @package SchoolPalm\ModuleBridge\Core
 */
abstract class AbstractModule implements ModuleContract
{
    /** @var string Current portal (e.g., admin, teacher) */
    protected string $portal;

    /** @var string Module name/key (e.g., students, exams) */
    protected string $moduleName;

    /** @var string Current action (e.g., index, create, update) */
    protected string $action;

    /** @var mixed Optional record ID */
    protected mixed $id = null;

    /** @var array Loaded submodules / action classes */
    protected array $modules = [];

    /**
     * AbstractModule constructor.
     *
     * Accepts a runtime context array which includes:
     * - portal
     * - moduleName
     * - action
     * - id
     *
     * @param array<string, mixed> $context
     */
    public function __construct(array $context = [])
    {
        $this->modules = [];
        $this->setContext($context);
        $this->loadModules();
    }

    /**
     * Inject runtime context into the module.
     *
     * Only existing properties will be updated.
     *
     * @param array<string, mixed> $context
     * @return void
     */
    public function setContext(array $context): void
    {
        foreach ($context as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Get current portal
     *
     * @return string
     */
    public function getPortal(): string
    {
        return $this->portal;
    }

    /**
     * Get module name
     *
     * @return string
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * Get current action
     *
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Get optional record ID
     *
     * @return mixed
     */
    public function getId(): mixed
    {
        return $this->id;
    }

    /**
     * Execute the current module action.
     *
     * Must be implemented by concrete modules.
     *
     * @return mixed
     */
    abstract public function performAction():mixed;

    /**
     * Load action handlers or submodules.
     *
     * Concrete modules should implement logic to populate $modules.
     *
     * @return void
     */
    abstract protected function loadModules(): void;

    /**
     * Resolve the module's front-end component path.
     *
     * @return string
     */
    abstract public function componentPath(): string;

    /**
     * Resolve a module-relative component path.
     *
     * @param string $path Optional subpath relative to the module's base component
     * @return string
     */
    abstract public function moduleComponentPath(string $path = ''): string;

    /**
     * Optional referer component path.
     *
     * @return string
     */
    abstract public function refererComponent(): string;
}
