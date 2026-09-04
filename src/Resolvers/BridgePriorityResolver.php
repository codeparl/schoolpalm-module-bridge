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

    /**
     * Resolve the delivery priority for a notification event.
     */
    public function resolve(NotificationEvent $event): ?string
    {
        // 1. Explicitly requested priority on dispatch builder takes highest precedence
        if (! empty($event->requestedPriority) && is_string($event->requestedPriority)) {
            return trim($event->requestedPriority);
        }

        // 2. Event-specific priority setting (e.g., notifications.priorities.student.admitted)
        $eventPriority = SettingsHost::group('notifications')
            ->get("priorities.{$event->event}");

        if (is_string($eventPriority) && ! empty(trim($eventPriority))) {
            return trim($eventPriority);
        }

        // 3. System-wide default priority setting
        $defaultPriority = SettingsHost::group('notifications')
            ->get('default_priority');

        if (is_string($defaultPriority) && ! empty(trim($defaultPriority))) {
            return trim($defaultPriority);
        }

        // 4. Default fallback priority level
        return 'normal';
    }
}
