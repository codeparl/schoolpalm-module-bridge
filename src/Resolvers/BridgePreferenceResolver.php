<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Resolvers;

use SchoolPalm\MessageDelivery\Notification\Contracts\PreferenceResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class BridgePreferenceResolver implements PreferenceResolver
{
    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    /**
     * Resolve notification preferences for recipient(s) in the event payload.
     *
     * @return array<string, mixed>
     */
    public function resolve(NotificationEvent $event): array
    {
        $recipients = $event->data['recipients'] ?? [];

        if (! is_array($recipients)) {
            $recipients = [$recipients];
        }

        $recipientPreferences = [];

        foreach ($recipients as $recipient) {
            $extracted = $this->extractRecipientPreferences($recipient, $event->event);

            if (! empty($extracted)) {
                $recipientPreferences[] = $extracted;
            }
        }

        // 1. Return aggregated recipient preferences if present
        if (! empty($recipientPreferences)) {
            return [
                'channels' => $this->normalizePreferences($recipientPreferences),
            ];
        }

        // 2. Fall back to global/tenant event channel settings
        $enabledChannels = SettingsHost::group('notifications')
            ->get("channels_enabled.{$event->event}", ['in_app']);

        $fallbackChannels = is_array($enabledChannels) ? $enabledChannels : [$enabledChannels];

        return [
            'channels' => array_values(array_filter($fallbackChannels, 'is_string')),
        ];
    }

    /**
     * Duck-type preference extraction without concrete Eloquent dependencies.
     *
     * @return array<string, mixed>
     */
    protected function extractRecipientPreferences(mixed $recipient, string $event): array
    {
        if (empty($recipient)) {
            return [];
        }

        // 1. Method call (e.g. notificationPreferences($event))
        if (is_object($recipient) && method_exists($recipient, 'notificationPreferences')) {
            $result = $recipient->notificationPreferences($event);
            return is_array($result) ? $result : [];
        }

        // 2. Object property (e.g. $recipient->preferences or $recipient->notification_preferences)
        if (is_object($recipient)) {
            if (isset($recipient->preferences) && is_array($recipient->preferences)) {
                return $recipient->preferences;
            }

            if (isset($recipient->notification_preferences) && is_array($recipient->notification_preferences)) {
                return $recipient->notification_preferences;
            }
        }

        // 3. Array payload (e.g. ['preferences' => [...]])
        if (is_array($recipient)) {
            if (isset($recipient['preferences']) && is_array($recipient['preferences'])) {
                return $recipient['preferences'];
            }

            if (isset($recipient['notification_preferences']) && is_array($recipient['notification_preferences'])) {
                return $recipient['notification_preferences'];
            }
        }

        return [];
    }

    /**
     * Normalize mixed preference structures into a clean channel list/map.
     *
     * Preserves boolean maps like ['email' => true, 'sms' => false]
     * and array lists like ['email', 'sms'].
     *
     * @param array<int, array<mixed>> $allPreferences
     * @return array<string, mixed>
     */
    protected function normalizePreferences(array $allPreferences): array
    {
        $normalized = [];

        foreach ($allPreferences as $preference) {
            foreach ($preference as $key => $value) {
                // List of channel strings: ['email', 'sms']
                if (is_int($key) && is_string($value)) {
                    $normalized[$value] = true;
                    continue;
                }

                // Explicit channel toggles: ['email' => true, 'sms' => false]
                if (is_string($key) && is_bool($value)) {
                    // Precedence: explicit false overrides previous true
                    if (! isset($normalized[$key]) || $value === false) {
                        $normalized[$key] = $value;
                    }
                }
            }
        }

        return $normalized;
    }
}
