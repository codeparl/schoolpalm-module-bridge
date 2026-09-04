<?php

namespace SchoolPalm\ModuleBridge\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use SchoolPalm\ModuleBridge\Manifest\ManifestFactory;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;
use SchoolPalm\ModuleBridge\Profiles\ContractProfile;
use SchoolPalm\ModuleBridge\Support\Helper;

/**
 * EventGenerator - Generates event classes for core contracts.
 * Events are generated ONCE per contract and available to ALL services.
 * Events are organized in subfolders by entity name (e.g., Events/Student/).
 * Events are stored at the root Backend/Events/ folder, NOT inside Core.
 */
class EventGenerator
{
    /**
     * The entity name (e.g., 'Student').
     */
    protected string $entity;

    /**
     * The entity name in lowercase (e.g., 'student').
     */
    protected string $entityLower;

    /**
     * The event namespace.
     */
    protected string $eventNamespace;

    /**
     * The module manifest.
     */
    protected ModuleManifest $manifest;

    /**
     * The manifest path.
     */
    protected string $manifestPath;

    /**
     * The module namespace.
     */
    protected string $moduleNamespace;

    /**
     * The contract profile.
     */
    protected ContractProfile $profile;

    /**
     * Events to generate.
     */
    protected array $events = ['Created', 'Updated', 'Deleted'];

    /**
     * The event base path.
     */
    protected string $eventBasePath;

    public function __construct(
        string $contractInterface,
        string $serviceClass,
        string $outputPath,
        ModuleManifest $manifest,
        string $manifestPath,
        ContractProfile $profile
    ) {
        $this->manifest = $manifest;
        $this->manifestPath = $manifestPath;
        $this->profile = $profile;

        // Get module info from manifest
        $this->moduleNamespace = $manifest->info()->namespace();
        $this->entity = $this->resolveEntity($contractInterface);
        $this->entityLower = strtolower($this->entity);
        $this->eventNamespace = $this->resolveEventNamespace();
        $this->eventBasePath = $this->resolveEventBasePath();
    }

