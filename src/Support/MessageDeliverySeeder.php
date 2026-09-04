<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Support;

use SchoolPalm\MessageDelivery\MessageDelivery;
use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;

class MessageDeliverySeeder
{
    /**
     * Default fallback providers per channel.
     */
    protected const DEFAULT_PROVIDERS = [
        'email'    => 'laravel-mail',
        'sms'      => 'africas-talking',
        'whatsapp' => 'meta',
        'push'     => 'firebase',
        'in_app'   => 'database',
    ];

    /**
     * Seed default message delivery configurations into SettingsHost.
     */
    public static function seedMessageConfigs(?string $schoolId = null, array $overrides = []): void
    {
        // 1. Target the specific school scope if provided
        $host = $schoolId ? SettingsHost::forSchool($schoolId) : SettingsHost::getFacadeRoot();

        // 2. Retrieve definition metadata for registered providers
        $definitions = MessageDelivery::definitions();

        // 3. Set default provider per channel
        foreach (self::DEFAULT_PROVIDERS as $channel => $defaultProvider) {
            $host->group("message_delivery.{$channel}")
                ->put('default_provider', $defaultProvider);
        }

        // 4. Loop through registered providers and set default values/config structures
        foreach ($definitions as $providerName => $definition) {
            $channel = $definition->channel();
            $groupKey = "message_delivery.{$channel}.{$providerName}";

            // Enable default providers by default, disable others
            $isDefault = (self::DEFAULT_PROVIDERS[$channel] ?? null) === $providerName;
            $isEnabled = $overrides["{$groupKey}.enabled"] ?? $isDefault;

            $host->group($groupKey)->put('enabled', $isEnabled);

            // Extract default config fields dynamically from provider schema definitions
            $fieldConfig = [];
            $fields = MessageDelivery::providerConfigurationFields($providerName);

            foreach ($fields as $field) {
                $fieldName = $field['name'] ?? null;
                if (!$fieldName) {
                    continue;
                }

                // Check overrides first -> then env fallbacks -> then schema defaults
                $fieldConfig[$fieldName] = $overrides["{$groupKey}.config.{$fieldName}"]
                    ?? self::resolveEnvironmentFallback($providerName, $fieldName)
                    ?? ($field['default'] ?? null);
            }

            // Persist provider configuration
            $host->group($groupKey)->put('config', $fieldConfig);
        }
    }

    /**
     * Resolve default test/environment fallbacks for known provider fields.
     */
    protected static function resolveEnvironmentFallback(string $provider, string $field): mixed
    {
        return match ("{$provider}.{$field}") {
            'laravel-mail.mailer'           => env('MAIL_MAILER', 'mailpit'),
            'meta.phone_number_id'          => env('WHATSAPP_META_PHONE_NUMBER_ID', '100609346290000'),
            'meta.access_token'             => env('WHATSAPP_META_ACCESS_TOKEN', 'EAAGNO4353ksdfsfsD...'),
            'meta.business_id'              => env('WHATSAPP_META_BUSINESS_ID', '109840293840923'),
            'africas-talking.username'      => env('AFRICAS_TALKING_USERNAME', 'sandbox'),
            'africas-talking.api_key'       => env('AFRICAS_TALKING_API_KEY', 'sandbox'),
            default                         => null,
        };
    }
}
