<?php

declare(strict_types=1);

use SchoolPalm\MessageDelivery\Contracts\TenantProviderSettings;
use SchoolPalm\MessageDelivery\Models\DatabaseNotification;
use SchoolPalm\MessageDelivery\Notification\Contracts\ChannelResolver;
use SchoolPalm\MessageDelivery\Notification\Contracts\EventResolver;
use SchoolPalm\MessageDelivery\Notification\Contracts\LanguageResolver;
use SchoolPalm\MessageDelivery\Notification\Contracts\NotificationEngine;
use SchoolPalm\MessageDelivery\Notification\Contracts\PreferenceResolver;
use SchoolPalm\MessageDelivery\Notification\Contracts\PriorityResolver;
use SchoolPalm\MessageDelivery\Notification\Contracts\RecipientResolver;
use SchoolPalm\MessageDelivery\Notification\Contracts\RetryResolver;
use SchoolPalm\MessageDelivery\Notification\Contracts\ScheduleResolver;
use SchoolPalm\MessageDelivery\Notification\Contracts\TemplateResolver;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationEvent;
use SchoolPalm\MessageDelivery\Notification\DTO\RetryPolicy;
use SchoolPalm\MessageDelivery\Notification\Support\NotificationCollection;
use SchoolPalm\MessageDelivery\Templates\Template;
use SchoolPalm\ModuleBridge\Resolvers\BridgeChannelResolver;
use SchoolPalm\ModuleBridge\Resolvers\BridgeEventResolver;
use SchoolPalm\ModuleBridge\Resolvers\BridgeLanguageResolver;
use SchoolPalm\ModuleBridge\Resolvers\BridgePreferenceResolver;
use SchoolPalm\ModuleBridge\Resolvers\BridgePriorityResolver;
use SchoolPalm\ModuleBridge\Resolvers\BridgeRecipientResolver;
use SchoolPalm\ModuleBridge\Resolvers\BridgeRetryResolver;
use SchoolPalm\ModuleBridge\Resolvers\BridgeScheduleResolver;
use SchoolPalm\ModuleBridge\Resolvers\BridgeTemplateResolver;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

/*
|--------------------------------------------------------------------------
| Test Setup
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    // Guard: the email channel uses Mailpit (SMTP) so Mailpit must be
    // running on 127.0.0.1:1025 before any email tests execute.
    $sock = @fsockopen('127.0.0.1', 1025, $errno, $errstr, 2);

    if ($sock === false) {
        throw new RuntimeException(
            'Mailpit SMTP server is not available on 127.0.0.1:1025. '
                . 'Start Mailpit before running integration tests:'
                . PHP_EOL . '  mailpit'
        );
    }

    fclose($sock);

    $this->app->bind(
        TenantProviderSettings::class,
        fn(): TenantProviderSettings => new class implements TenantProviderSettings
        {
            public function providerFor(string $channel): ?string
            {
                return match ($channel) {
                    'in_app' => 'database-notifications',
                    'email' => 'laravel-mail',
                    default => null,
                };
            }

            public function configurationFor(string $channel, string $provider): array
            {
                return match ($provider) {
                    'database-notifications' => [],
                    'laravel-mail' => ['mailer' => 'mailpit'],
                    default => [],
                };
            }

            public function enabled(string $channel, string $provider): bool
            {
                return true;
            }
        }
    );

    // Default recipient resolver returns a single in-app recipient.
    $this->app->bind(
        RecipientResolver::class,
        fn(): RecipientResolver => new class implements RecipientResolver
        {
            public function resolve(NotificationEvent $event): NotificationCollection
            {
                return new NotificationCollection([
                    ['notifiable_type' => 'App\Models\User', 'notifiable_id' => 1],
                ]);
            }
        }
    );

    // Default channel resolver routes to in_app so notifications
    // can be asserted against the database.
    $this->app->bind(
        ChannelResolver::class,
        fn(): ChannelResolver => new class implements ChannelResolver
        {
            public function resolve(NotificationEvent $event, array $preferences = []): array
            {
                return $event->requestedChannels ?: ['in_app'];
            }
        }
    );
});

/*
|--------------------------------------------------------------------------
| BridgeRecipientResolver
|--------------------------------------------------------------------------
*/

