<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Resolvers;

use SchoolPalm\MessageDelivery\Notification\Contracts\LanguageResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class BridgeLanguageResolver implements LanguageResolver
{
    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    public function resolve(NotificationEvent $event): ?string
    {
        // 1. Explicitly requested language on the event takes highest priority
        if (! empty($event->requestedLanguage) && is_string($event->requestedLanguage)) {
            return $event->requestedLanguage;
        }

        // 2. Resolve preferred locale from the recipient context
        $recipientLocale = $this->resolveRecipientLocale($event->data['recipients'] ?? []);
        if ($recipientLocale !== null) {
            return $recipientLocale;
        }

        // 3. Fall back to system/tenant settings via SettingsHost
        $configuredLocale = SettingsHost::group('localization')->get('locale');
        if (is_string($configuredLocale) && ! empty(trim($configuredLocale))) {
            return $configuredLocale;
        }

        // 4. Fall back to application default locale or hardcoded fallback
        return config('app.locale', 'en');
    }

    /**
     * Safely resolve locale attribute from mixed recipient representation.
     */
    protected function resolveRecipientLocale(mixed $recipients): ?string
    {
        if (! is_array($recipients)) {
            $recipients = [$recipients];
        }

        $firstRecipient = $recipients[0] ?? null;

        if ($firstRecipient === null) {
            return null;
        }

        // Array-based recipient representation
        if (is_array($firstRecipient)) {
            $locale = $firstRecipient['locale'] ?? $firstRecipient['language'] ?? null;
            return is_string($locale) && ! empty(trim($locale)) ? $locale : null;
        }

        // Object / Generic Model representation (duck-typing without importing Eloquent Model)
        if (is_object($firstRecipient)) {
            $locale = $firstRecipient->locale ?? $firstRecipient->language ?? null;
            return is_string($locale) && ! empty(trim($locale)) ? $locale : null;
        }

        return null;
    }
}
