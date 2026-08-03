# SchoolPalm Module Bridge (Developer Documentation)

## 1. What this package is
The **SchoolPalm Module Bridge** is the runtime abstraction layer that lets **SchoolPalm core** and the **Module SDK** execute the same “module” codebase without hard-coupling them to each other.

It provides:
- A shared contract location for module base classes and module behavior.
- A runtime **binding** mechanism so the host application can supply its concrete base module implementation.
- Consistent helpers for:
  - relation definitions + hydration
  - module contract querying (in-memory, contract-level)
  - installer pipeline coordination
  - SDK/runtime registries and autoloading

## 2. Mental model: Host vs Modules vs SDK
Think of three layers:

### A) Host application (SchoolPalm core or Module SDK)
The host application owns:
- The concrete implementation of the module base class.
- Runtime services: registries, resolvers, contract search services, etc.

### B) Module SDK (development/test sandbox)
In SDK runtime, additional behaviors may be enabled (e.g., module autoloading) to support development and testing.

### C) Modules (your feature packages)
A module should:
- Follow the agreed module structure (actions, components, contracts, relation provider).
- Extend the bridge’s module base type (through the aliasing mechanism).
- Implement module actions via the resolved runtime context.

## 3. The Bridge binding mechanism (critical)
### 3.1 The contract location
Modules should extend:

- `SchoolPalm\ModuleBridge\Core\Module`

This is an **alias target**, not a real implementation.

### 3.2 What the host must do
At application boot, the host must bind its concrete module base implementation:

```php
use SchoolPalm\ModuleBridge\Support\Bridge;

// During host boot
Bridge::bind(\App\Core\Module::class);
```

Internally this uses `class_alias()` so that extending `SchoolPalm\ModuleBridge\Core\Module` actually becomes extending your host’s concrete class.

### 3.3 Binding rules
- Call **once per request lifecycle** (subsequent calls are ignored).
- The concrete class must **exist**.
- Concrete class should extend the host’s expected abstract behavior (the README recommends extending an abstract base).

### 3.4 Runtime module registration
At runtime, modules/components/actions use:

- `Bridge::runtime($moduleInstance)` to register the currently executing module.
- `Bridge::current()` to retrieve the active module instance.

This is intentionally a lightweight runtime context holder.

## 4. Service provider integration
The package ships a Laravel service provider:

- `SchoolPalm\ModuleBridge\Providers\ModuleBridgeServiceProvider`

It is responsible for registering key services in the container, including:
- `created.registry` (SDK runtime only)
- `SnapshotRegistry`
- `autoload.registry`
- `sdk.config` (config fetcher)
- `module.registry`
- `module-transit`
- `module.installer`
- `module.autoload` (SDK runtime only)

It also:
- Reads encrypted academic levels via `EncryptedConfig::read('academic_levels')`
- Applies them as defaults via `LevelManager::setDefaultLevels($levels)`

### SDK runtime vs core runtime
The provider checks a config value:
- `config('sdk.runtime', 'SDK') == 'SDK'`

When running as SDK:
- certain registries and module autoloading are enabled.

When running as core:
- those parts are typically not activated.

## 5. Implementing a module (what you should extend)
A module should extend the bridge module base:

```php
use SchoolPalm\ModuleBridge\Core\Module;

class UserModule extends Module
{
    protected function loadModules(): void
    {
        // optional: load/register submodules or action handlers
    }

    public function performAction(): void
    {
        // implement action handling using $this->action / $this->portal / $this->id
    }

    public function componentPath(): string
    {
        return 'UserManagement/Index';
    }

    public function moduleComponentPath(string $path = ''): string
    {
        return 'UserManagement/' . ($path ?: 'Index');
    }

    public function refererComponent(): string
    {
        return 'Dashboard/Index';
    }
}
```

The core expectation is:
- `performAction()` runs the current action.
- component path methods help render the right UI entrypoint.

## 6. ResolverContract: how modules/actions/components are located
Modules are discovered and executed using a resolver (contract).

You should provide a resolver implementation that follows `ResolverContract`:
- `resolveModuleMainClass(string $module): ?string`
- `resolveActionPath(string $module): ?string`
- `resolveActionNamespace(string $module): ?string`
- `resolveComponent(string $module, string $action): string`
- `resolveModuleComponentBase(string $module): string`

Conceptually:
- A **module name** maps to a **module main class**.
- Actions map to a path/namespace and a component path.

