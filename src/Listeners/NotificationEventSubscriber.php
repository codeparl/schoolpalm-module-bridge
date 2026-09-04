<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Listeners;

use Illuminate\Events\Dispatcher;
use SchoolPalm\MessageDelivery\Notification\Contracts\NotificationEngine;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;

class NotificationEventSubscriber
{
    public function __construct(
        protected NotificationEngine $notificationEngine
    ) {}

    /**
     * Handle any application event and forward it to the notification engine.
     */
    public function handleAllEvents(string $eventName, array $data): void
    {
        // Optional: Filter out internal framework or package events to prevent loops
        if (str_starts_with($eventName, 'eloquent.') || str_starts_with($eventName, 'illuminate.')) {
            return;
        }

        // Extract the primary payload (e.g. Model or data array) from the event arguments
        $payload = $data[0] ?? [];

        // Build the notification event DTO
        $event = new NotificationEvent(
            event: $eventName,
            data: is_array($payload) ? $payload : ['model' => $payload, 'recipients' => $payload->recipients ?? []]
        );

        // Dispatch through your package's core engine
        $this->notificationEngine->dispatch($event);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            '*' => 'handleAllEvents',
        ];
    }
}
