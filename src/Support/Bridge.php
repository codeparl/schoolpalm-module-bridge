<?php

namespace SchoolPalm\ModuleBridge\Support;
use SchoolPalm\ModuleBridge\Core\Module;
use RuntimeException;

/**
 * Class Bridge
 *
 * The Bridge is the core mechanism that connects **SchoolPalm** and
 * **schoolpalm/module-sdk** at runtime.
 *
 * This library intentionally contains **no concrete Module implementation**.
 * Instead, it allows the host application (SchoolPalm or a Module SDK sandbox)
 * to dynamically bind its own concrete Module base class.
 *
 * ─────────────────────────────────────────────────────────────
 * WHY THIS EXISTS
 * ─────────────────────────────────────────────────────────────
 * - SchoolPalm and Module SDK must both:
 *   - Resolve modules
 *   - Execute module routes
 *   - Boot module services
 * - BUT they must NOT depend on each other directly.
 *
 * The bridge solves this by:
 * - Defining a shared abstract contract location:
 *   `SchoolPalm\ModuleBridge\Core\Module`
 * - Allowing the host application to bind its own concrete implementation
 *   to that contract using `class_alias`.
 *
 * ─────────────────────────────────────────────────────────────
 * HOW IT WORKS
 * ─────────────────────────────────────────────────────────────
 * At runtime, the host application calls:
 *
 *     Bridge::bind(App\Core\Module::class);
 *
 * This creates an alias:
 *
 *     App\Core\Module
 *         ↳ SchoolPalm\ModuleBridge\Core\Module
 *
 * Any module that extends:
 *
 *     SchoolPalm\ModuleBridge\Core\Module
 *
 * will now transparently use the host application's implementation.
 *
 * ─────────────────────────────────────────────────────────────
 * IMPORTANT CONSTRAINTS
 * ─────────────────────────────────────────────────────────────
 * - Binding can happen ONLY ONCE per request lifecycle
 * - Subsequent calls are ignored silently
 * - The concrete class MUST exist
 * - The concrete class SHOULD extend AbstractModule
 *
 * ─────────────────────────────────────────────────────────────
 * INTENDED USERS
 * ─────────────────────────────────────────────────────────────
 * ✔ SchoolPalm Core Application
 * ✔ schoolpalm/module-sdk (for UI + functionality testing)
 * ✖ Individual modules (they should never bind)
 *
 * @package SchoolPalm\ModuleBridge\Support
 */



final class Bridge
{
    protected static bool $bound = false;

    /** @var Module|null */
    protected static ?Module $runtime = null;

    /**
     * Bind the concrete Module base class to the bridge contract.
     * Called ONCE by the host application.
     */
    public static function bind(string $concreteBaseClass): void
    {
        if (self::$bound) {
            return;
        }

        if (! class_exists($concreteBaseClass)) {
            throw new RuntimeException(
                "Cannot bind module bridge. Class {$concreteBaseClass} not found."
            );
        }

        class_alias(
            $concreteBaseClass,
            \SchoolPalm\ModuleBridge\Core\Module::class
        );

        self::$bound = true;
    }

    /**
     * Register the currently executing module instance.
     * Called at runtime (per request).
     */
    public static function runtime(Module $module): void
    {
        self::$runtime = $module;
    }

    /**
     * Get the active module instance.
     */
    public static function current(): Module
    {
        if (! self::$runtime) {
            throw new RuntimeException(
                'No module runtime registered. Did you forget Bridge::runtime()?'
            );
        }

        return self::$runtime;
    }

    /**
     * Check if a runtime module exists.
     */
    public static function hasRuntime(): bool
    {
        return self::$runtime !== null;
    }

    /**
     * Clear runtime (optional, good for tests / long workers).
     */
    public static function clearRuntime(): void
    {
        self::$runtime = null;
    }
}
