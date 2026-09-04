<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Resolvers;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use SchoolPalm\MessageDelivery\Notification\Contracts\ScheduleResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;
use SchoolPalm\ModuleBridge\Services\ContextResolver;
use Throwable;

class BridgeScheduleResolver implements ScheduleResolver
{
    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    /**
     * Resolve the delayed schedule or execution timestamp for a notification event.
     */
    public function resolve(NotificationEvent $event): DateInterval|DateTimeInterface|int|null
    {
        // 1. Explicit metadata takes precedence (set via ->schedule() or ->delay() on dispatch builder)
        $explicit = $event->metadata['scheduled_at'] ?? $event->data['scheduled_at'] ?? null;

        if ($explicit !== null) {
            $parsed = $this->parseScheduleValue($explicit);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        // 2. Check for configured default delay settings per event
        $configuredDelay = SettingsHost::group('notifications')
            ->get("schedule_delay.{$event->event}");

        if ($configuredDelay !== null) {
            return $this->parseScheduleValue($configuredDelay);
        }

        // 3. Fall back to immediate execution
        return null;
    }

    /**
     * Parse and normalize mixed schedule inputs.
     */
    protected function parseScheduleValue(mixed $value): DateInterval|DateTimeInterface|int|null
    {
        if ($value instanceof DateTimeInterface || $value instanceof DateInterval || is_int($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);

        // Parse numeric strings (timestamp or seconds delay)
        if (is_numeric($trimmed)) {
            return (int) $trimmed;
        }

        // Parse ISO 8601 duration strings (e.g., 'PT15M')
        if (str_starts_with($trimmed, 'P')) {
            try {
                return new DateInterval($trimmed);
            } catch (Throwable) {
                // Fall through to DateTimeImmutable
            }
        }

        // Parse relative or absolute date-time strings (e.g., '+10 minutes', '2026-08-10 12:00:00')
        try {
            return new DateTimeImmutable($trimmed);
        } catch (Throwable) {
            return null;
        }
    }
}
