<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Resolvers;

use SchoolPalm\MessageDelivery\Notification\Contracts\EventResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

class BridgeEventResolver implements EventResolver
{
    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    public function resolve(NotificationEvent $event): array
    {
        return [
            'tenant_id' => $this->contextResolver->tenantId(),
            'school_id' => $this->contextResolver->schoolId(),
            'user_id'   => $this->contextResolver->userId(),
            'module'    => $this->contextResolver->currentModule(),
        ];
    }
}
