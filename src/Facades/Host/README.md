# Host Facades

This directory contains Laravel facades for module bridge host adapters. Each facade proxies a specific host adapter and exposes context-aware helpers for the current tenant, school, user, or module execution.

## Available Host APIs

- `CacheHost` — cache access with context-aware keys
- `DocumentHost` — document storage and retrieval helpers
- `LoggerHost` — logging helpers for module runtime context
- `MessageHost` — channel delivery builders for message sending
- `NotificationHost` — notification dispatch and event helpers
- `QueueHost` — queue helper for scoped job dispatch
- `SettingsHost` — tenant/school/user settings access
- `StorageHost` — generic storage helper for documents and assets

## Using Host Facades

Host facades are designed to be used from anywhere inside the package or a consuming application. They automatically include runtime context values such as `tenant_id`, `school_id`, and current module information.

### Example: Sending a scoped message

```php
use SchoolPalm\ModuleBridge\Facades\Host\MessageHost;

MessageHost::forSchool('school-999')
    ->email()
    ->subject('Welcome')
    ->body('Hello from Module Bridge')
    ->dispatch();
```

### Example: Dispatching a notification event

```php
use SchoolPalm\ModuleBridge\Facades\Host\NotificationHost;

NotificationHost::withContext([
    'user_id' => 'user-123',
    'source'  => 'billing',
])->dispatch(
    'invoice.paid',
    ['amount' => 1200],
    ['school_id' => 'school-999'],
    ['channel' => 'notification'],
    ['email'],
    'en',
    'high',
    'invoice_paid_template'
);
```

### Example: Reading settings for current tenant/school

```php
use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;

$locale = SettingsHost::forTenant('tenant-001')
    ->forSchool('school-999')
    ->forUser('user-123')
    ->group('localization')
    ->get('locale', 'en');
```

### Example: Accessing cache with context-aware keys

```php
use SchoolPalm\ModuleBridge\Facades\Host\CacheHost;

CacheHost::forTenant('tenant-001')
    ->forSchool('school-999')
    ->put('recent_notifications', $notifications, 3600);
```

### Example: Using storage host

```php
use SchoolPalm\ModuleBridge\Facades\Host\StorageHost;

StorageHost::forSchool('school-999')
    ->put('documents/receipt.pdf', $contents);
```

## Example: Using LoggerHost

```php
use SchoolPalm\ModuleBridge\Facades\Host\LoggerHost;

LoggerHost::channel('audit')
    ->info('User login', [
        'user_id' => 'user-123',
        'tenant_id' => 'tenant-001',
    ]);

$logs = LoggerHost::logsByContext('tenant_id', 'tenant-001', 50);
```

## Example: Using QueueHost

```php
use SchoolPalm\ModuleBridge\Facades\Host\QueueHost;

QueueHost::withContext([
    'tenant_id' => 'tenant-001',
    'school_id' => 'school-999',
])
->dispatch(new \App\Jobs\SendReportJob());
```

## Example: Using DocumentHost

```php
use SchoolPalm\ModuleBridge\Facades\Host\DocumentHost;

DocumentHost::pdf()
    ->data(['title' => 'Report'])
    ->generate();
```

## API Reference

### CacheHost

- `driver(?string $driver)`
- `store(?string $store)`
- `tags(array|string $tags)`
- `forSchool(?string $schoolId)`
- `forTenant(?string $tenantId)`
- `get(string $key, mixed $default = null)`
- `put(string $key, mixed $value, DateTimeInterface|DateInterval|int|null $ttl = null)`
- `forever(string $key, mixed $value)`
- `forget(string $key)`
- `flush()`
- `remember(string $key, DateTimeInterface|DateInterval|int|null $ttl, Closure $callback)`
- `rememberForever(string $key, Closure $callback)`
- `increment(string $key, int $value = 1)`
- `decrement(string $key, int $value = 1)`
- `many(array $keys)`
- `putMany(array $values, DateTimeInterface|DateInterval|int|null $ttl = null)`
- `pull(string $key, mixed $default = null)`
- `lock(string $key, int $seconds)`

### DocumentHost

- `pdf()`
- `excel()`
- `word()`
- `csv()`
- `image()`

### LoggerHost

