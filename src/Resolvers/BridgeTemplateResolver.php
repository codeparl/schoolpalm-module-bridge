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
        $schoolId = $event->context['school_id'] ?? $this->contextResolver->schoolId();
        $tenantId = $event->context['tenant_id'] ?? $this->contextResolver->tenantId();

        $templates = SettingsHost::forTenant($tenantId)
            ->forSchool($schoolId)
            ->group('notifications')
            ->get("templates.{$event->event}", []);

        if (! is_array($templates)) {
            return null;
        }

        $channels = array_values(array_filter($channels ?: $event->requestedChannels));

        foreach ($channels as $channel) {
            if (! isset($templates[$channel])) {
                continue;
            }

            $templateConfig = $templates[$channel];

            if ($language !== null && is_array($templateConfig) && isset($templateConfig[$language])) {
                $templateConfig = $templateConfig[$language];
            }

            if (is_string($templateConfig) && $templateConfig !== '') {
                return new Template($event->requestedTemplate ?? "{$event->event}.{$channel}", $channel, $templateConfig);
            }

            if (is_array($templateConfig)) {
                $content = $templateConfig['content'] ?? null;

                if (! is_string($content) || $content === '') {
                    continue;
                }

                $subject = isset($templateConfig['subject']) ? (string) $templateConfig['subject'] : null;
                $variables = is_array($templateConfig['variables']) ? $templateConfig['variables'] : [];

                return new Template(
                    $event->requestedTemplate ?? "{$event->event}.{$channel}",
                    $channel,
                    $content,
                    $variables,
                    $subject
                );
            }
        }

        return null;
    }
}
