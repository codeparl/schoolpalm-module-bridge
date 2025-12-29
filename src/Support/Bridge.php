<?php

namespace SchoolPalm\ModuleBridge\Support;

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
    /**
     * Indicates whether the bridge has already been bound.
     *
     * This prevents accidental rebinding and class alias collisions.
     */
    protected static bool $bound = false;

    /**
     * Bind the concrete Module base class to the shared bridge Module contract.
     *
     * This method must be called by the host application during bootstrapping
     * (e.g. in a Service Provider).
     *
     * Example:
     * ```
     * Bridge::bind(\SchoolPalm\Core\Module::class);
     * ```
     *
     * @param string $concreteBaseClass Fully-qualified class name of the
     *                                  host application's Module base class.
     *
     * @throws RuntimeException If the provided class does not exist.
     *
     * @return void
     */
    public static function bind(string $concreteBaseClass): void
    {
        // Prevent rebinding once the bridge is established
        if (self::$bound) {
            return;
        }

        // Ensure the concrete class exists before aliasing
        if (! class_exists($concreteBaseClass)) {
            throw new RuntimeException(
                "Cannot bind module bridge. Class {$concreteBaseClass} not found."
            );
        }

        // Alias the host Module implementation to the bridge Module contract
        class_alias(
            $concreteBaseClass,
            \SchoolPalm\ModuleBridge\Core\Module::class
        );

        self::$bound = true;
    }
}
