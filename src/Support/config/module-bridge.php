<?php

declare(strict_types=1);

return [

    'app' => [
        'url' => env('API_URL', 'http://schoolpalm.test'),
        'env' => env('APP_ENV', 'local'),
        'api' => ['config' => 'sdk/config'],
        'store' => [
            'url' => env('STORE_URL', 'http://schoolpalm.test')
        ]
    ],

    'current_school_session_key' => 'current_school_id',

    'modules' => [
        'root' => base_path('modules'),
        'dev_port_start' => 5174,
        'transit_path' => base_path('packages'),
        'transit_file' => realpath(__DIR__) . '/../../data/module-transit.json'
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Registry Path
    |--------------------------------------------------------------------------
    |
    | Path where the CreatedModuleRegistry will store the JSON registry.
    | You can override this in your .env for testing or different environments.
    |
    */
    'registry_path' => env('MODULE_REGISTRY_PATH', base_path('modules.json')),

    /*
    |--------------------------------------------------------------------------
    | Cache Key
    |--------------------------------------------------------------------------
    |
    | Key used to cache the module registry in Laravel's cache system.
    |
    */
    'cache_key' => env('MODULE_REGISTRY_CACHE_KEY', 'schoolpalm'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (minutes)
    |--------------------------------------------------------------------------
    |
    | Time in minutes to cache the module registry.
    | Set to null to cache indefinitely (not recommended in dev).
    |
    */
    'cache_ttl' => env('MODULE_REGISTRY_CACHE_TTL', 60),

    'package_map' => [
        'app-logger' => 'logger',
        'document-builder' => 'documents',
        'message-delivery' => 'messages',
        'cache-store' => 'cache',
        'app-settings' => 'settings',
        'queued-jobs' => 'queues',
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoload Registry Path (Optional)
    |--------------------------------------------------------------------------
    |
    | Path used by AutoloadModuleRegistry for its cache file.
    | Only used if the module-bridge runs standalone.
    |
    */
    'autoload_registry_path' => env(
        'AUTOLOAD_MODULE_REGISTRY_PATH',
        base_path('bootstrap/cache/modules.php')
    ),

    /*
    |--------------------------------------------------------------------------
    | Enable Testing Fallback
    |--------------------------------------------------------------------------
    |
    | When true, the registries fallback to a safe temp folder for testing
    | instead of using production paths. Useful for Testbench or bridge dev.
    |
    */
    'testing_fallback' => env('MODULE_BRIDGE_TESTING_FALLBACK', true),

    /*
    |--------------------------------------------------------------------------
    | Integrated Package Defaults
    |--------------------------------------------------------------------------
    |
    | Centralized default configuration options for integrated SchoolPalm 
    | ecosystem modules (MessageDelivery and QueuedJobs).
    |
    */
    'message_delivery' => [
        'default_channel' => env('MESSAGE_DELIVERY_DEFAULT_CHANNEL', 'sms'),
        'enabled_channels' => ['sms', 'email', 'push', 'whatsapp', 'in_app'],
        'fallback_enabled' => env('MESSAGE_DELIVERY_FALLBACK_ENABLED', true),
    ],

    'queued_jobs' => [
        'default_queue' => env('QUEUED_JOBS_QUEUE', 'default'),
        'default_connection' => env('QUEUED_JOBS_CONNECTION', 'redis'),
        'auto_inject_context' => env('QUEUED_JOBS_AUTO_INJECT_CONTEXT', true),
    ],

];