- `channel(?string $channel)`
- `getDefaultDriver()`
- `context()`
- `createDatabaseDriver()`
- `createFileDriver()`
- `logs()`
- `log(LogPayload $payload)`
- `info(string $message, array $data = [])`
- `debug(string $message, array $data = [])`
- `warning(string $message, array $data = [])`
- `error(string $message, array $data = [], ?Throwable $exception = null)`
- `exception(Throwable $exception, array $data = [])`
- `logsByContext(string $key, mixed $value = null, int $limit = 100)`
- `delete(mixed $id)`
- `flush()`
- `prune(CarbonInterface $before)`
- `format(Collection $logs, string $formatter)`
- `export(mixed $content, string $filename)`
- `exportLogs(Collection $logs, string $format, string $filename)`

### MessageHost

- `forSchool(?string $schoolId)`
- `forTenant(?string $tenantId)`
- `withContext(array $context)`
- `sms()`
- `email()`
- `push()`
- `whatsapp()`
- `inApp()`
- `channels(array $channels)`

### NotificationHost

- `forSchool(?string $schoolId)`
- `forTenant(?string $tenantId)`
- `withContext(array|MessageContext $context)`
- `withAutoScope()`
- `event(string $event)`
- `dispatch(string $event, array $data = [], array $context = [], array $metadata = [], array $channels = [], ?string $language = null, ?string $priority = null, ?string $template = null)`

### QueueHost

- `job(object $job)`
- `dispatch(object $job)`
- `forSchool(?string $schoolId)`
- `forTenant(?string $tenantId)`
- `withContext(array|MessageContext|QueueContext $context)`
- `withAutoScope()`
- `withTenant(string|int $tenantId)`
- `withSchool(string|int $schoolId)`
- `withUser(string|int $userId)`
- `withModule(string $module)`
- `withMetadata(array $metadata)`
- `prepare()`

### SettingsHost

- `get(string $key, mixed $default = null)`
- `all()`
- `has(string $key)`
- `set(string $key, mixed $value)`
- `forget(string $key)`
- `forTenant(?string $tenantId)`
- `forSchool(?string $schoolId)`
- `forUser(?string $userId)`

### StorageHost

- `forContext(?string $tenantId, ?string $schoolId)`
- `put(string $path, mixed $contents)`
- `get(string $path)`
- `exists(string $path)`
- `delete(string $path)`
- `deleteDirectory(string $path)`
- `copy(string $from, string $to)`
- `move(string $from, string $to)`
- `publicUrl(string $path)`
- `resolvePath(string $path)`

## Configuration

### module-bridge

The `module-bridge` package config is published from `src/Support/config/module-bridge.php`.

Key settings include:

- `sdk.runtime` — runtime mode used by the bridge
- `sdk.registry_path` — autoload registry location
- `sdk.snapshot.registry_path` — snapshot registry location
- `module-bridge.default_channel` — default message channel
- `module-bridge.cache_key` — cache key prefix
- `module-bridge.cache_ttl` — cache TTL for module registry snapshots

### app-settings

`SettingsHost` uses the AppSettings package. Configure:

- `app-settings.driver` — storage driver (e.g. `database`)
- `app-settings.database_connection` — database connection name
- `app-settings.table` — settings table name

### cache-store

`CacheHost` uses the CacheStore package. Configure:

- `cache-store.driver` — `file`, `database`, or other store
- `cache-store.key_separator` — separator for generated cache keys
- `cache-store.prefix` — prefix for cache keys
- `cache-store.context.tenant` — include tenant context in keys
- `cache-store.context.school` — include school context in keys

### message-delivery

`MessageHost` and `NotificationHost` use the MessageDelivery package.
Configure:

- `message-delivery.default_channel`
- `message-delivery.notification.default_language`
- `message-delivery.notification.default_priority`
- `message-delivery.delivery_tracking`
- `message-delivery.channels` — channel provider mapping
- `message-delivery.providers` — provider-specific config

### queued-jobs

`QueueHost` uses the QueuedJobs package. Configure:

- `queued-jobs.connection`
- `queued-jobs.queue`
- `queued-jobs.capture_context`
- `queued-jobs.auto_restore_context`
- `queued-jobs.tries`
- `queued-jobs.timeout`

## Best Practices

- Use explicit scoping for multi-tenant applications.
- Keep context keys consistent (`tenant_id`, `school_id`, `user_id`).
- Prefer facade wrappers for runtime-host operations.
- Use `withAutoScope()` when you want ambient context merged with explicit settings.
