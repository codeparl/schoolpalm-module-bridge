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

    /**
     * Resolve the queue retry policy, timeouts, and queue connection for a notification event.
     */
    public function resolve(NotificationEvent $event): ?RetryPolicy
    {
        // 1. Gather explicit metadata configuration if passed via dispatch builder
        $explicit = $this->extractExplicitConfig($event);

        // 2. Gather event-specific settings (e.g. notifications.retries.student.admitted)
        $eventSettings = SettingsHost::group('notifications')
            ->get("retries.{$event->event}", []);

        if (! is_array($eventSettings)) {
            $eventSettings = ['max_attempts' => $eventSettings];
        }

        // 3. Gather system-wide default settings (e.g. notifications.default_retry)
        $defaultSettings = SettingsHost::group('notifications')
            ->get('default_retry', []);

        if (! is_array($defaultSettings)) {
            $defaultSettings = [];
        }

        // 4. Resolve parameters in order of strict precedence: Explicit -> Event Setting -> Global Setting -> Config Fallback
        $tries = $explicit['tries']
            ?? $this->intOrNull($eventSettings['max_attempts'] ?? $eventSettings['tries'] ?? null)
            ?? $this->intOrNull($defaultSettings['max_attempts'] ?? $defaultSettings['tries'] ?? null)
            ?? config('message-delivery.queue.tries', 3);

        $timeout = $explicit['timeout']
            ?? $this->intOrNull($eventSettings['timeout'] ?? null)
            ?? $this->intOrNull($defaultSettings['timeout'] ?? null)
            ?? config('message-delivery.queue.timeout', 60);

        $backoff = $explicit['backoff']
            ?? $eventSettings['backoff']
            ?? $defaultSettings['backoff']
            ?? config('message-delivery.queue.backoff', [5, 15, 30]);

        $queue = $explicit['queue']
            ?? $this->stringOrNull($eventSettings['queue'] ?? null)
            ?? $this->stringOrNull($defaultSettings['queue'] ?? null)
            ?? config('message-delivery.queue.default_queue', 'notifications');

        $connection = $explicit['connection']
            ?? $this->stringOrNull($eventSettings['connection'] ?? null)
            ?? $this->stringOrNull($defaultSettings['connection'] ?? null)
            ?? config('message-delivery.queue.default_connection');

        return new RetryPolicy(
            tries: $tries,
            timeout: $timeout,
            backoff: $backoff,
            queue: $queue,
            connection: $connection
        );
    }

    /**
     * Extract explicit retry and queue configuration from event metadata.
     *
     * @return array<string, mixed>
     */
    protected function extractExplicitConfig(NotificationEvent $event): array
    {
        $meta = $event->metadata;
        $retryMeta = $meta['retry'] ?? $meta['retries'] ?? [];

        if (! is_array($retryMeta)) {
            $retryMeta = ['tries' => $retryMeta];
        }

        return [
            'tries'      => $this->intOrNull($retryMeta['tries'] ?? $retryMeta['max_attempts'] ?? $meta['tries'] ?? null),
            'timeout'    => $this->intOrNull($retryMeta['timeout'] ?? $meta['timeout'] ?? null),
            'backoff'    => $retryMeta['backoff'] ?? $meta['backoff'] ?? null,
            'queue'      => $this->stringOrNull($retryMeta['queue'] ?? $meta['queue'] ?? null),
            'connection' => $this->stringOrNull($retryMeta['connection'] ?? $meta['connection'] ?? null),
        ];
    }

    protected function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    protected function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && ! empty(trim($value)) ? trim($value) : null;
    }
}
