<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Resolvers;

use SchoolPalm\MessageDelivery\Notification\Contracts\RecipientResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\MessageDelivery\Notification\Support\NotificationCollection;
use SchoolPalm\MessageDelivery\Notification\Support\RecipientCollection;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class BridgeRecipientResolver implements RecipientResolver
{
    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    public function resolve(NotificationEvent $event): NotificationCollection
    {
        $recipients = $event->data['recipients'] ?? [];

        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }

        return new NotificationCollection($recipients);
    }
}
