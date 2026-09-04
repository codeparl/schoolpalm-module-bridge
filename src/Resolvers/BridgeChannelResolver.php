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
        // 1. Precedence 1: Explicitly requested channels via builder ->channels([...])
        if (! empty($event->requestedChannels)) {
            return $this->sanitizeChannels($event->requestedChannels);
        }

        // 2. Precedence 2: Recipient preferences (e.g. ['email' => true, 'sms' => false])
        $preferenceChannels = $this->extractChannelsFromPreferences($preferences);
        if (! empty($preferenceChannels)) {
            return $this->sanitizeChannels($preferenceChannels);
        }

        // 3. Precedence 3: System settings default per event
        $enabledChannels = SettingsHost::group('notifications')
            ->get("channels_enabled.{$event->event}", ['in_app']);

        $defaultChannels = is_array($enabledChannels) ? $enabledChannels : [$enabledChannels];

        return $this->sanitizeChannels($defaultChannels);
    }

    /**
     * Extract flat string channel names from preference payloads.
     *
     * Supports:
     * - ['email', 'sms']
     * - ['email' => true, 'sms' => false]
     * - [['channel' => 'email'], ['channel' => 'sms']]
     */
    protected function extractChannelsFromPreferences(array $preferences): array
    {
        $channels = [];

        foreach ($preferences as $key => $value) {
            if (is_string($value)) {
                $channels[] = $value;
            } elseif (is_string($key) && $value === true) {
                $channels[] = $key;
            } elseif (is_array($value) && isset($value['channel']) && is_string($value['channel'])) {
                $channels[] = $value['channel'];
            }
        }

        return $channels;
    }

    /**
     * Sanitize, filter, and normalize channel list.
     *
     * @param array<mixed> $channels
     * @return array<int, string>
     */
    protected function sanitizeChannels(array $channels): array
    {
        $clean = array_values(array_unique(array_filter(
            $channels,
            fn($item) => is_string($item) && ! empty(trim($item))
        )));

        return empty($clean) ? ['in_app'] : $clean;
    }
}
