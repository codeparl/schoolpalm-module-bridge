<?php

namespace Tests;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase as Orchestra;
use SchoolPalm\AppLogger\AppLoggerServiceProvider;
use SchoolPalm\AppSettings\Providers\AppSettingsServiceProvider;
use SchoolPalm\CacheStore\Providers\CacheStoreServiceProvider;
use SchoolPalm\ModuleBridge\Providers\ModuleBridgeServiceProvider;
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
