<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use SchoolPalm\AppSettings\Builders\SettingsBuilder;
use SchoolPalm\AppSettings\Managers\SettingsManager;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class SettingsAdapter
{
    protected ?string $contextType = null;
    protected string|int|null $contextId = null;
    protected ?string $group = null;
    protected ?string $connection = null;

    public function __construct(
        protected SettingsManager $manager,
        protected ContextResolver $contextResolver
    ) {}

    /**
     * Scope explicitly for a group (Supports both group() and withGroup()).
     */
    public function group(?string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function withGroup(?string $group): self
    {
        return $this->group($group);
    }

    /**
     * Scope explicitly for a database connection (Supports both connection() and withConnection()).
     */
    public function connection(?string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function withConnection(?string $connection): self
    {
        return $this->connection($connection);
    }

    /**
     * Scope explicitly for a school (Default scope).
     */
    public function forSchool(?string $schoolId = null): self
    {
        $this->contextType = 'school';
        $this->contextId = $schoolId ?? $this->contextResolver->schoolId();

        return $this;
    }

    /**
     * Scope explicitly for a tenant.
     */
    public function forTenant(?string $tenantId = null): self
    {
        $this->contextType = 'tenant';
        $this->contextId = $tenantId ?? $this->contextResolver->tenantId();

        return $this;
    }

    /**
     * Scope explicitly for a user.
     */
    public function forUser(?string $userId = null): self
    {
        $this->contextType = 'user';
        $this->contextId = $userId ?? $this->contextResolver->userId();

        return $this;
    }

    /**
     * Automatically resolve context with 'school' as the default scope.
     */
    public function withAutoScope(): self
    {
        if ($this->contextType !== null) {
            return $this;
        }

        // Default Priority: School -> Tenant -> User
        if ($schoolId = $this->contextResolver->schoolId()) {
            return $this->forSchool($schoolId);
        }

        if ($tenantId = $this->contextResolver->tenantId()) {
            return $this->forTenant($tenantId);
        }

        if ($userId = $this->contextResolver->userId()) {
            return $this->forUser($userId);
        }

        return $this;
    }

    /**
     * Alias method mapping set() to put().
     */
    public function set(string $key, mixed $value): static
    {
        $this->__call('put', [$key, $value]);

        return $this;
    }

    /**
     * Build the underlying SettingsBuilder with all accumulated modifiers.
     */
    protected function newBuilder(): SettingsBuilder
    {
        $this->withAutoScope();

        // Start builder with connection
        $builder = $this->manager->connection($this->connection);

        // Apply context scope if resolved
        if ($this->contextType !== null) {
            $builder = $builder->context($this->contextType, $this->contextId);
        }

        // Apply explicit group or default group ('default') to maintain isolation
        $builder = $builder->group($this->group ?? 'default');

        return $builder;
    }

    /**
     * Proxy operation calls directly to the constructed SettingsBuilder.
     */
    public function __call(string $method, array $parameters): mixed
    {
        $builder = $this->newBuilder();

        $result = $builder->{$method}(...$parameters);

        // Reset state after executing the operation
        $this->resetState();

        return $result === $builder ? $this : $result;
    }

    /**
     * Reset transient state after execution.
     */
    protected function resetState(): void
    {
        $this->contextType = null;
        $this->contextId = null;
        $this->group = null;
        $this->connection = null;
    }
}
