<?php

namespace Tests;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase as Orchestra;
use SchoolPalm\AppLogger\AppLoggerServiceProvider;
use SchoolPalm\AppSettings\Providers\AppSettingsServiceProvider;
use SchoolPalm\CacheStore\Providers\CacheStoreServiceProvider;
use SchoolPalm\MessageDelivery\MessageDeliveryServiceProvider;
use SchoolPalm\ModuleBridge\Providers\ModuleBridgeServiceProvider;
use SchoolPalm\QueuedJobs\Providers\QueuedJobsServiceProvider;
use UnnovateBrains\DocumentBuilder\DocumentBuilderServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Package Service Providers
            |--------------------------------------------------------------------------
            */
            AppLoggerServiceProvider::class,
            AppSettingsServiceProvider::class,
            DocumentBuilderServiceProvider::class,
            // Register ModuleBridge BEFORE CacheStore so CacheContextResolver is bound
            ModuleBridgeServiceProvider::class,
            CacheStoreServiceProvider::class,
            MessageDeliveryServiceProvider::class,
            QueuedJobsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        /*
        |--------------------------------------------------------------------------
        | Application & Environment
        |--------------------------------------------------------------------------
        */
        Config::set(
            'app.key',
            'base64:' . base64_encode(random_bytes(32))
        );

        /*
        |--------------------------------------------------------------------------
        | Database (Testbench In-Memory SQLite)
        |--------------------------------------------------------------------------
        */
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Filesystem
        |--------------------------------------------------------------------------
        */
        Config::set(
            'filesystems.default',
            'local'
        );

        Config::set(
            'filesystems.disks.local.root',
            __DIR__ . '/../workbench/storage/app'
        );

        /*
        |--------------------------------------------------------------------------
        | App Logger
        |--------------------------------------------------------------------------
        */
        $app['config']->set(
            'app-logger.driver',
            'file'
        );

        Config::set(
            'filesystems.disks.app-logger',
            [
                'driver' => 'local',
                'root'   => __DIR__ . '/../workbench/storage/logs',
            ]
        );

        $app['config']->set(
            'app-logger.database_connection',
            null
        );

        /*
        |--------------------------------------------------------------------------
        | App Settings (Database Driver Configuration)
        |--------------------------------------------------------------------------
        */
        $app['config']->set(
            'app-settings.driver',
            'database'
        );

        $app['config']->set(
            'app-settings.database_connection',
            'testing'
        );

        $app['config']->set(
            'app-settings.table',
            'settings'
        );

        /*
        |--------------------------------------------------------------------------
        | Cache Store Configuration (File Driver)
        |--------------------------------------------------------------------------
        */
        $app['config']->set('cache.default', 'file');
        $app['config']->set('cache.stores.file', [
            'driver' => 'file',
            'path'   => __DIR__ . '/../workbench/storage/framework/cache',
        ]);

        $app['config']->set(
            'cache-store.driver',
            'file'
        );

        $app['config']->set(
            'cache-store.key_separator',
            ':'
        );

        $app['config']->set(
            'cache-store.prefix',
            'schoolpalm'
        );

        $app['config']->set(
            'cache-store.context',
            [
                'tenant' => true,
                'school' => true,
            ]
        );

        $app['config']->set(
            'cache-store.drivers.file',
            [
                'path' => __DIR__ . '/../workbench/storage/framework/cache',
            ]
        );

        $app['config']->set(
            'cache-store.drivers.database',
            [
                'table' => 'cache_store',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Message Delivery Configuration
        |--------------------------------------------------------------------------
        */
        $app['config']->set('message-delivery.default_channel', 'email');

        $app['config']->set('message-delivery.notification', [
            'default_language' => 'en',
            'default_priority' => 'normal',
        ]);

        $app['config']->set('message-delivery.delivery_tracking', true);

        $app['config']->set('message-delivery.channels', [
            'email'    => 'laravel-mail',
            'sms'      => 'egosms',
            'whatsapp' => 'twilio-whatsapp',
            'push'     => 'firebase',
        ]);

        $app['config']->set('message-delivery.providers', [
            'laravel-mail' => [
                'mailer' => 'array',
            ],
            'ses' => [
                'mailer' => 'ses',
            ],
            'mailgun' => [
                'mailer' => 'mailgun',
            ],
            'postmark' => [
                'mailer' => 'postmark',
            ],
            'resend' => [
                'mailer' => 'resend',
            ],
            'egosms' => [
                'api_url'   => 'https://api.egosms.co/v1',
                'username'  => 'test_user',
                'password'  => 'secret',
                'sender_id' => 'SCHOOLPALM',
            ],
            'twilio-sms' => [
                'sid'   => 'AC_test_sid',
                'token' => 'test_token',
                'from'  => '+1234567890',
            ],
            'twilio-whatsapp' => [
                'sid'   => 'AC_test_sid',
                'token' => 'test_token',
                'from'  => 'whatsapp:+14155238886',
            ],
            'firebase' => [
                'credentials' => __DIR__ . '/../workbench/storage/app/firebase.json',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Queued Jobs Configuration
        |--------------------------------------------------------------------------
        */
        $app['config']->set('queued-jobs.connection', 'sync');
        $app['config']->set('queued-jobs.queue', 'default');
        $app['config']->set('queued-jobs.capture_context', true);
        $app['config']->set('queued-jobs.auto_restore_context', true);
        $app['config']->set('queued-jobs.tries', 3);
        $app['config']->set('queued-jobs.timeout', 120);

        View::addLocation(__DIR__ . '/../workbench/resources/views');
    }

    /**
     * Load package database migrations for Testbench execution.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(
            __DIR__ . '/../vendor/schoolpalm/app-settings/database/migrations'
        );

        // Create tables required for cross-channel notification tests
        // (in-app database notifications + email delivery tracking).
        $this->artisan('migrate')->run();

        \Illuminate\Support\Facades\Schema::create(
            'notifications',
            function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->string('notifiable_type');
                $table->string('notifiable_id');
                $table->index(['notifiable_type', 'notifiable_id']);

                $table->string('title');
                $table->text('body')->nullable();

                $table->json('data')->nullable();

                $table->string('channel')->nullable();
                $table->string('provider')->nullable();

                $table->timestamp('read_at')->nullable();

                $table->timestamps();
            }
        );

        \Illuminate\Support\Facades\Schema::create(
            'message_deliveries',
            function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->string('tenant_id')->nullable();
                $table->string('school_id')->nullable();

                $table->string('channel');
                $table->string('provider');
                $table->string('recipient');
                $table->string('status');

                $table->string('provider_message_id')->nullable();
                $table->string('subject')->nullable();
                $table->json('metadata')->nullable();
                $table->text('error')->nullable();

                $table->datetime('queued_at')->nullable();
                $table->datetime('sent_at')->nullable();
                $table->datetime('delivered_at')->nullable();

                $table->timestamps();

                $table->index('tenant_id');
                $table->index('school_id');
                $table->index('channel');
                $table->index('status');
                $table->index('created_at');
            }
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Clean storage & cache directories before every test
        |--------------------------------------------------------------------------
        */
        $cachePath = __DIR__ . '/../workbench/storage/framework/cache';
        if (file_exists($cachePath)) {
            $this->app->make('files')->cleanDirectory($cachePath);
        }

        $this->app
            ->make('filesystem')
            ->disk('local')
            ->deleteDirectory('tenants');
    }
}
