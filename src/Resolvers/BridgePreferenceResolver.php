<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Resolvers;

use SchoolPalm\MessageDelivery\Notification\Contracts\PreferenceResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class BridgePreferenceResolver implements PreferenceResolver
{
    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    public function resolve(NotificationEvent $event): array
    {
        $userId   = $event->context['user_id'] ?? $this->contextResolver->userId();
        $schoolId = $event->context['school_id'] ?? $this->contextResolver->schoolId();
        $tenantId = $event->context['tenant_id'] ?? $this->contextResolver->tenantId();

        if (! $userId) {
            return ['channels' => ['email']];
        }

        $channels = SettingsHost::forTenant($tenantId)
            ->forSchool($schoolId)
            ->forUser($userId)
            ->group('notifications')
            ->get('channels', ['email']);

        return [
            'channels' => is_array($channels) ? $channels : [$channels],
        ];
    }
}
