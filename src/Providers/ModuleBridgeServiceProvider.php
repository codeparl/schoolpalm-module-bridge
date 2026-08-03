<?php

namespace SchoolPalm\ModuleBridge\Providers;

use Illuminate\Support\ServiceProvider;
use SchoolPalm\ModuleBridge\Core\CreatedModuleRegistry;
use SchoolPalm\ModuleBridge\Core\AutoloadModuleRegistry;
use SchoolPalm\ModuleBridge\Core\ModuleAutoload;
use SchoolPalm\ModuleBridge\Support\EncryptedConfig;
use SchoolPalm\ModuleBridge\Support\LevelManager;
use Composer\Autoload\ClassLoader;
use SchoolPalm\AppSettings\Managers\SettingsManager;

use SchoolPalm\CacheStore\Contracts\CacheContextResolver as CacheContextResolverContract;
use SchoolPalm\CacheStore\Manager\CacheStoreManager;
use SchoolPalm\ModuleBridge\Adapters\CacheAdapter;
use SchoolPalm\ModuleBridge\Adapters\Document\SchoolPalmDocumentContextHandler;
use SchoolPalm\ModuleBridge\Adapters\DocumentHost;
use SchoolPalm\ModuleBridge\Adapters\LoggerAdapter;
use SchoolPalm\ModuleBridge\Adapters\MessageDeliveryAdapter;
use SchoolPalm\ModuleBridge\Adapters\QueuedJobsAdapter;
use SchoolPalm\ModuleBridge\Adapters\SettingsAdapter;
use SchoolPalm\ModuleBridge\Adapters\StorageAdapter;
use SchoolPalm\ModuleBridge\Contracts\Host\ContextHost;
use SchoolPalm\ModuleBridge\Contracts\Host\ModuleHost;
use SchoolPalm\ModuleBridge\Contracts\Host\SchoolHost;
use SchoolPalm\ModuleBridge\Contracts\Host\TenantHost;
use SchoolPalm\ModuleBridge\Contracts\Host\UserHost;
use SchoolPalm\ModuleBridge\Core\ModuleRegistry;
use SchoolPalm\ModuleBridge\Platform\ConfigFetcher;
use SchoolPalm\ModuleBridge\Packaging\ModuleTransit;
use SchoolPalm\ModuleBridge\Pipeline\ModuleInstallerFactory;
use SchoolPalm\ModuleBridge\Services\ContextResolver;
use SchoolPalm\ModuleBridge\Services\Host\ContextHostService;
use SchoolPalm\ModuleBridge\Services\Host\SchoolHostService;
use SchoolPalm\ModuleBridge\Services\Host\TenantHostService;
use SchoolPalm\ModuleBridge\Services\Host\UserHostService;
use SchoolPalm\ModuleBridge\Snapshot\SnapshotRegistry;
use UnnovateBrains\DocumentBuilder\Contracts\DocumentStorage;
use SchoolPalm\ModuleBridge\Services\Host\ModuleHostService;
use SchoolPalm\QueuedJobs\Managers\QueuedJobsManager;
use UnnovateBrains\DocumentBuilder\Contracts\ContextHandler;
use UnnovateBrains\DocumentBuilder\Contracts\DocumentContextResolver;
use SchoolPalm\MessageDelivery\Notification\Contracts\{
    EventResolver,
    RecipientResolver,
    PreferenceResolver,
    LanguageResolver,
    ChannelResolver,
    PriorityResolver,
    RetryResolver,
    ScheduleResolver,
    TemplateResolver
};
use SchoolPalm\MessageDelivery\Notification\Engine\NotificationEngine;
use SchoolPalm\MessageDelivery\Notification\NotificationManager;
use SchoolPalm\ModuleBridge\Adapters\NotificationAdapter;
use SchoolPalm\ModuleBridge\Resolvers\{
    BridgeEventResolver,
    BridgeRecipientResolver,
    BridgePreferenceResolver,
    BridgeLanguageResolver,
    BridgeChannelResolver,
    BridgePriorityResolver,
    BridgeRetryResolver,
    BridgeScheduleResolver,
    BridgeTemplateResolver
};

class ModuleBridgeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Support/config/module-bridge.php',
            'module-bridge'
        );

        // Created registry
        if (config('sdk.runtime', 'SDK') == 'SDK') {
            $this->app->singleton('created.registry', function ($app) {
                $path = config('sdk.registry_path');
                return new CreatedModuleRegistry($path);
            });
        }

        $this->app->singleton(SnapshotRegistry::class, function ($app) {
            return new SnapshotRegistry(
                $app['cache.store'],
                config('sdk.snapshot.registry_path', null)
            );
        });

        // Autoload registry
        $this->app->singleton('autoload.registry', function ($app) {
            $path = config('sdk.autoload_registry_path');
            return new AutoloadModuleRegistry($path);
        });

        $this->app->singleton('sdk.config', function ($app) {
            return new ConfigFetcher();
        });

        $this->app->singleton('module.registry', function ($app) {
            return new ModuleRegistry();
        });

        $this->app->singleton('module-transit', function ($app) {
            return new ModuleTransit();
        });

        $this->app->singleton('module.installer', function ($app) {
            return new ModuleInstallerFactory();
        });

        if (config('sdk.runtime', 'SDK') == 'SDK') {
            $this->app->singleton('module.autoload', function ($app) {
                $loader = $this->resolveComposerLoader();
                return new ModuleAutoload($loader);
            });
        }

        /**
         * Core Host bindings
         */
        $this->app->singleton(TenantHost::class, TenantHostService::class);
        $this->app->singleton(SchoolHost::class, SchoolHostService::class);
        $this->app->singleton(UserHost::class, UserHostService::class);
        $this->app->singleton(ModuleHost::class, ModuleHostService::class);

        $this->app->singleton(ContextHost::class, function ($app) {
            return new ContextHostService(
                $app->make(TenantHost::class),
                $app->make(SchoolHost::class),
                $app->make(UserHost::class),
                $app->make(ModuleHost::class)
            );
        });

        /**
         * Context Resolver Bindings
         */
        $this->app->singleton(ContextResolver::class);

        // BIND EXACT CACHECTX CONTRACT TO CONTEXTRESOLVER
        $this->app->singleton(
            CacheContextResolverContract::class,
            ContextResolver::class
        );

        $this->app->singleton(
            DocumentContextResolver::class,
            ContextResolver::class
        );

        $this->app->bind(
            ContextHandler::class,
            SchoolPalmDocumentContextHandler::class
        );

        /**
         * Adapters & Utilities
         */
        $this->app->singleton('module.storage', function ($app) {
            return new StorageAdapter(
                $app->make(DocumentStorage::class),
                $app->make(ContextResolver::class)
            );
        });

        $this->app->singleton(DocumentHost::class, function ($app) {
            return new DocumentHost(
                $app->make(ContextResolver::class)
            );
        });

        $this->app->singleton(LoggerAdapter::class, function ($app) {
            return new LoggerAdapter(
                $app->make(ContextResolver::class)
            );
        });

        $this->app->singleton(SettingsAdapter::class, function ($app) {
            return new SettingsAdapter(
                $app->make(SettingsManager::class),
                $app->make(ContextResolver::class)
            );
        });

        $this->app->singleton(CacheAdapter::class, function ($app) {
            return new CacheAdapter(
                $app->make(CacheStoreManager::class),
                $app->make(ContextResolver::class)
            );
        });


        $this->app->singleton(
            'module-bridge.message-delivery',
            fn($app) => new MessageDeliveryAdapter(
                contextResolver: $app->make(ContextResolver::class)
            )
        );

        $this->app->bind(QueuedJobsAdapter::class, function ($app) {
            return new QueuedJobsAdapter(
                $app->make(ContextResolver::class),
                $app->make(QueuedJobsManager::class)
            );
        });

        $this->app->bind(EventResolver::class, BridgeEventResolver::class);
        $this->app->bind(RecipientResolver::class, BridgeRecipientResolver::class);
        $this->app->bind(PreferenceResolver::class, BridgePreferenceResolver::class);
        $this->app->bind(LanguageResolver::class, BridgeLanguageResolver::class);
        $this->app->bind(ChannelResolver::class, BridgeChannelResolver::class);
        $this->app->bind(PriorityResolver::class, BridgePriorityResolver::class);
        $this->app->bind(RetryResolver::class, BridgeRetryResolver::class);
        $this->app->bind(ScheduleResolver::class, BridgeScheduleResolver::class);
        $this->app->bind(TemplateResolver::class, BridgeTemplateResolver::class);


        // 3. Bind the NotificationManager
        $this->app->singleton(NotificationManager::class, function ($app) {
            return new NotificationManager(
                $app->make(NotificationEngine::class)
            );
        });

        // 4. Bind the NotificationAdapter used by the Facade
        $this->app->singleton(NotificationAdapter::class, function ($app) {
            return new NotificationAdapter(
                $app->make(ContextResolver::class),
                $app->make(NotificationManager::class),
                $app
            );
        });
    }

    public function boot()
    {
        $levels = EncryptedConfig::read('academic_levels');
        LevelManager::setDefaultLevels($levels);

        $this->publishes([
            __DIR__ . '/../Support/config/module-bridge.php' => config_path('module-bridge.php'),
        ], 'config');

        if (config('sdk.runtime', 'SDK') == 'SDK') {
            $this->app->make('module.autoload')->boot();
        }
    }

    protected function resolveComposerLoader(): ClassLoader
    {
        foreach (spl_autoload_functions() as $autoload) {
            if (is_array($autoload) && $autoload[0] instanceof ClassLoader) {
                return $autoload[0];
            }
        }

        throw new \RuntimeException('Composer ClassLoader not found.');
    }
}
