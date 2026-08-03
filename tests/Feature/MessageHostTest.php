<?php

declare(strict_types=1);

use SchoolPalm\MessageDelivery\Builders\ChannelMessageBuilder;
use SchoolPalm\MessageDelivery\Builders\MultiChannelMessageBuilder;
use SchoolPalm\MessageDelivery\MessageDelivery;
use SchoolPalm\ModuleBridge\Facades\Host\MessageHost;

/**
 * Helper to inspect context from builder's build() method via Reflection.
 */
function getMessageContext(ChannelMessageBuilder $builder): array
{
    $reflection = new ReflectionClass($builder);
    $buildMethod = $reflection->getMethod('build');
    $buildMethod->setAccessible(true);

    /** @var \SchoolPalm\MessageDelivery\Messages\Message $message */
    $message = $buildMethod->invoke($builder);

    return $message->context ?? [];
}

it('automatically attaches ambient context when calling single channel builders', function () {
    $smsBuilder = MessageHost::sms();

    expect($smsBuilder)->toBeInstanceOf(ChannelMessageBuilder::class);

    $context = getMessageContext($smsBuilder);

    expect($context)->toMatchArray([
        'tenant_id' => 'tenant_demo_001',
        'school_id' => 'SDK-001',
    ]);
});

it('allows explicit school context overriding while retaining ambient tenant context', function () {
    $emailBuilder = MessageHost::forSchool('school-999')->email();

    $context = getMessageContext($emailBuilder);

    expect($context['school_id'])->toBe('school-999');
    expect($context['tenant_id'])->toBe('tenant_demo_001');
});

it('allows explicit tenant context overriding', function () {
    $whatsappBuilder = MessageHost::forTenant('tenant-2026')->whatsapp();

    $context = getMessageContext($whatsappBuilder);

    expect($context['tenant_id'])->toBe('tenant-2026');
});

it('merges custom context using withContext method while retaining ambient context', function () {
    $pushBuilder = MessageHost::withContext([
        'user_id' => 'user-404',
        'action'  => 'fee_payment_reminder',
    ])->push();

    $context = getMessageContext($pushBuilder);

    expect($context)->toMatchArray([
        'tenant_id' => 'tenant_demo_001',
        'school_id' => 'SDK-001',
        'user_id'   => 'user-404',
        'action'    => 'fee_payment_reminder',
    ]);
});



it('creates multi channel builders with resolved context', function () {
    $multiBuilder = MessageHost::channels(['sms', 'email', 'in_app']);

    expect($multiBuilder)->toBeInstanceOf(MultiChannelMessageBuilder::class);

    $reflection = new ReflectionClass($multiBuilder);
    $property = $reflection->getProperty('context');
    $context = $property->getValue($multiBuilder);

    expect($context)->toMatchArray([
        'tenant_id' => 'tenant_demo_001',
        'school_id' => 'SDK-001',
    ]);
});

it('ensures adapter methods return immutable clones without leaking state', function () {
    $scopedBuilder = MessageHost::forSchool('school-888')->email();
    $defaultBuilder = MessageHost::email();

    expect(getMessageContext($scopedBuilder)['school_id'])->toBe('school-888');
    expect(getMessageContext($defaultBuilder)['school_id'])->toBe('SDK-001');
});
