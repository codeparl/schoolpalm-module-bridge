<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Resolvers;

use SchoolPalm\MessageDelivery\Notification\Contracts\EventResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class BridgeEventResolver implements EventResolver
{
    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    /**
     * Resolve and enrich the execution context for a notification event.
     *
     * Explicitly provided event context overrides dynamic environment context.
     *
     * @return array<string, mixed>
     */
    public function resolve(NotificationEvent $event): array
    {
        // 1. Resolve dynamic runtime environment context
        $runtimeContext = [
            'tenant_id' => $this->contextResolver->tenantId(),
            'school_id' => $this->contextResolver->schoolId(true),
            'user_id'   => $this->contextResolver->userId(),
            'module'    => $this->contextResolver->currentModule(),
        ];

        // 2. Merge runtime context with explicitly provided event context
        // $event->context takes precedence over automatically resolved context
        return array_merge(
            array_filter($runtimeContext, fn($value) => $value !== null),
            $event->context
        );
    }
}
