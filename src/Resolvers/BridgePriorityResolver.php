<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Resolvers;

use SchoolPalm\MessageDelivery\Notification\Contracts\PriorityResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class BridgePriorityResolver implements PriorityResolver
{
    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    public function resolve(NotificationEvent $event): ?string
    {
        if ($event->requestedPriority !== null) {
            return $event->requestedPriority;
        }

        $schoolId = $event->context['school_id'] ?? $this->contextResolver->schoolId();
        $tenantId = $event->context['tenant_id'] ?? $this->contextResolver->tenantId();

        $priority = SettingsHost::forTenant($tenantId)
            ->forSchool($schoolId)
            ->group('notifications')
            ->get("priorities.{$event->event}", null);

        return $priority !== null ? (string) $priority : null;
    }
}
