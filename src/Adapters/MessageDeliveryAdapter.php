<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use SchoolPalm\MessageDelivery\Context\MessageContext;
use SchoolPalm\MessageDelivery\MessageDelivery;
use SchoolPalm\ModuleBridge\Adapters\MessageDeliveryProxy;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class MessageDeliveryAdapter
{
    /**
     * Explicit context overrides and accumulated state.
     */
    protected array $contextData = [];

    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    /**
     * Explicitly scope execution to a specific school (Immutable).
     */
    public function forSchool(?string $schoolId = null): static
    {
        $clone = clone $this;
        $clone->contextData['school_id'] = $schoolId ?? $this->contextResolver->schoolId(true);
        $clone->contextData['tenant_id'] ??= $this->contextResolver->tenantId();

        return $clone;
    }

    /**
     * Explicitly scope execution to a specific tenant (Immutable).
     */
    public function forTenant(?string $tenantId = null): static
    {
        $clone = clone $this;
        $clone->contextData['tenant_id'] = $tenantId ?? $this->contextResolver->tenantId();

        return $clone;
    }

    /**
     * Merge array or MessageContext into the adapter context data (Immutable).
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
     */
    public function withAutoScope(): static
    {
        $ambient = array_filter([
            'tenant_id'  => $this->contextResolver->tenantId(),
            'school_id'  => $this->contextResolver->schoolId(true),
            'user_id'    => $this->contextResolver->userId(),
            'module'     => $this->contextResolver->currentModule(),
            'module_key' => $this->contextResolver->currentModuleKey(),
        ], fn($val) => $val !== null && $val !== '');

        $clone = clone $this;
        $clone->contextData = array_merge($ambient, $this->contextData);

        return $clone;
    }

    /**
     * Build an instance of MessageDelivery configured with the resolved context.
     */
    public function instance(): MessageDelivery
    {
        $scoped = $this->withAutoScope();

        $cleanContext = array_filter(
            $scoped->contextData,
            fn($val) => $val !== null && $val !== ''
        );

        return MessageDelivery::withContext($cleanContext);
    }

    /*
    |--------------------------------------------------------------------------
    | Channel Builder Forwarding via Proxy
    |--------------------------------------------------------------------------
    */

    public function sms(): MessageDeliveryProxy
    {
        return $this->proxy($this->instance()->sms());
    }

    public function email(): MessageDeliveryProxy
    {
        return $this->proxy($this->instance()->email());
    }

    public function push(): MessageDeliveryProxy
    {
        return $this->proxy($this->instance()->push());
    }

    public function whatsapp(): MessageDeliveryProxy
    {
        return $this->proxy($this->instance()->whatsapp());
    }

    public function inApp(): MessageDeliveryProxy
    {
        return $this->proxy($this->instance()->inApp());
    }

    public function channels(array $channels): MessageDeliveryProxy
    {
        return $this->proxy($this->instance()->channels($channels));
    }

    /**
     * Wrap channel builder in MessageDeliveryProxy to handle automatic view namespace resolution.
     */
    protected function proxy(mixed $builder): MessageDeliveryProxy
    {
        $scoped = $this->withAutoScope();

        return new MessageDeliveryProxy(
            builder: $builder,
            contextResolver: $this->contextResolver,
            contextData: $scoped->contextData
        );
    }

    /**
     * Proxy dynamic calls to the configured MessageDelivery instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->instance()->{$method}(...$parameters);
    }
}
