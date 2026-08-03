<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Facades\Host;

use Illuminate\Support\Facades\Facade;
use SchoolPalm\MessageDelivery\Builders\ChannelMessageBuilder;
use SchoolPalm\MessageDelivery\Builders\MultiChannelMessageBuilder;
use SchoolPalm\ModuleBridge\Adapters\MessageDeliveryAdapter;

/**
 * @method static MessageDeliveryAdapter forSchool(?string $schoolId = null)
 * @method static MessageDeliveryAdapter forTenant(?string $tenantId = null)
 * @method static MessageDeliveryAdapter withContext(array $context)
 * @method static ChannelMessageBuilder sms()
 * @method static ChannelMessageBuilder email()
 * @method static ChannelMessageBuilder push()
 * @method static ChannelMessageBuilder whatsapp()
 * @method static ChannelMessageBuilder inApp()
 * @method static MultiChannelMessageBuilder channels(array $channels)
 * 
 * @see \SchoolPalm\ModuleBridge\Adapters\MessageDeliveryAdapter
 */
class MessageHost extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'module-bridge.message-delivery';
    }
}
