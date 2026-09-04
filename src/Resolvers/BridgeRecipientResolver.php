<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Resolvers;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use SchoolPalm\MessageDelivery\Notification\Contracts\RecipientResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\MessageDelivery\Notification\Support\NotificationCollection;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class BridgeRecipientResolver implements RecipientResolver
{
    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    /**
     * Resolve raw recipients into actionable objects, models, DTOs, Notifiables, or route definitions.
     */
    public function resolve(NotificationEvent $event): NotificationCollection
    {
        $rawRecipients = $event->data['recipients'] ?? [];
        $routes = $event->data['routes'] ?? [];
        $recipientKey = $event->data['recipient_key'] ?? null;
        $targetModel = $event->data['target_model'] ?? null;

        if (! is_array($rawRecipients)) {
            $rawRecipients = array_filter([$rawRecipients]);
        }

        $resolved = [];

        // Case A: Explicit Notification Direct Target Routes (e.g., ->route('email', 'user@example.com'))
        if (! empty($routes) && is_array($routes)) {
            foreach ($routes as $channel => $target) {
                // If the route target is a direct destination address (e.g. valid email, phone number, URL) or object
                if ($this->isDirectTarget($target)) {
                    $resolved[] = [
                        'channel' => $channel,
                        'target' => $target,
                    ];
                }
            }
        }

        foreach ($rawRecipients as $recipient) {
            // Case B: Passed as an Object (User model, Student, DTO, Notifiable)
            if (is_object($recipient)) {
                $resolved[] = $this->applyRouteMappings($recipient, $routes);
                continue;
            }

            // Case C: Passed as an Associative Array (e.g. ['email' => '...', 'name' => '...'])
            if (is_array($recipient) && ! empty($recipient)) {
                $resolved[] = $recipient;
                continue;
            }

            // Case D: Passed as Identifier, Email, Phone, or Target String/Integer
            if (is_string($recipient) || is_int($recipient)) {
                $modelClass = $targetModel ?? config('auth.providers.users.model');
                $modelInstance = $this->findEntityModel($modelClass, $recipient, $recipientKey);

                $resolvedItem = $modelInstance !== null
                    ? $this->applyRouteMappings($modelInstance, $routes)
                    : $recipient;

                if ($resolvedItem !== null && $resolvedItem !== '') {
                    $resolved[] = $resolvedItem;
                }
            }
        }

        return new NotificationCollection($resolved);
    }

    /**
     * Apply configured custom routes or property overrides to resolved models/objects.
     *
     * @param array<string, mixed> $routes
     */
    protected function applyRouteMappings(object $recipient, array $routes): object
    {
        if (empty($routes)) {
            return $recipient;
        }

        // Attach custom route map to model instance dynamically if unsupported natively
        if (method_exists($recipient, 'setNotificationRoutes')) {
            $recipient->setNotificationRoutes($routes);
        } elseif (property_exists($recipient, 'notificationRoutes')) {
            $recipient->notificationRoutes = array_merge($recipient->notificationRoutes ?? [], $routes);
        }

        return $recipient;
    }

    /**
     * Determine whether a route target is a direct target address vs a model property reference.
     */
    protected function isDirectTarget(mixed $target): bool
    {
        if (is_object($target)) {
            return true;
        }

        if (is_array($target)) {
            return true;
        }

        if (is_string($target)) {
            // Valid email address
            if (filter_var($target, FILTER_VALIDATE_EMAIL)) {
                return true;
            }

            // Valid phone number
            if (preg_match('/^\+?[0-9]{7,15}$/', $target)) {
                return true;
            }

            // Webhook URL or endpoint
            if (filter_var($target, FILTER_VALIDATE_URL)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if an object is a Laravel Notifiable instance or implements notification routing.
     */
    protected function isNotifiable(object $object): bool
    {
        if ($object instanceof AnonymousNotifiable) {
            return true;
        }

        $traits = class_uses_recursive($object);

        return isset($traits[Notifiable::class]) || method_exists($object, 'routeNotificationFor');
    }

    /**
     * Look up target model/entity via explicit key, primary ID, UUID, Email, or Phone.
     */
    protected function findEntityModel(?string $modelClass, string|int $recipient, ?string $recipientKey = null): ?object
    {
        if (empty($modelClass) || ! class_exists($modelClass)) {
            return null;
        }

        /** @var mixed $query */
        $query = $modelClass::query();

        // 1. Explicit lookup key specified via proxy (e.g., ->usingKey('admission_number'))
        if ($recipientKey !== null) {
            return $query->where($recipientKey, $recipient)->first();
        }

        // 2. Look up by Primary Key ID or UUID
        if (is_int($recipient) || (is_string($recipient) && Str::isUuid($recipient))) {
            return $query->find($recipient);
        }

        // 3. Look up by Email address
        if (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return $query->where('email', $recipient)->first();
        }

        // 4. Look up by Phone Number
        if (is_string($recipient) && preg_match('/^\+?[0-9]{7,15}$/', $recipient)) {
            return $query->where('phone_number', $recipient)
                ->orWhere('phone', $recipient)
                ->first();
        }

        // 5. Fallback numeric lookup (for numeric IDs passed as strings)
        if (is_numeric($recipient)) {
            return $query->find($recipient);
        }

        return null;
    }
}
