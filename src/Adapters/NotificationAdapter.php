<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use Illuminate\Contracts\Container\Container;
use SchoolPalm\MessageDelivery\Context\MessageContext;
use SchoolPalm\MessageDelivery\Notification\Contracts\{
    ChannelResolver,
    EventResolver,
    LanguageResolver,
    PreferenceResolver,
    PriorityResolver,
    RecipientResolver,
    RetryResolver,
    ScheduleResolver,
    TemplateResolver
};
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationDispatch;
use SchoolPalm\MessageDelivery\Notification\NotificationManager;
use SchoolPalm\MessageDelivery\Notification\Support\NotificationResult;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class NotificationAdapter
{
    protected array $contextData = [];

    public function __construct(
        protected ContextResolver $contextResolver,
        protected NotificationManager $notificationManager,
        protected Container $container
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
     */
    public function withAutoScope(): static
    {
        $ambient = array_filter([
            'tenant_id' => $this->contextResolver->tenantId(),
            'school_id' => $this->contextResolver->schoolId(),
            'user_id'   => $this->contextResolver->userId(),
            'channel'   => $this->contextResolver->currentModule(),
        ], fn($val) => $val !== null && $val !== '');

        $clone = clone $this;
        $clone->contextData = array_merge($ambient, $this->contextData);

        return $clone;
    }

    /**
     * Start a fluent notification dispatch, pre-configured with auto-scoped context.
     */
    public function event(string $event): NotificationDispatch
    {
        $scoped = $this->withAutoScope();
        $cleanContext = array_filter($scoped->contextData, fn($val) => $val !== null && $val !== '');

        return $this->notificationManager->event($event)->context($cleanContext);
    }

    /**
     * Dispatch a notification event directly with auto-scoped context.
     */
    public function dispatch(
        string $event,
        array $data = [],
        array $context = [],
        array $metadata = [],
        array $channels = [],
        ?string $language = null,
        ?string $priority = null,
        ?string $template = null,
    ): NotificationResult {
        $scoped = $this->withAutoScope();
        $mergedContext = array_merge($scoped->contextData, $context);
        $cleanContext = array_filter($mergedContext, fn($val) => $val !== null && $val !== '');

        return $this->notificationManager->dispatch(
            event: $event,
            data: $data,
            context: $cleanContext,
            metadata: $metadata,
            channels: $channels,
            language: $language,
            priority: $priority,
            template: $template,
        );
    }

    /**
     * Delegate any other unresolved calls directly to the underlying NotificationManager instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->notificationManager->{$method}(...$parameters);
    }
}
