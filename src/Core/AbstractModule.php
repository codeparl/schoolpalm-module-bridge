<?php

namespace SchoolPalm\ModuleBridge\Core;

use SchoolPalm\ModuleBridge\Contracts\ModuleContract;

/**
 * Class AbstractModule
 *
 * This is the **base abstract module class** that all modules should extend
 * when using the SchoolPalm / Module Bridge architecture.
 *
 * It implements the ModuleContract and defines the common structure
 * for module execution, action handling, and contextual injection.
 *
 * ─────────────────────────────────────────────────────────────
 * PURPOSE
 * ─────────────────────────────────────────────────────────────
 * - Acts as the bridge between **SchoolPalm** core and **module-sdk**
 * - Provides a consistent module lifecycle:
 *     1. Receive context from the framework
 *     2. Load submodules or action handlers
 *     3. Execute the assigned action
 * - Modules never interact with the framework directly—they always extend
 *   this abstract class to remain framework-agnostic.
 *
 * ─────────────────────────────────────────────────────────────
 * PROPERTIES
 * ─────────────────────────────────────────────────────────────
 * @property string $portal     The portal or area (e.g., 'admin', 'user') where the module is executed.
 * @property string $moduleName The unique name of the module.
 * @property string $action     The current action to perform (e.g., 'create', 'update', 'delete').
 * @property mixed  $id         Optional ID related to the action (e.g., record ID). Defaults to null.
 *
 * ─────────────────────────────────────────────────────────────
 * METHODS
 * ─────────────────────────────────────────────────────────────
 * - performAction() : void
 *      Executes the module's current action. Must be implemented in concrete modules.
 *
 * - loadModules() : void
 *      Loads child modules or submodules. Intended to be overridden by concrete implementations.
 *
 * - setContext(array $context) : void
 *      Dynamically injects runtime context (portal, moduleName, action, id) from the framework.
 *
 * @package SchoolPalm\ModuleBridge\Core
 */
abstract class AbstractModule implements ModuleContract
{
    protected string $portal;
    protected string $moduleName;
    protected string $action;
    protected mixed $id = null;

    /**
     * Execute the current module action.
     *
     * Concrete modules must implement this method to define
     * how the module handles its action lifecycle.
     *
     * @return void
     */
    abstract public function performAction(): void;

    /**
     * Load action handlers / submodules.
     *
     * Concrete modules should override this method to register
     * child modules or action-specific handlers if needed.
     *
     * @return void
     */
    abstract protected function loadModules(): void;

    /**
     * Inject runtime context into the module.
     *
     * This allows the framework or module SDK to provide:
     * - portal
     * - moduleName
     * - action
     * - id
     *
     * Only existing properties are updated.
     *
     * @param array<string, mixed> $context Key-value pairs of context data.
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


    public function getPortal(): string
{
    return $this->portal;
}

public function getModuleName(): string
{
    return $this->moduleName;
}

public function getAction(): string
{
    return $this->action;
}

public function getId(): mixed
{
    return $this->id;
}

}
