<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use SchoolPalm\MessageDelivery\Builders\ChannelMessageBuilder;
use SchoolPalm\MessageDelivery\Builders\MultiChannelMessageBuilder;
use SchoolPalm\MessageDelivery\Context\MessageContext;
use SchoolPalm\MessageDelivery\MessageDelivery;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class MessageDeliveryAdapter
{
    protected array $contextData = [];

    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    /**
     * Explicitly scope execution to a specific school.
     */
    public function forSchool(?string $schoolId = null): static
    {
        $clone = clone $this;
        $clone->contextData['school_id'] = $schoolId ?? $this->contextResolver->schoolId();
        $clone->contextData['tenant_id'] ??= $this->contextResolver->tenantId();

        return $clone;
    }

    /**
     * Explicitly scope execution to a specific tenant.
     */
    public function forTenant(?string $tenantId = null): static
    {
        $clone = clone $this;
        $clone->contextData['tenant_id'] = $tenantId ?? $this->contextResolver->tenantId();

        return $clone;
    }

    /**
     * Set explicit custom context key-value pairs.
     */
    public function withContext(array|MessageContext $context): static
    {
        $clone = clone $this;
        $data = $context instanceof MessageContext ? $context->all() : $context;
        $clone->contextData = array_merge($clone->contextData, $data);

        return $clone;
    }

    /**
     * Merge ambient application context with explicit overrides.
     * Custom context takes priority over ambient defaults.
     */
    public function withAutoScope(): static
    {
        $ambient = array_filter([
            'tenant_id' => $this->contextResolver->tenantId(),
            'school_id' => $this->contextResolver->schoolId(),
            'module'    => $this->contextResolver->currentModule(),
        ], fn($val) => $val !== null);

        $clone = clone $this;
        // Ambient acts as base defaults; contextData overrides or expands on it
        $clone->contextData = array_merge($ambient, $this->contextData);

        return $clone;
    }

    /**
     * Build an instance of MessageDelivery configured with the resolved context.
     */
    public function instance(): MessageDelivery
    {
        $scoped = $this->withAutoScope();

        // Filter out null entries
        $cleanContext = array_filter($scoped->contextData, fn($val) => $val !== null);

        return MessageDelivery::withContext($cleanContext);
    }

    public function sms(): ChannelMessageBuilder
    {
        return $this->instance()->sms();
    }

    public function email(): ChannelMessageBuilder
    {
        return $this->instance()->email();
    }

    public function push(): ChannelMessageBuilder
    {
        return $this->instance()->push();
    }

    public function whatsapp(): ChannelMessageBuilder
    {
        return $this->instance()->whatsapp();
    }

    public function inApp(): ChannelMessageBuilder
    {
        return $this->instance()->inApp();
    }

    public function channels(array $channels): MultiChannelMessageBuilder
    {
        return $this->instance()->channels($channels);
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->instance()->{$method}(...$parameters);
    }
}