    /**
     * Generate default notification settings key-value array from a manifest array.
     * Ready to be ingested by SettingsHost during module installation in SchoolPalm.
     *
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    public static function generateSettingsFromManifest(array $manifest): array
    {
        $settings = [];
        $events = $manifest['events'] ?? [];

        foreach ($events as $eventClass) {
            if (! is_string($eventClass)) {
                continue;
            }

            // 1. Resolve event identifier key (e.g. "student.created")
            $eventKey = static::resolveEventKey($eventClass);

            // 2. Extract defaults from class static methods or fallback
            $defaults = static::extractDefaultsFromClass($eventClass);

            // 3. Map settings
            $settings["channels_enabled.{$eventKey}"] = $defaults['channels'] ?? ['email', 'in_app'];
            $settings["priority.{$eventKey}"]         = $defaults['priority'] ?? 'normal';

            if (! empty($defaults['templates'])) {
                foreach ($defaults['templates'] as $channel => $template) {
                    $settings["templates.{$eventKey}.{$channel}"] = $template;
                }
            } else {
                $settings["templates.{$eventKey}.email"] = [
                    'subject'   => "Notification: {$eventKey}",
                    'content'   => "An event ({$eventKey}) was triggered.",
                    'variables' => [],
                ];
                $settings["templates.{$eventKey}.in_app"] = "Event {$eventKey} was triggered.";
            }
        }

        return $settings;
    }

    /**
     * Convert event class FQCN to dot-notation event key.
     * Example: Unnovatebrains\Common\Student\Backend\Events\Students\StudentCreatedEvent -> "student.created"
     */
    protected static function resolveEventKey(string $eventClass): string
    {
        if (defined("{$eventClass}::EVENT_KEY")) {
            return constant("{$eventClass}::EVENT_KEY");
        }

        $className = class_basename($eventClass);
        $cleanName = preg_replace('/Event$/', '', $className);
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $cleanName));

        return str_replace('_', '.', $snake);
    }

    /**
     * Extract defaults from class if class exists and implements setting extraction method.
     */
    protected static function extractDefaultsFromClass(string $eventClass): array
    {
        if (class_exists($eventClass)) {
            $reflection = new ReflectionClass($eventClass);

            if ($reflection->hasMethod('defaultNotificationSettings')) {
                return $eventClass::defaultNotificationSettings();
            }
        }

        return [];
    }

    /**
     * Generate all events for the contract.
     * Events are generated ONCE per contract.
     */
    public function generate(): array
    {
        $generatedEvents = [];

        foreach ($this->events as $action) {
            $eventClass = $this->entity . $action . 'Event';
            $eventPath = $this->getEventPath($eventClass);

            // Generate event class (skip if exists)
            $this->generateEventClass($eventClass, $eventPath);

            $generatedEvents[] = [
                'class' => $this->eventNamespace . '\\' . $eventClass,
                'action' => $action,
                'path' => $eventPath,
            ];
        }

        // Store events in manifest
        $this->storeEventsInManifest($generatedEvents);

        return $generatedEvents;
    }

    /**
     * Generate a single event class.
     */
    protected function generateEventClass(string $eventClass, string $eventPath): void
    {
        // Skip if already exists (preserve user customizations)
        if (File::exists($eventPath)) {
            return;
        }

        $stub = <<<PHP
<?php

namespace {$this->eventNamespace};

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * {$this->entity} {$eventClass}
 * 
 * Dispatched when a {$this->entity} is {$this->getActionName($eventClass)}.
 * Available to all services (internal, API, snapshot) via the contract.
 */
class {$eventClass}
{
    use Dispatchable, SerializesModels;

    /**
     * The data that was processed.
     */
    public readonly array \$data;

    /**
     * The model instance or result.
     */
    public readonly mixed \$model;

    /**
     * Create a new event instance.
     */
    public function __construct(array \$data, mixed \$model)
    {
        \$this->data = \$data;
        \$this->model = \$model;
    }
}
PHP;

        File::ensureDirectoryExists(dirname($eventPath));
        File::put($eventPath, $stub);
    }

    /**
     * Store events in the module manifest.
     */
    protected function storeEventsInManifest(array $events): void
    {
        $existingManifest = ManifestFactory::loadManifest($this->manifestPath);
        if (!$existingManifest) {
            return;
        }

        // Get existing events or initialize
        $existingEvents = $existingManifest['events'] ?? [];

        // Extract event class names
        $eventClasses = array_map(fn($e) => $e['class'], $events);

        // Merge with existing
        $mergedEvents = array_unique(array_merge($existingEvents, $eventClasses));

        // Update manifest
        ManifestFactory::update($existingManifest, [
            'events' => $mergedEvents
        ], $this->manifestPath);
    }

    /**
     * Get the method to integrate events into the service class.
     * This is available for ALL services (internal, API, snapshot).
     * Uses manifest to resolve all values.
     */
    public function getServiceIntegrationCode(): string
    {
        $entity = $this->entity;
        $eventNamespace = $this->moduleNamespace . '\\Events\\' .  Str::plural($this->entity);

        return <<<PHP

    /**
     * Dispatch an event for this service.
     * Events are defined in the contract and available to all services.
     * 
     * @param string \$action  'Created', 'Updated', 'Deleted'
     * @param array \$data     The data that was processed
     * @param mixed \$result   The model instance or result
     */
    protected function dispatchEvent(string \$action, array \$data, mixed \$result): void
    {
        \$eventClass = \$this->getEventNamespace() . '\\\\' . \$this->getEntityName() . \$action . 'Event';
        
        if (class_exists(\$eventClass)) {
            event(new \$eventClass(\$data, \$result));
        }
    }

    /**
     * Get the entity name (e.g., '{$entity}').
     */
    protected function getEntityName(): string
    {
        return '{$entity}';
    }

    /**
     * Get the event namespace.
     * Events are stored in the Events folder at the module root.
     */
    protected function getEventNamespace(): string
    {
        return '{$eventNamespace}';
    }

PHP;
    }

    /**
     * Get the imports needed for event integration.
     */
    public function getEventImports(): array
    {
        return [
            'Illuminate\\Foundation\\Events\\Dispatchable',
            'Illuminate\\Queue\\SerializesModels',
        ];
    }

    /**
     * Resolve entity name from contract.
     * StudentContract → Student
     */
    protected function resolveEntity(string $contract): string
    {
        $class = Helper::afterLast($contract, '\\');
        $entity = str_replace('Contract', '', $class);
        // Remove 'Core' if present (for contracts in Core folder)
        $entity = str_replace('Core', '', $entity);
        return $entity;
    }

    /**
     * Resolve event namespace from manifest.
     * Uses manifest to get the module namespace.
     * Events are at {namespace}\Events\{entity} (NOT in Core).
     */
    protected function resolveEventNamespace(): string
    {
        return $this->moduleNamespace . '\\Events\\' .  Str::plural($this->entity);
    }

    /**
     * Resolve the event base path from the manifest.
     * Uses manifest root to determine the path.
     * Events are at {root}/Backend/Events/{entity} (NOT in Core).
     */
    protected function resolveEventBasePath(): string
    {
        $root  =  dirname($this->manifestPath);
        return $root . '/Backend/Events/' . Str::plural($this->entity);
    }

    /**
     * Get the full path for an event class.
     */
    protected function getEventPath(string $eventClass): string
    {
        return $this->eventBasePath . '/' . $eventClass . '.php';
    }

    /**
     * Get human-readable action name.
     */
    protected function getActionName(string $eventClass): string
    {
        $actions = [
            'Created' => 'created',
            'Updated' => 'updated',
            'Deleted' => 'deleted',
            'Restored' => 'restored',
            'ForceDeleted' => 'force deleted',
        ];

        foreach ($actions as $action => $name) {
            if (str_contains($eventClass, $action)) {
                return $name;
            }
        }

        return strtolower($eventClass);
    }
}
