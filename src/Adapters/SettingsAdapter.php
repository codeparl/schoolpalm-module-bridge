<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use SchoolPalm\AppSettings\Builders\SettingsBuilder;
use SchoolPalm\AppSettings\Managers\SettingsManager;
use SchoolPalm\AppSettings\Support\SettingsScope;
use SchoolPalm\MessageDelivery\Contracts\ConfigurationField;
use SchoolPalm\MessageDelivery\MessageDelivery;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class SettingsAdapter
{
    protected SettingsScope $scope;

    public function __construct(
        protected SettingsManager $manager,
        protected ContextResolver $contextResolver,
        ?SettingsScope $scope = null
    ) {
        $this->scope = $scope ?? new SettingsScope();
    }

    /**
     * Scope explicitly for a database connection (Immutable).
     */
    public function withConnection(?string $connection): static
    {
        $clone = clone $this;
        $clone->scope = $this->scope->withConnection($connection);

        return $clone;
    }

    public function connection(?string $connection): static
    {
        return $this->withConnection($connection);
    }

    /**
     * Helper to read a setting key or nested group path directly.
     *
     * Example:
     * $adapter->settings('message_delivery.email.laravel-mail.enabled')
     *
     * Proxies to:
     * $adapter->group('message_delivery.email.laravel-mail.enabled')->get()
     */
    public function settings(?string $path = null, mixed $default = null): mixed
    {
        if ($path === null || $path === '') {
            return $this->newBuilder()->get(null, $default);
        }

        return $this->withGroup($path)->newBuilder()->get(null, $default);
    }

    /**
     * Scope explicitly for a context type and ID (Immutable).
     */
    public function withContext(string $type, string|int|null $id = null): static
    {
        $clone = clone $this;
        $clone->scope = $this->scope->withContext($type, $id);

        return $clone;
    }

    /**
     * Scope explicitly for a group (Immutable).
     */
    public function withGroup(?string $group): static
    {
        $clone = clone $this;
        $clone->scope = $this->scope->withGroup($group);

        return $clone;
    }

    public function group(?string $group): static
    {
        return $this->withGroup($group);
    }

    /**
     * Attach explicit cache context for tenant and school isolation (Immutable).
     */
    public function withCacheContext(mixed $context): static
    {
        $clone = clone $this;
        $clone->scope = $this->scope->withCacheContext($context);

        return $clone;
    }

    /**
     * Scope explicitly for a school (Immutable).
     */
    public function forSchool(string|int|null $schoolId = null, ?string $schoolCode = null): static
    {
        $resolvedId   = $schoolId ?? $this->contextResolver->schoolId(true);
        $resolvedCode = $schoolCode ?? $this->contextResolver->schoolCode();

        $adapter = $this->withContext('school', $resolvedId ?: null);

        // Automatically bind matching cache context
        if ($resolvedCode !== '') {
            $tenantId  = $this->contextResolver->tenantId();
            $autoCache = array_values(array_filter([$tenantId, $resolvedCode]));

            $adapter = $adapter->withCacheContext($autoCache);
        }

        return $adapter;
    }

    /**
     * Scope explicitly for a tenant (Immutable).
     */
    public function forTenant(?string $tenantId = null): static
    {
        $resolvedId = $tenantId ?? $this->contextResolver->tenantId();

        return $this->withContext('tenant', $resolvedId ?: null);
    }

    /**
     * Scope explicitly for a user (Immutable).
     */
    public function forUser(?string $userId = null): static
    {
        $resolvedId = $userId ?? $this->contextResolver->userId();

        return $this->withContext('user', $resolvedId ?: null);
    }

    /**
     * Automatically resolve context, cache context, and connection from ContextResolver.
     */
    public function withAutoScope(): static
    {
        $adapter = $this;

        // 1. Auto-resolve database context priority: School -> Tenant -> User
        if (!$adapter->scope->hasContext()) {
            if ($schoolId = $this->contextResolver->schoolId(true)) {
                $adapter = $adapter->forSchool($schoolId);
            } elseif ($tenantId = $this->contextResolver->tenantId()) {
                $adapter = $adapter->forTenant($tenantId);
            } elseif ($userId = $this->contextResolver->userId()) {
                $adapter = $adapter->forUser($userId);
            }
        }

        // 2. Auto-resolve cache context isolation
        if ($adapter->scope->cacheContext() === null) {
            $tenantId   = $this->contextResolver->tenantId();
            $schoolCode = $this->contextResolver->schoolCode();

            $autoCache = array_filter([
                $tenantId !== '' ? $tenantId : null,
                $schoolCode !== '' ? $schoolCode : null,
            ]);

            if (!empty($autoCache)) {
                $adapter = $adapter->withCacheContext(array_values($autoCache));
            }
        }
        return $adapter;
    }

    /**
     * Get the current settings scope instance.
     */
    public function getScope(): SettingsScope
    {
        return $this->scope;
    }

    /**
     * Alias mapping set() to put().
     */
    public function set(string $key, mixed $value): mixed
    {
        return $this->__call('put', [$key, $value]);
    }

    /**
     * Flush settings on underlying scoped builder.
     */
    public function flush(?string $groupPrefix = null): mixed
    {
        $scopedAdapter = $this->withAutoScope();

        if ($groupPrefix !== null) {
            $scopedAdapter = $scopedAdapter->withGroup($groupPrefix);
        }

        return $scopedAdapter->newBuilder()->flush();
    }

    /**
     * Clear settings scoped to the currently configured group or context.
     */
    public function clear(): mixed
    {
        return $this->flush();
    }

    /**
     * Seed default message delivery configurations.
     */
    public function seedMessageConfigs(
        string|int|null $schoolId = null,
        array $defaults = [],
        array $overrides = []
    ): void {
        $host = $schoolId ? $this->forSchool($schoolId) : $this;

        $channelDefaults = array_merge([
            'email'    => 'laravel-mail',
            'sms'      => 'egosms',
            'whatsapp' => 'meta',
            'push'     => 'firebase',
            'in_app'   => 'database-notifications',
        ], $defaults);

        // 1. Seed channel default selections
        foreach ($channelDefaults as $channel => $provider) {
            $host->group("message_delivery.{$channel}")
                ->put('default_provider', $provider);
        }

        // 2. Iterate through registered provider definitions
        foreach (MessageDelivery::definitions() as $providerName => $definition) {
            $channel  = $definition->channel();
            $groupKey = "message_delivery.{$channel}.{$providerName}";

            // Enable if it matches the designated default for the channel
            $isDefault = ($channelDefaults[$channel] ?? null) === $providerName;
            $host->group($groupKey)->put('enabled', $isDefault);

            // Populate configuration options using ConfigurationField objects
            $fieldConfig = [];

            /** @var ConfigurationField $field */
            foreach ($definition->configurationFields() as $field) {
                $name = $field->name();

                $fieldConfig[$name] = $overrides[$providerName][$name]
                    ?? $field->default();
            }

            $host->group($groupKey)->put('config', $fieldConfig);
        }
    }

    /**
     * Build underlying SettingsBuilder using injected SettingsManager and proxy scope state.
     */
    protected function newBuilder(): SettingsBuilder
    {
        $scopedAdapter = $this->withAutoScope();
        $scope = $scopedAdapter->getScope();

        // 1. Connection
        $builder = $this->manager->connection($scope->connection());

        // 2. Context Type & ID
        if ($scope->hasContext() && $scope->contextId() !== null) {
            $builder = $builder->context($scope->contextType(), $scope->contextId());
        }

        // 3. Group
        if ($scope->hasGroup()) {
            $builder = $builder->group($scope->group());
        }

        // 4. Cache Context
        if ($scope->cacheContext() !== null && method_exists($builder, 'withCacheContext')) {
            $builder = $builder->withCacheContext($scope->cacheContext());
        }

        return $builder;
    }

    /**
     * Proxy operation calls directly to constructed SettingsBuilder.
     */
    public function __call(string $method, array $parameters): mixed
    {
        $builder = $this->newBuilder();
        $result = $builder->{$method}(...$parameters);

        return $result === $builder ? $this : $result;
    }
}
