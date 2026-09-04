<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Concerns;

use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;

/**
 * Trait HasNotificationChannels
 *
 * Provides route mapping methods for models to support multi-channel dispatching
 * across standard, real-time, and collaboration channels.
 */
trait HasNotificationChannels
{
    /**
     * Route notification for the email channel.
     */
    public function routeNotificationForEmail(): ?string
    {
        return $this->email ?? null;
    }

    /**
     * Route notification for the SMS channel.
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone_number ?? $this->phone ?? null;
    }

    /**
     * Route notification for the WhatsApp channel.
     */
    public function routeNotificationForWhatsapp(): ?string
    {
        return $this->whatsapp_number ?? $this->whatsapp ?? $this->phone_number ?? $this->phone ?? null;
    }

    /**
     * Route notification for the Push channel (FCM device token).
     */
    public function routeNotificationForPush(): ?string
    {
        return $this->fcm_token ?? $this->device_token ?? null;
    }

    /**
     * Route notification for the In-App channel.
     */
    public function routeNotificationForInApp(): mixed
    {
        return $this;
    }

    /**
     * Route notification for the Slack channel (Webhook URL or Channel ID).
     */
    public function routeNotificationForSlack(): ?string
    {
        return $this->slack_webhook_url ?? $this->slack_channel ?? null;
    }

    /**
     * Route notification for the Discord channel (Webhook URL).
     */
    public function routeNotificationForDiscord(): ?string
    {
        return $this->discord_webhook_url ?? null;
    }

    /**
     * Route notification for the Telegram channel (Chat ID).
     */
    public function routeNotificationForTelegram(): ?string
    {
        return $this->telegram_chat_id ?? null;
    }

    /**
     * Route notification for the WebSocket / Broadcast channel (Channel name/topic).
     */
    public function routeNotificationForWebsocket(): ?string
    {
        // Defaults to user private channel if a method or attribute exists
        if (method_exists($this, 'receivesBroadcastNotificationsOn')) {
            return $this->receivesBroadcastNotificationsOn();
        }

        return $this->websocket_channel ?? ('private-user.' . ($this->id ?? ''));
    }

    /**
     * Route notification for generic Webhook channels.
     */
    public function routeNotificationForWebhook(): ?string
    {
        return $this->webhook_url ?? null;
    }

    /**
     * Get notification preferences for this model scoped with the user ID from settings.
     *
     * @param string|null $event The event name being evaluated (e.g. 'exam.published')
     * @return array<string, mixed>
     */
    public function notificationPreferences(?string $event = null): array
    {
        if (! isset($this->id)) {
            return [];
        }

        // Fetch settings scoped to this specific user ID
        $userPreferences = SettingsHost::group("notifications.users.{$this->id}")
            ->get('channels_enabled', []);

        if (! is_array($userPreferences)) {
            return [];
        }

        // If scoped to a specific event, check if rules exist for it
        if ($event && isset($userPreferences[$event])) {
            return is_array($userPreferences[$event]) ? $userPreferences[$event] : [$userPreferences[$event]];
        }

        return $userPreferences;
    }
}
