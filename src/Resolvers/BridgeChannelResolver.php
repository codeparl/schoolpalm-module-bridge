<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Resolvers;

use SchoolPalm\MessageDelivery\Notification\Contracts\ChannelResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class BridgeChannelResolver implements ChannelResolver
{
    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    public function resolve(NotificationEvent $event, array $preferences = []): array
    {
        $schoolId = $event->context['school_id'] ?? $this->contextResolver->schoolId();
        $tenantId = $event->context['tenant_id'] ?? $this->contextResolver->tenantId();

        $enabledChannels = SettingsHost::forTenant($tenantId)
            ->forSchool($schoolId)
            ->group('notifications')
            ->get("channels_enabled.{$event->event}", ['mail', 'database']);

        if (! is_array($enabledChannels)) {
            $enabledChannels = [$enabledChannels];
        }

        $requestedChannels = is_array($event->requestedChannels)
            ? $event->requestedChannels
            : [];

        $channels = array_values(array_filter(array_unique(array_merge(
            $enabledChannels,
            $preferences,
            $requestedChannels
        ))));

        return empty($channels) ? ['mail'] : $channels;
    }
}