it('BridgeRecipientResolver keeps Eloquent model recipients intact', function (): void {
    $resolver = app(BridgeRecipientResolver::class);

    $user = new class(['id' => 42]) extends \Illuminate\Database\Eloquent\Model {
        protected $table = 'users';

        protected $fillable = ['id'];
    };

    $collection = $resolver->resolve(
        new NotificationEvent(event: 'test.event', data: ['recipients' => [$user]])
    );

    expect($collection->all())->toHaveCount(1)
        ->and($collection->first())->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class);
});

it('BridgeRecipientResolver falls back to raw strings when no user is found', function (): void {
    \Illuminate\Support\Facades\Schema::create('users', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
        $table->timestamps();
    });

    $resolver = app(BridgeRecipientResolver::class);

    $collection = $resolver->resolve(
        new NotificationEvent(event: 'test.event', data: ['recipients' => ['unknown@example.com']])
    );

    expect($collection->all())->toHaveCount(1)
        ->and($collection->first())->toBe('unknown@example.com');
});

it('BridgeRecipientResolver returns empty collection when no recipients', function (): void {
    $resolver = app(BridgeRecipientResolver::class);

    $collection = $resolver->resolve(
        new NotificationEvent(event: 'test.event', data: [])
    );

    expect($collection->all())->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| BridgeEventResolver
|--------------------------------------------------------------------------
*/

it('BridgeEventResolver enriches event metadata with context ids', function (): void {
    $resolver = app(BridgeEventResolver::class);

    $metadata = $resolver->resolve(
        new NotificationEvent(event: 'test.event', context: [
            'tenant_id' => 'tenant-1',
            'school_id' => 'school-1',
            'user_id'   => 'user-1',
        ])
    );

    expect($metadata)->toMatchArray([
        'tenant_id' => 'tenant-1',
        'school_id' => 'school-1',
        'user_id'   => 'user-1',
    ]);
});

/*
|--------------------------------------------------------------------------
| BridgeChannelResolver
|--------------------------------------------------------------------------
*/

it('BridgeChannelResolver defaults to in_app when nothing is configured', function (): void {
    $resolver = app(BridgeChannelResolver::class);

    $channels = $resolver->resolve(
        new NotificationEvent(event: 'test.event')
    );

    expect($channels)->toBe(['in_app']);
});

it('BridgeChannelResolver uses requested channels when provided', function (): void {
    $resolver = app(BridgeChannelResolver::class);

    $channels = $resolver->resolve(
        new NotificationEvent(event: 'test.event', requestedChannels: ['in_app', 'email'])
    );

    expect($channels)->toContain('in_app')
        ->and($channels)->toContain('email');
});

/*
|--------------------------------------------------------------------------
| BridgePreferenceResolver
|--------------------------------------------------------------------------
*/

it('BridgePreferenceResolver defaults to in_app channels without a user', function (): void {
    $resolver = app(BridgePreferenceResolver::class);

    $preferences = $resolver->resolve(
        new NotificationEvent(event: 'test.event', context: [])
    );

    expect($preferences)->toMatchArray(['channels' => ['in_app']]);
});

/*
|--------------------------------------------------------------------------
| BridgeLanguageResolver
|--------------------------------------------------------------------------
*/

it('BridgeLanguageResolver uses requested language when provided', function (): void {
    $resolver = app(BridgeLanguageResolver::class);

    $language = $resolver->resolve(
        new NotificationEvent(event: 'test.event', requestedLanguage: 'fr')
    );

    expect($language)->toBe('fr');
});

/*
|--------------------------------------------------------------------------
| BridgePriorityResolver
|--------------------------------------------------------------------------
*/

it('BridgePriorityResolver uses requested priority when provided', function (): void {
    $resolver = app(BridgePriorityResolver::class);

    $priority = $resolver->resolve(
        new NotificationEvent(event: 'test.event', requestedPriority: 'high')
    );

    expect($priority)->toBe('high');
});

/*
|--------------------------------------------------------------------------
| BridgeScheduleResolver
|--------------------------------------------------------------------------
*/

it('BridgeScheduleResolver returns null when no schedule is set', function (): void {
    $resolver = app(BridgeScheduleResolver::class);

    $schedule = $resolver->resolve(
        new NotificationEvent(event: 'test.event')
    );

    expect($schedule)->toBeNull();
});

it('BridgeScheduleResolver parses a string date into a DateTime', function (): void {
    $resolver = app(BridgeScheduleResolver::class);

    $schedule = $resolver->resolve(
        new NotificationEvent(event: 'test.event', data: ['scheduled_at' => '2026-01-01 10:00:00'])
    );

    expect($schedule)
        ->toBeInstanceOf(\DateTimeInterface::class)
        ->and($schedule->format('Y-m-d'))->toBe('2026-01-01');
});

/*
|--------------------------------------------------------------------------
| BridgeRetryResolver
|--------------------------------------------------------------------------
*/

it('BridgeRetryResolver returns a RetryPolicy when configured', function (): void {
    \SchoolPalm\ModuleBridge\Facades\Host\SettingsHost::forTenant('tenant_demo_001')
        ->forSchool('school-1')
        ->group('notifications')
        ->set('retries.retry.event', ['max_attempts' => 3, 'timeout' => 60]);

    $resolver = app(BridgeRetryResolver::class);

    $policy = $resolver->resolve(
        new NotificationEvent(event: 'retry.event', context: [
            'tenant_id' => 'tenant_demo_001',
            'school_id' => 'school-1',
        ])
    );

    expect($policy)->toBeInstanceOf(RetryPolicy::class)
        ->and($policy->tries)->toBe(3)
        ->and($policy->timeout)->toBe(60);
});

it('BridgeRetryResolver returns null when no retry configured', function (): void {
    $resolver = app(BridgeRetryResolver::class);

    $policy = $resolver->resolve(
        new NotificationEvent(event: 'retry.none', context: [])
    );

    expect($policy)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| BridgeTemplateResolver
|--------------------------------------------------------------------------
*/

it('BridgeTemplateResolver returns null when no template is configured', function (): void {
    $resolver = app(BridgeTemplateResolver::class);

    $template = $resolver->resolve(
        new NotificationEvent(event: 'no.template', context: [])
    );

    expect($template)->toBeNull();
});

it('BridgeTemplateResolver returns a Template from settings', function (): void {
    \SchoolPalm\ModuleBridge\Facades\Host\SettingsHost::forTenant('tenant_demo_001')
        ->forSchool('school-1')
        ->group('notifications')
        ->set('templates.welcome.email', 'Hello {{ name }}!');

    $resolver = app(BridgeTemplateResolver::class);

    $template = $resolver->resolve(
        new NotificationEvent(event: 'welcome', context: [
            'tenant_id' => 'tenant_demo_001',
            'school_id' => 'school-1',
        ]),
        channels: ['email'],
        language: 'en',
    );

    expect($template)->toBeInstanceOf(Template::class)
        ->and($template->content)->toBe('Hello {{ name }}!');
});

/*
|--------------------------------------------------------------------------
| End-to-End: resolvers wired through the engine
|--------------------------------------------------------------------------
*/

it('engine dispatches in-app notification using the bridge resolvers', function (): void {
    $this->app->bind(RecipientResolver::class, BridgeRecipientResolver::class);
    $this->app->bind(ChannelResolver::class, BridgeChannelResolver::class);
    $this->app->bind(EventResolver::class, BridgeEventResolver::class);
    $this->app->bind(PreferenceResolver::class, BridgePreferenceResolver::class);
    $this->app->bind(LanguageResolver::class, BridgeLanguageResolver::class);
    $this->app->bind(PriorityResolver::class, BridgePriorityResolver::class);
    $this->app->bind(ScheduleResolver::class, BridgeScheduleResolver::class);
    $this->app->bind(RetryResolver::class, BridgeRetryResolver::class);
    $this->app->bind(TemplateResolver::class, BridgeTemplateResolver::class);

    $result = app(NotificationEngine::class)->dispatch(
        new NotificationEvent(
            event: 'bridge.e2e',
            data: ['title' => 'Bridge Works'],
            context: ['user_id' => 'user-1'],
        )
    );

    expect($result)->wasDispatched()->toBeTrue();

    $notification = DatabaseNotification::first();

    expect($notification)->not->toBeNull()
        ->and($notification->title)->toBe('Bridge Works')
        ->and($notification->channel)->toBe('in_app')
        ->and($notification->provider)->toBe('database-notifications');
});
