<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Resolvers;

use SchoolPalm\MessageDelivery\Notification\Contracts\TemplateResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\MessageDelivery\Templates\Template;
use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class BridgeTemplateResolver implements TemplateResolver
{
    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    public function resolve(NotificationEvent $event, array $channels = [], ?string $language = null): ?Template
    {
        $targetChannels = array_values(array_filter($channels ?: $event->requestedChannels));

        if (empty($targetChannels)) {
            $targetChannels = ['email'];
        }

        $language = $language ?? $event->requestedLanguage;
        $notificationGroup = SettingsHost::group('notifications');

        foreach ($targetChannels as $channel) {
            // 1. Check direct view/content overrides (e.g., ->view(), ->title(), ->text())
            $explicitTemplate = $this->resolveFromExplicitOverrides($event, $channel);
            if ($explicitTemplate !== null) {
                return $explicitTemplate;
            }

            // 2. Resolve from event payload ($event->data)
            $payloadConfig = $this->extractFromDataPayload($event, $channel);

            // 3. Resolve from settings host if no payload config found
            $templateConfig = $payloadConfig ?? $this->extractFromSettings($notificationGroup, $event->event, $channel);

            if ($templateConfig === null) {
                continue;
            }

            // Handle language localization sub-key
            if ($language !== null && is_array($templateConfig) && isset($templateConfig[$language])) {
                $templateConfig = $templateConfig[$language];
            }

            // Build template object from config
            $template = $this->buildTemplateFromConfig($event, $channel, $templateConfig);
            if ($template !== null) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Resolve template when ->view() or explicit content was defined on the dispatch builder.
     */
    protected function resolveFromExplicitOverrides(NotificationEvent $event, string $channel): ?Template
    {
        $view = $event->metadata['view'] ?? $event->data['view'] ?? null;
        $title = $event->metadata['title'] ?? $event->data['title'] ?? null;
        $text = $event->metadata['text'] ?? $event->data['text'] ?? null;

        // If no view or explicit text/title was specified, return null to allow database/setting resolution
        if (empty($view) && empty($text) && empty($title)) {
            return null;
        }

        return new Template(
            name: $event->requestedTemplate ?? "{$event->event}.{$channel}",
            channel: $channel,
            // Guard: If $view exists, supply a fallback content indicator so driver validations won't fail
            content: ! empty($text) ? (string) $text : ($view ? "@include('{$view}')" : ''),
            variables: $event->data,
            subject: $title !== null ? (string) $title : null,
            view: $view !== null ? (string) $view : null
        );
    }

    /**
     * Extract template configuration from $event->data payload.
     */
    protected function extractFromDataPayload(NotificationEvent $event, string $channel): mixed
    {
        if (isset($event->data['templates'][$channel])) {
            return $event->data['templates'][$channel];
        }

        if ($channel === 'email' && isset($event->data['template'])) {
            return $event->data['template'];
        }

        return null;
    }

    /**
     * Extract template configuration from SettingsHost.
     */
    protected function extractFromSettings(mixed $group, string $event, string $channel): mixed
    {
        $configKey = "templates.{$event}.{$channel}";
        $templateConfig = $group->get($configKey);

        if ($templateConfig !== null) {
            return $templateConfig;
        }

        $eventTemplates = $group->get("templates.{$event}", []);
        if (is_array($eventTemplates) && isset($eventTemplates[$channel])) {
            return $eventTemplates[$channel];
        }

        return null;
    }

    /**
     * Instantiate a clean Template DTO from parsed configuration.
     */
    protected function buildTemplateFromConfig(NotificationEvent $event, string $channel, mixed $config): ?Template
    {
        $templateName = $event->requestedTemplate ?? "{$event->event}.{$channel}";

        if (is_string($config) && ! empty(trim($config))) {
            return new Template(
                name: $templateName,
                channel: $channel,
                content: trim($config)
            );
        }

        if (is_array($config)) {
            $content = $config['content'] ?? null;
            $view = isset($config['view']) ? (string) $config['view'] : null;

            if (empty($content) && empty($view)) {
                return null;
            }

            $subject = isset($config['subject']) ? (string) $config['subject'] : null;
            $variables = is_array($config['variables'] ?? null) ? $config['variables'] : [];

            return new Template(
                name: $templateName,
                channel: $channel,
                content: ! empty($content) ? (string) $content : ($view ? "@include('{$view}')" : ''),
                variables: array_merge($event->data, $variables),
                subject: $subject,
                view: $view
            );
        }

        return null;
    }
}
