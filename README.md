# SchoolPalm Module Bridge

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Core bridge between SchoolPalm and ModuleSDK module systems. This library provides a standardized way to define, resolve, and execute modules across different contexts (core application vs. SDK), ensuring consistency and decoupling between SchoolPalm core and module implementations.

## Table of Contents

- [SchoolPalm Module Bridge](#schoolpalm-module-bridge)
  - [Table of Contents](#table-of-contents)
  - [Installation](#installation)
  - [Overview](#overview)
    - [Key Concepts](#key-concepts)
  - [Usage](#usage)
    - [Binding the Bridge](#binding-the-bridge)
    - [Creating Modules](#creating-modules)
    - [Resolving Modules](#resolving-modules)
  - [API Reference](#api-reference)
    - [Contracts](#contracts)
      - [ModuleContract](#modulecontract)
      - [ResolverContract](#resolvercontract)
    - [Core Classes](#core-classes)
      - [AbstractModule](#abstractmodule)
      - [Module](#module)
    - [Support Classes](#support-classes)
      - [Bridge](#bridge)
  - [Examples](#examples)
    - [Complete Module Implementation](#complete-module-implementation)
    - [Bridge Binding in Laravel Service Provider](#bridge-binding-in-laravel-service-provider)
  - [License](#license)

## Installation

Install via Composer:

```bash
composer require schoolpalm/module-bridge
```

**Requirements:**
- PHP 8.2 or higher

## Overview

The SchoolPalm Module Bridge serves as an abstraction layer that allows:

- **SchoolPalm Core**: To execute modules without direct dependencies on module implementations
- **Module SDK**: To develop and test modules in isolation
- **Modules**: To remain framework-agnostic while following consistent patterns

### Key Concepts

- **Modules**: Self-contained units of functionality that implement business logic and UI components
- **Resolvers**: Components that locate module classes, actions, and components at runtime
- **Bridge Binding**: Runtime mechanism to connect the bridge to concrete implementations

## Usage

### Binding the Bridge

Before using the bridge, the host application (SchoolPalm core or Module SDK) must bind its concrete Module base class:

```php
use SchoolPalm\ModuleBridge\Support\Bridge;

// During application bootstrapping (e.g., in a Service Provider)
Bridge::bind(\App\Core\Module::class);
```

This creates a class alias that allows modules extending `SchoolPalm\ModuleBridge\Core\Module` to transparently use the host application's implementation.

### Creating Modules

All modules should extend the bridge's Module class:

```php
use SchoolPalm\ModuleBridge\Core\Module;

class MyModule extends Module
{
    protected function loadModules(): void
    {
        // Load child modules or action handlers
    }

    public function performAction(): void
    {
        // Implement module logic based on $this->action, $this->portal, etc.
    }

    public function componentPath(): string
    {
        return 'MyModule/Index'; // Inertia component path
    }

    public function moduleComponentPath(string $path = ''): string
    {
        return 'MyModule/' . $path;
    }

    public function refererComponent(): string
    {
        return ''; // Optional referer path
    }
}
```

### Resolving Modules

Implement the `ResolverContract` to provide module resolution:

```php
use SchoolPalm\ModuleBridge\Contracts\ResolverContract;

class MyResolver implements ResolverContract
{
    public function resolveModuleMainClass(string $module): ?string
    {
        // Return fully-qualified class name
        return "App\\Modules\\{$module}\\{$module}Module";
    }

    public function resolveActionPath(string $module): ?string
    {
        return "app/Modules/{$module}/Actions";
    }

    public function resolveActionNamespace(string $module): ?string
    {
        return "App\\Modules\\{$module}\\Actions";
    }

    public function resolveComponent(string $module, string $action): string
    {
        return "{$module}/{$action}";
    }

    public function resolveModuleComponentBase(string $module): string
    {
        return $module;
    }
}
```

## API Reference

### Contracts

#### ModuleContract

Defines the interface that all modules must implement.

- `performAction(): mixed` - Execute the module's business logic
- `componentPath(): string` - Return the main UI component path
- `moduleComponentPath(string $path = ''): string` - Return module-relative component paths
- `refererComponent(): string` - Optional referer component path

#### ResolverContract

Defines the interface for module resolution services.

- `resolveModuleMainClass(string $module): ?string` - Get module's main class
- `resolveActionPath(string $module): ?string` - Get actions directory path
- `resolveActionNamespace(string $module): ?string` - Get actions namespace
- `resolveComponent(string $module, string $action): string` - Get component path for action
- `resolveModuleComponentBase(string $module): string` - Get module's base component path

### Core Classes

#### AbstractModule

Abstract base class providing common module functionality.

**Properties:**
- `$portal: string` - Current portal/area (e.g., 'admin', 'user')
- `$moduleName: string` - Module identifier
- `$action: string` - Current action (e.g., 'create', 'update')
- `$id: mixed` - Optional action-related ID

**Methods:**
- `setContext(array $context): void` - Inject runtime context
- `performAction(): void` - Abstract method for action execution
- `loadModules(): void` - Abstract method for loading submodules

#### Module

Empty abstract class that gets aliased at runtime to the host application's concrete Module class.

### Support Classes

#### Bridge

Static utility for binding the bridge to concrete implementations.

- `bind(string $concreteBaseClass): void` - Bind concrete class to bridge (call once per request)

## Examples

### Complete Module Implementation

```php
<?php

namespace App\Modules\UserManagement;

use SchoolPalm\ModuleBridge\Core\Module;

class UserModule extends Module
{
    protected function loadModules(): void
    {
        // Register child modules if needed
        $this->registerChildModule('Profile', ProfileModule::class);
    }

    public function performAction(): void
    {
        switch ($this->action) {
            case 'create':
                $this->createUser();
                break;
            case 'update':
                $this->updateUser($this->id);
                break;
            case 'delete':
                $this->deleteUser($this->id);
                break;
            default:
                throw new \InvalidArgumentException("Unknown action: {$this->action}");
        }
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

    private function createUser(): void
    {
        // Implementation logic
    }

    private function updateUser($id): void
    {
        // Implementation logic
    }

    private function deleteUser($id): void
    {
        // Implementation logic
    }
}
```

### Bridge Binding in Laravel Service Provider

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use SchoolPalm\ModuleBridge\Support\Bridge;

class ModuleBridgeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Bind the bridge during application boot
        Bridge::bind(\App\Core\Module::class);
    }
}
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
