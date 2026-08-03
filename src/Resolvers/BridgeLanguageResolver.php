<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Resolvers;

use SchoolPalm\MessageDelivery\Notification\Contracts\LanguageResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class BridgeLanguageResolver implements LanguageResolver
{
    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    public function resolve(NotificationEvent $event): ?string
    {
        if ($event->requestedLanguage !== null) {
            return $event->requestedLanguage;
        }

        $userId   = $event->context['user_id'] ?? $this->contextResolver->userId();
        $schoolId = $event->context['school_id'] ?? $this->contextResolver->schoolId();
        $tenantId = $event->context['tenant_id'] ?? $this->contextResolver->tenantId();

        $builder = SettingsHost::forTenant($tenantId)->forSchool($schoolId);

        if ($userId) {
            $builder->forUser($userId);
        }

        return $builder->group('localization')->get('locale', 'en');
    }
}
