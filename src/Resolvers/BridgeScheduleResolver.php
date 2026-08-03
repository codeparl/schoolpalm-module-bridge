<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Resolvers;

use DateInterval;
use DateTimeInterface;
use SchoolPalm\MessageDelivery\Notification\Contracts\ScheduleResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class BridgeScheduleResolver implements ScheduleResolver
{
    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    public function resolve(NotificationEvent $event): DateInterval|DateTimeInterface|int|null
    {
        $scheduledAt = $event->data['scheduled_at'] ?? null;

        if ($scheduledAt instanceof DateTimeInterface || $scheduledAt instanceof DateInterval || is_int($scheduledAt)) {
            return $scheduledAt;
        }

        if (is_string($scheduledAt) && trim($scheduledAt) !== '') {
            try {
                return new \DateTimeImmutable($scheduledAt);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
