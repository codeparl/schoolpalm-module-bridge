<?php

declare(strict_types=1);

use SchoolPalm\MessageDelivery\Notification\DTO\NotificationDispatch;
use SchoolPalm\ModuleBridge\Adapters\NotificationAdapter;
use SchoolPalm\ModuleBridge\Facades\Host\NotificationHost;

it('resolves the notification adapter through the NotificationHost facade', function () {
    expect(NotificationHost::forSchool('school-999'))->toBeInstanceOf(NotificationAdapter::class);
});

it('creates a notification dispatch with auto-scoped context', function () {
    $dispatch = NotificationHost::forSchool('school-999')->event('test.event');

    expect($dispatch)->toBeInstanceOf(NotificationDispatch::class);

    $event = $dispatch->buildEvent();
    expect($event->toArray()['context'])->toMatchArray([
        'tenant_id' => 'tenant_demo_001',
        'school_id' => 'school-999',
    ]);
});

it('merges explicit context with ambient context while using NotificationHost', function () {
    $dispatch = NotificationHost::withContext([
        'user_id' => 'user-123',
        'action'  => 'payment_reminder',
    ])->event('test.event');

    expect($dispatch)->toBeInstanceOf(NotificationDispatch::class);

    $event = $dispatch->buildEvent();
    expect($event->toArray()['context'])->toMatchArray([
        'tenant_id' => 'tenant_demo_001',
        'school_id' => 'SDK-001',
        'user_id'   => 'user-123',
        'action'    => 'payment_reminder',
    ]);
});
