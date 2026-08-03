<?php

namespace SchoolPalm\ModuleBridge\Context;

use RuntimeException;

class CurrentContext
{
    /**
     * Current executing module.
     */
    protected static ?string $currentModule = null;

    /**
     * Context stack.
     *
     * Useful for nested module execution.
     */
    protected static array $stack = [];

    /*
    |--------------------------------------------------------------------------
    | SET CURRENT MODULE
    |--------------------------------------------------------------------------
    */

    public static function set(string $moduleKey): void
    {
        static::$currentModule = $moduleKey;
    }

    /*
    |--------------------------------------------------------------------------
    | PUSH CONTEXT
    |--------------------------------------------------------------------------
    |
    | Saves current context before switching.
    |
    */

    public static function push(string $moduleKey): void
    {
        static::$stack[] = static::$currentModule;

        static::$currentModule = $moduleKey;
    }

    /*
    |--------------------------------------------------------------------------
    | POP CONTEXT
    |--------------------------------------------------------------------------
    |
    | Restores previous context.
    |
    */

    public static function pop(): ?string
    {
        static::$currentModule = array_pop(static::$stack);

        return static::$currentModule;
    }

    /*
    |--------------------------------------------------------------------------
    | GET CURRENT MODULE
    |--------------------------------------------------------------------------
    */

    public static function current(): ?string
    {
        return static::$currentModule;
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK MODULE
    |--------------------------------------------------------------------------
    */

    public static function is(string $moduleKey): bool
    {
        return static::$currentModule === $moduleKey;
    }

    /*
    |--------------------------------------------------------------------------
    | REQUIRE MODULE
    |--------------------------------------------------------------------------
    */

    public static function require(string $moduleKey): void
    {
        if (!static::is($moduleKey)) {

            throw new RuntimeException(
                sprintf(
                    'Unauthorized internal module access. Expected [%s], current [%s].',
                    $moduleKey,
                    static::$currentModule ?? 'null'
                )
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ENSURE CONTEXT EXISTS
    |--------------------------------------------------------------------------
    */

    public static function ensure(): void
    {
        if (!static::$currentModule) {

            throw new RuntimeException(
                'No active module execution context.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EXECUTE INSIDE CONTEXT
    |--------------------------------------------------------------------------
    |
    | Automatically restores previous context.
    |
    */

    public static function run(
        string $moduleKey,
        callable $callback
    ): mixed {

        static::push($moduleKey);

        try {
            return $callback();
        } finally {
            static::pop();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET STACK
    |--------------------------------------------------------------------------
    */

    public static function stack(): array
    {
        return static::$stack;
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK IF CONTEXT EXISTS
    |--------------------------------------------------------------------------
    */

    public static function has(): bool
    {
        return static::$currentModule !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAR CONTEXT
    |--------------------------------------------------------------------------
    */

    public static function clear(): void
    {
        static::$currentModule = null;
        static::$stack = [];
    }
}