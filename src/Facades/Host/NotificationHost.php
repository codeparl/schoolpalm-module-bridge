<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Facades\Host;

use Illuminate\Support\Facades\Facade;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationDispatch;
use SchoolPalm\MessageDelivery\Notification\Support\NotificationResult;
use SchoolPalm\MessageDelivery\Context\MessageContext;
use SchoolPalm\ModuleBridge\Adapters\NotificationAdapter;

/**
 * @method static NotificationAdapter forSchool(?string $schoolId = null)
 * @method static NotificationAdapter forTenant(?string $tenantId = null)
 * @method static NotificationAdapter withContext(array|MessageContext $context)
 * @method static NotificationAdapter withAutoScope()
 * @method static NotificationDispatch event(string $event)
 * @method static NotificationResult dispatch(string $event, array $data = [], array $context = [], array $metadata = [], array $channels = [], ?string $language = null, ?string $priority = null, ?string $template = null, mixed $recipients = null)
 * @method static bool notify(string $event, array $data = [], array $context = [], array $metadata = [], array $channels = [], ?string $language = null, ?string $priority = null, ?string $template = null, mixed $recipients = null)
 *
 * @see NotificationAdapter
 */
class NotificationHost extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return NotificationAdapter::class;
    }
}
