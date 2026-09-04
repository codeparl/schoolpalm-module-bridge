<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use SchoolPalm\MessageDelivery\Context\MessageContext;
use SchoolPalm\MessageDelivery\Notification\NotificationManager;
use SchoolPalm\MessageDelivery\Notification\Support\NotificationResult;
use SchoolPalm\ModuleBridge\Adapters\NotificationDispatchProxy;
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
     * Set explicit custom context key-value pairs (Immutable).
     */
    public function withContext(array|MessageContext $context): static
    {
        $clone = clone $this;
        $data = $context instanceof MessageContext ? $context->all() : $context;
        $clone->contextData = array_merge($clone->contextData, $data);

        return $clone;
    }

    /**
     * Merge ambient application context with explicit overrides (Immutable).
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
     * Resolve the final cleaned context array after applying auto-scope.
     */
    protected function resolvedContext(array $overrides = []): array
    {
        $scoped = $this->withAutoScope();
        $merged = array_merge($scoped->contextData, $overrides);

        return array_filter($merged, fn($val) => $val !== null && $val !== '');
    }

    /**
     * Start a fluent notification dispatch, pre-configured with auto-scoped context
     * and view namespace resolution.
     */
    public function event(string $event): NotificationDispatchProxy
    {
        $scopedContext = $this->resolvedContext();

        $dispatch = $this->notificationManager->event($event)
            ->context($scopedContext);

        return new NotificationDispatchProxy(
            dispatch: $dispatch,
            contextResolver: $this->contextResolver,
            contextData: $scopedContext
        );
    }

    /**
     * Resolve template/view name with module namespace prefix if '::' is omitted.
     */
    public function resolveTemplateKey(?string $template): ?string
    {
        if ($template === null || str_contains($template, '::')) {
            return $template;
        }

        $moduleKey = $this->contextResolver->currentModuleKey()
            ?? $this->contextData['module_key']
            ?? $this->contextData['module']
            ?? null;

        if ($moduleKey) {
            $prefix = Str::kebab(str_replace('\\', '.', $moduleKey));
            return $prefix . '::' . $template;
        }

        return $template;
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
        mixed $recipients = null,
    ): NotificationResult {
        if ($recipients !== null) {
            $data['recipients'] = $recipients;
        }

        $resolvedTemplate = $this->resolveTemplateKey($template);

        return $this->notificationManager->dispatch(
            event: $event,
            data: $data,
            context: $this->resolvedContext($context),
            metadata: $metadata,
            channels: $channels,
            language: $language,
            priority: $priority,
            template: $resolvedTemplate,
        );
    }

    /**
     * Convenience helper: dispatch a notification and return whether it was dispatched.
     */
    public function notify(
        string $event,
        array $data = [],
        array $context = [],
        array $metadata = [],
        array $channels = [],
        ?string $language = null,
        ?string $priority = null,
        ?string $template = null,
        mixed $recipients = null,
    ): bool {
        return $this->dispatch(
            event: $event,
            data: $data,
            context: $context,
            metadata: $metadata,
            channels: $channels,
            language: $language,
            priority: $priority,
            template: $template,
            recipients: $recipients,
        )->wasDispatched();
    }

    /**
     * Delegate any other unresolved calls directly to the underlying NotificationManager.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this->notificationManager, $method)) {
            return $this->notificationManager->{$method}(...$parameters);
        }

        throw new \BadMethodCallException(
            "Method [{$method}] does not exist on " . static::class
        );
    }
}
