<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use SchoolPalm\CacheStore\Manager\CacheStoreManager;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class CacheAdapter
{
    protected ?string $driver = null;
    protected ?string $store = null;
    protected array $tags = [];
    protected ?string $tenantId = null;
    protected ?string $schoolId = null;
    protected bool $hasExplicitContext = false;

    public function __construct(
        protected CacheStoreManager $manager,
        protected ContextResolver $contextResolver
    ) {}

    /**
     * Target a specific cache driver.
     */
    public function driver(?string $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * Target a specific cache store.
     */
    public function store(?string $store): static
    {
        $this->store = $store;

        return $this;
    }

    /**
     * Attach cache tags to the operation.
     */
    public function tags(array|string $tags): static
    {
        $this->tags = (array) $tags;

        return $this;
    }

    /**
     * Explicitly set school context.
     */
    public function forSchool(?string $schoolId = null): static
    {
        $clone = clone $this;
        $clone->schoolId = $schoolId ?? $this->contextResolver->schoolId(true);
        $clone->tenantId ??= $this->contextResolver->tenantId();
        $clone->hasExplicitContext = true;

        return $clone;
    }

    /**
     * Explicitly set tenant context.
     */
    public function forTenant(?string $tenantId = null): static
    {
        $this->tenantId = $tenantId ?? $this->contextResolver->tenantId();
        $this->hasExplicitContext = true;

        return $this;
    }

    /**
     * Automatically resolve context with school as default priority.
     */
    public function withAutoScope(): static
    {
        if ($this->hasExplicitContext) {
            return $this;
        }

        // Priority resolution: School -> Tenant
        $this->schoolId = $this->contextResolver->schoolId(true);
        $this->tenantId = $this->contextResolver->tenantId();

        return $this;
    }

    /**
     * Forward operation calls directly to CacheStoreManager and reset state.
     */
    public function __call(string $method, array $parameters): mixed
    {
        $this->withAutoScope();

        // Start with the base manager instance
        $targetManager = $this->manager->forContext($this->tenantId, $this->schoolId);

        if ($this->driver !== null) {
            $targetManager = $targetManager->driver($this->driver);
        }

        if ($this->store !== null) {
            $targetManager = $targetManager->store($this->store);
        }

        if (!empty($this->tags)) {
            $targetManager = $targetManager->tags($this->tags);
        }

        // Call the method on the configured manager instance
        $result = $targetManager->{$method}(...$parameters);

        $this->resetState();

        return $result;
    }

    /**
     * Reset transient state after execution to prevent cross-call leakage.
     */
    protected function resetState(): void
    {
        $this->driver = null;
        $this->store = null;
        $this->tags = [];
        $this->tenantId = null;
        $this->schoolId = null;
        $this->hasExplicitContext = false;
    }
}
