<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Resolvers;

use SchoolPalm\MessageDelivery\Notification\Contracts\RetryResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\MessageDelivery\Notification\DTO\RetryPolicy;
use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class BridgeRetryResolver implements RetryResolver
{
    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    public function resolve(NotificationEvent $event): ?RetryPolicy
    {
        $schoolId = $event->context['school_id'] ?? $this->contextResolver->schoolId();
        $tenantId = $event->context['tenant_id'] ?? $this->contextResolver->tenantId();

        $settings = SettingsHost::forTenant($tenantId)
            ->forSchool($schoolId)
            ->group('notifications')
            ->get("retries.{$event->event}", []);

        if (! is_array($settings)) {
            $settings = ['max_attempts' => $settings];
        }

        $tries = isset($settings['max_attempts']) ? (int) $settings['max_attempts'] : null;
        $backoff = $settings['backoff'] ?? null;
        $timeout = isset($settings['timeout']) ? (int) $settings['timeout'] : null;
        $queue = isset($settings['queue']) ? (string) $settings['queue'] : null;
        $connection = isset($settings['connection']) ? (string) $settings['connection'] : null;

        if ($tries === null && $backoff === null && $timeout === null && $queue === null && $connection === null) {
            return null;
        }

        return new RetryPolicy(
            $tries,
            $timeout,
            $backoff,
            $queue,
            $connection
        );
    }
}