## 7. Relations: define once, hydrate consistently
This bridge includes a relations subsystem built around:
- `Relation`
- `RelationProvider`
- `RelationRegistryBuilder`
- `RelationEngine`

### 7.1 Define relations in a provider
A provider returns a map of relation names to `Relation::...` definitions.

Example patterns (method names):
- `Relation::belongsTo($contract, $localKey, $foreignKey)`
- `Relation::hasOne($contract, $foreignKey, $localKey)`
- `Relation::hasMany($contract, $foreignKey, $localKey)`
- `Relation::belongsToMany($contract, $pivotContract, $pivotParentKey, $pivotRelatedKey, $localKey, $foreignKey)`
- `Relation::morphOne(...)`, `Relation::morphMany(...)`, `Relation::morphTo(...)`

### 7.2 Build registry and load
At runtime:
- Build a registry from installed modules.
- Use the engine to hydrate `with([...])` relations.

Typical flow:
1) `$registry = app(RelationRegistryBuilder::class)->build($installedModules)`
2) `$engine = new RelationEngine($registry)`
3) `$engine->load($items, ['student', 'student.address'])`

### 7.3 Why this matters
- Relations are declared in module code.
- Hydration is handled by the bridge and engines.
- This keeps modules consistent across host implementations.

## 8. ContractQueryBuilder: module contract querying (non-SQL)
### 8.1 What it is
`SchoolPalm\ModuleBridge\Query\ContractQueryBuilder` is a Laravel-style fluent query builder that:
- is **not database-aware**
- is **not Eloquent**
- does **not** generate SQL

Instead, it acts as a:
- query state container
- relation hydration coordinator
- execution orchestrator calling into a host service

### 8.2 How it executes
`get()` calls:
- `$this->service->querySearch($this->filters)`

Then it applies in-memory operations:
- wheres
- select
- sorting
- limit/offset

If relations are requested via `with(...)` it hydrates them using `RelationEngine`.

### 8.3 Typical usage
```php
$query = ContractQueryBuilder::for($service, $engine)
    ->where('status', 'active')
    ->with('student.profile')
    ->orderBy('created_at', 'desc')
    ->limit(10);

$items = $query->get();
```

### 8.4 What the host service must provide
Your `$service` instance must implement a method used by the builder:
- `querySearch(array $filters): array`

The builder’s filter format is internal (it passes an array of filter descriptors). Keep this consistent across host implementations.

## 9. Installer pipeline integration (high-level)
The package includes an installer pipeline orchestration layer.

A key class is:
- `SchoolPalm\ModuleBridge\Pipeline\ModuleInstallerPipeline`

It extends an `InstallerPipeline` and is responsible for:
- building the installer action list via `PipelineBuilder`
- running actions using an `InstallContext`

You may run it in:
- module install flows
- module update flows
- CI/development environment provisioning

### Dry run / silent
The pipeline supports:
- `setDryRun(bool $dryRun = true)`
- `setSilent(bool $silent = true)`

## 10. Security/config helpers: EncryptedConfig
`EncryptedConfig` provides encrypted JSON storage.

- It is used by the provider to read encrypted academic levels.
- `enc.php` script exists to encrypt raw JSON config to the encrypted form.

Module authors generally:
- should **read** via helper APIs
- should not implement encryption workflows inside modules

## 11. Generators and scaffolding
The repository contains generators (e.g., scaffold and facade doc builders). Their role is to:
- reduce boilerplate
- enforce consistent conventions across modules

Treat generators as “opinionated starting points” and update the output to match your module requirements.

## 12. Common pitfalls
- **Forgetting to bind** the bridge base module in the host app.
- Binding multiple times per lifecycle (ignored, but can hide boot ordering issues).
- Extending the wrong base class (modules must extend the bridge’s core module contract type).
- Declaring relations incorrectly (ensure relation keys match actual expected item shapes produced by your contract search service).
- Using contract queries without implementing `querySearch()` in the service passed to `ContractQueryBuilder`.

---

## Appendix: Where to look in the codebase
Key files:
- `src/Support/Bridge.php` (binding + runtime registration)
- `src/Providers/ModuleBridgeServiceProvider.php` (DI wiring)
- `src/Query/ContractQueryBuilder.php` (contract query builder)
- `src/Relations/*` (relation system)
- `src/Pipeline/*` (install pipeline)
- `enc.php` (encrypted config tool)

