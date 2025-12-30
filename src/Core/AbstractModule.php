<?php

namespace SchoolPalm\ModuleBridge\Core;

use SchoolPalm\ModuleBridge\Contracts\ModuleContract;
use SchoolPalm\ModuleBridge\Contracts\ResolverContract;

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
 * @property ResolverContract|null $resolver Resolver used to resolve module files and components
 *
 * ─────────────────────────────────────────────────────────────
 * METHODS
 * ─────────────────────────────────────────────────────────────
 * - performAction() : mixed
 *      Executes the module's action lifecycle (child must implement)
 *
 * - loadModules() : void
 *      Load action handler classes or submodules (child must implement)
 *
 * - setContext(array $context) : void
 *      Injects runtime context into the module
 *
 * - setResolver(ResolverContract $resolver) : static
 *      Sets the resolver implementation
 *
 * - getResolver() : ResolverContract
 *      Retrieves the current resolver
 *
 * - hasResolver() : bool
 *      Checks if resolver is set
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

    /** @var ResolverContract|null Resolver used for module files/components */
    protected ?ResolverContract $resolver = null;

    /**
     * AbstractModule constructor.
     *
     * Only injects runtime context. No resolver or module loading occurs here.
     *
     * @param array<string, mixed> $context
     */
    public function __construct(array $context = [])
    {
        $this->modules = [];
        $this->setContext($context);
    }

    /* -----------------------------------------------------------------
     |  Context
     | -----------------------------------------------------------------
     */

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

    /* -----------------------------------------------------------------
     |  Resolver
     | -----------------------------------------------------------------
     */

    /**
     * Set the resolver implementation.
     *
     * @param ResolverContract $resolver
     * @return $this
     */
    public function setResolver(ResolverContract $resolver): static
    {
        $this->resolver = $resolver;
        return $this;
    }

    /**
     * Get the resolver implementation.
     *
     * @return ResolverContract
     */
    public function getResolver(): ResolverContract
    {
        if (! $this->resolver) {
            throw new \RuntimeException(
                'Module resolver has not been set.'
            );
        }

        return $this->resolver;
    }

    /**
     * Check if resolver is available.
     *
     * @return bool
     */
    public function hasResolver(): bool
    {
        return $this->resolver !== null;
    }

    /* -----------------------------------------------------------------
     |  Getters
     | -----------------------------------------------------------------
     */

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

    /* -----------------------------------------------------------------
     |  Lifecycle Contracts (Child must implement)
     | -----------------------------------------------------------------
     */

    abstract public function performAction(): mixed;

    abstract protected function loadModules(): void;

    abstract public function componentPath(): string;

    abstract public function moduleComponentPath(string $path = ''): string;

    abstract public function refererComponent(): string;
}
