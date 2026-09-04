<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationDispatch;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\MessageDelivery\Notification\Support\NotificationResult;
use SchoolPalm\ModuleBridge\Adapters\NotificationAdapter;
use SchoolPalm\ModuleBridge\Facades\Host\NotificationHost;
use SchoolPalm\ModuleBridge\Resolvers\BridgeRecipientResolver;

class ResolverTestUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public function routeNotificationForMail($notification = null): string
    {
        return $this->email;
    }
}

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
        'school_id' => '1',
        'user_id'   => 'user-123',
        'action'    => 'payment_reminder',
    ]);
});

it('injects explicit recipients into the dispatch data', function () {
    $dispatch = NotificationHost::forSchool('school-999')->event('test.event')
        ->data(['recipients' => ['user-1', 'user-2']]);

    expect($dispatch)->toBeInstanceOf(NotificationDispatch::class);

    $event = $dispatch->buildEvent();
    expect($event->data['recipients'])->toBe(['user-1', 'user-2']);
});

it('dispatches immediately with explicit recipients via dispatch()', function () {
    $result = NotificationHost::forSchool('school-999')->dispatch(
        event: 'test.event',
        data: ['amount' => 5000],
        recipients: ['user-1', 'user-2'],
    );

    expect($result)->toBeInstanceOf(NotificationResult::class);
    expect($result->status)->toBeIn(['dispatched', 'skipped', 'failed']);
});

it('provides a notify() convenience helper returning a boolean', function () {
    $wasDispatched = NotificationHost::forSchool('school-999')->notify(
        event: 'test.event',
        data: ['amount' => 5000],
        recipients: ['user-1'],
    );

    expect($wasDispatched)->toBeBool();
});

/*
|--------------------------------------------------------------------------
| BridgeRecipientResolver: polymorphic recipients
|--------------------------------------------------------------------------
*/

it('passes an eloquent model recipient through unchanged', function () {
    $user = new ResolverTestUser(['id' => 42, 'email' => 'model@example.com']);

    $resolver = new BridgeRecipientResolver(
        app(\SchoolPalm\ModuleBridge\Services\ContextResolver::class)
    );

    $collection = $resolver->resolve(
        new NotificationEvent(event: 'test.event', data: ['recipients' => [$user]])
    );

    $resolved = $collection->all();

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0])->toBeInstanceOf(Model::class)
        ->and($resolved[0])->toBe($user);
});

it('resolves a plain email string to the configured auth model', function () {
    \Illuminate\Support\Facades\Schema::create('users', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
        $table->timestamps();
    });

    config([
        'auth.providers.users.model' => ResolverTestUser::class,
    ]);

    $user = ResolverTestUser::query()->create([
        'id' => 1,
        'email' => 'parent@example.com',
    ]);

    $resolver = new BridgeRecipientResolver(
        app(\SchoolPalm\ModuleBridge\Services\ContextResolver::class)
    );

    $collection = $resolver->resolve(
        new NotificationEvent(event: 'fee.reminder', data: ['recipients' => ['parent@example.com']])
    );

    $resolved = $collection->all();

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0])->toBeInstanceOf(ResolverTestUser::class)
        ->and($resolved[0]->email)->toBe('parent@example.com');
});

it('falls back to the raw string recipient when no user is found', function () {
    \Illuminate\Support\Facades\Schema::create('users', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
        $table->timestamps();
    });

    config([
        'auth.providers.users.model' => ResolverTestUser::class,
    ]);

    $resolver = new BridgeRecipientResolver(
        app(\SchoolPalm\ModuleBridge\Services\ContextResolver::class)
    );

    $collection = $resolver->resolve(
        new NotificationEvent(event: 'fee.reminder', data: ['recipients' => ['unknown@example.com']])
    );

    $resolved = $collection->all();

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0])->toBe('unknown@example.com');
});
