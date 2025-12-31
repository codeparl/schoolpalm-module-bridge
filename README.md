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
      - [SchoolModel](#schoolmodel)
    - [Support Classes](#support-classes)
      - [Bridge](#bridge)
      - [EncryptedConfig](#encryptedconfig)
      - [Helper](#helper)
  - [Examples](#examples)
    - [Complete Module Implementation](#complete-module-implementation)
    - [Bridge Binding in Laravel Service Provider](#bridge-binding-in-laravel-service-provider)
  - [Scripts and Tools](#scripts-and-tools)
    - [enc.php](#encphp)
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
Bridge::bind(\App\Core\BaseModule::class);
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

#### SchoolModel

Abstract base class extending `Illuminate\Database\Eloquent\Model` for school-related models, ensuring multi-tenant safety by automatically scoping queries to the current school.

**Purpose:**
- Provides automatic scoping of queries by `school_id` based on the `current_school` context.
- Prevents modules from accidentally accessing data from other schools in a multi-tenant environment.

**Key Features:**
- **Global Scope**: Automatically applies a `where('school_id', ...)` filter to all queries using the current school context.
- **Multi-Tenant Safety**: Ensures that school-specific data (e.g., Students, Teachers, Classes) is properly isolated.

**Methods:**
- `forSchool(int $schoolId): \Illuminate\Database\Eloquent\Builder` - Override the global scope to query records for a specific school explicitly.

**Usage Example:**
```php
use SchoolPalm\ModuleBridge\Core\SchoolModel;

class Student extends SchoolModel
{
    protected $table = 'students';
}

// Automatically scoped to current_school
$students = Student::all();

// Override scope for a specific school
$otherSchoolStudents = Student::forSchool($schoolId)->get();
```

**Notes:**
- The `current_school` must be resolved from the application context (e.g., via a tenant resolver or service container).
- Vendor modules should extend `SchoolModel` for school-specific tables to ensure proper scoping in production and SDK/test environments.

### Support Classes

#### Bridge

Static utility for binding the bridge to concrete implementations.

- `bind(string $concreteBaseClass): void` - Bind concrete class to bridge (call once per request)

#### EncryptedConfig

Utility class for handling encrypted configuration files using AES-256-CBC encryption.

**Purpose:**
- Securely store and retrieve sensitive configuration data (e.g., academic levels).
- Prevent unauthorized access to config files by encrypting them.

**Key Features:**
- **Encryption**: Uses AES-256-CBC with a predefined key for encrypting/decrypting data.
- **File Obfuscation**: Hashes filenames to obscure the logical config names.
- **Initialization**: Sets up the base path for encrypted config files.

**Methods:**
- `init(?string $basePath = null): void` - Initialize the base path for encrypted files (defaults to `src/Support/config/`).
- `resolveFilePath(string $key): string` - Get the full path to an encrypted file based on a logical key.
- `read(string $key): array` - Decrypt and read data from an encrypted file.
- `write(string $key, array $data): bool` - Encrypt and write data to a file (internal use only).
- `encryptAndClean(string $configPath, string $rawFilePath, string $key): bool` - Encrypt a raw JSON file and remove the original.

**Usage Example:**
```php
use SchoolPalm\ModuleBridge\Support\EncryptedConfig;

// Initialize (optional, defaults to src/Support/config/)
EncryptedConfig::init('/path/to/config');

// Read encrypted config
$levels = EncryptedConfig::read('academic_levels');

// Encrypt and clean raw file (internal use)
EncryptedConfig::encryptAndClean('/config/path', '/raw/file.json', 'key');
```

**Notes:**
- The encryption key is hardcoded and must match between SDK and SchoolPalm.
- Vendors should never call `write()` or `encryptAndClean()` directly; these are for internal use.

#### Helper

Utility class providing pure PHP helper functions for common operations.

**Purpose:**
- Offer reusable functions for string manipulation, path handling, JSON operations, and module naming.
- Ensure consistency across SchoolPalm core and Module SDK.

**Key Features:**
- **Path Helpers**: Extract segments from paths (e.g., portal, module, action).
- **JSON Helpers**: Load/store JSON files with optional key extraction.
- **Module Helpers**: Normalize module names, generate folder names for levels/roles/modules.
- **String Helpers**: Laravel-like string functions (studly, kebab, snake, etc.).

**Methods:**
- `getPathSegment(string $key, ?string $path, bool $central = false): ?string` - Extract a path segment.
- `loadJson(string $fileName, ?string $key = null, ?string $path = null): array` - Load and decode a JSON file.
- `storeJson(string $fileName, array $data, string $path): bool` - Store data as JSON.
- `normalizeModuleName(string $module): string` - Normalize module names to StudlyCase.
- `levelsFolderName(array $levels, array $levelCodes = []): string` - Generate folder names for academic levels.
- `roleFolderName(string $role): string` - Generate folder names for roles.
- `moduleFolderName(string|array|object $module): string` - Generate folder names for modules.
- `getAcademicLevels(): array` - Retrieve academic levels from encrypted config.
- Various string helpers: `contains()`, `startsWith()`, `endsWith()`, `studly()`, `kebab()`, etc.

**Usage Example:**
```php
use SchoolPalm\ModuleBridge\Support\Helper;

// Path segment extraction
$module = Helper::getPathSegment('module', 'admin/students/edit/5'); // 'students'

// JSON operations
$data = Helper::loadJson('config.json');
Helper::storeJson('output.json', $data, '/path/to/dir');

// String manipulation
$studly = Helper::studly('hello-world'); // 'HelloWorld'
$kebab = Helper::kebab('HelloWorld'); // 'hello-world'

// Module naming
$folder = Helper::moduleFolderName('user.management'); // 'UserManagement'
```

**Notes:**
- All methods are static and stateless.
- Designed to be framework-independent for maximum reusability.

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

## Scripts and Tools

### enc.php

A command-line script for encrypting raw JSON configuration files and removing the originals.

**Purpose:**
- Encrypt sensitive configuration data (e.g., academic levels) for secure storage.
- Automate the process of converting raw JSON files to encrypted format.

**Usage:**
```bash
php enc.php
```

**Requirements:**
- The raw JSON file must exist at `src/Support/config/academic_levels.json`.
- The script uses `EncryptedConfig::encryptAndClean()` to perform the encryption.

**Notes:**
- Run this script after updating raw config files to encrypt them.
- The original raw file is removed after successful encryption.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
