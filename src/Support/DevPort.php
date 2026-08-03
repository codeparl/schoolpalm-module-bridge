<?php

namespace SchoolPalm\ModuleBridge\Support;

use Illuminate\Support\Str;

class DevPort
{
    protected static function path(): string
    {
        return realpath(__DIR__) . '/config/ports.json';
    }

    /**
     * Normalize a value.
     *
     * Examples:
     *  id-manager      => id_manager
     *  ID Manager      => id_manager
     *  vendor/module   => vendor_module
     *  student.module  => student_module
     */
    protected static function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    /**
     * Compact identifier.
     *
     * Examples:
     *  id-manager => idmanager
     *  ID Manager => idmanager
     */
    protected static function compact(string $value): string
    {
        return preg_replace(
            '/[^a-z0-9]/',
            '',
            strtolower($value)
        );
    }

    /**
     * Split module key into segments.
     *
     * vendor.context.module
     * =>
     * [vendor, context, module]
     */
    protected static function segments(string $key): array
    {
        return array_values(
            array_filter(
                explode('.', strtolower($key))
            )
        );
    }

    /**
     * Build lookup variants.
     *
     * vendor.context.id-manager
     * =>
     * [
     *   idmanager,
     *   contextidmanager,
     *   vendorcontextidmanager
     * ]
     */
    protected static function buildVariants(string $key): array
    {
        $segments = self::segments($key);

        $variants = [];

        $count = count($segments);

        if ($count >= 1) {
            $variants[] = self::compact(
                $segments[$count - 1]
            );
        }

        if ($count >= 2) {
            $variants[] = self::compact(
                $segments[$count - 2]
                . '_'
                . $segments[$count - 1]
            );
        }

        if ($count >= 3) {
            $variants[] = self::compact(
                implode('_', $segments)
            );
        }

        return array_unique($variants);
    }

    /**
     * Exact identifier match only.
     */
    protected static function matches(string $input, string $key): bool
    {
        $input = self::compact($input);

        foreach (self::buildVariants($key) as $variant) {

            if ($variant === $input) {
                return true;
            }
        }

        return false;
    }

    public static function add(string $module_key): int
    {
        $path = self::path();
        $ports = Helper::loadJson($path);

        if (isset($ports[$module_key])) {
            return $ports[$module_key];
        }

        $last = empty($ports)
            ? config('module-bridge.modules.dev_port_start', 5174)
            : last(array_values($ports));

        $newPort = $last + 1;

        $ports[$module_key] = $newPort;

        Helper::storeJson($path, $ports);

        return $newPort;
    }

    public static function remove(string $module_key): void
    {
        $path = self::path();
        $ports = Helper::loadJson($path);

        if (!isset($ports[$module_key])) {
            return;
        }

        unset($ports[$module_key]);

        Helper::storeJson($path, $ports);
    }

    public static function get(string $input): ?array
    {
        $path = self::path();
        $ports = Helper::loadJson($path);

        foreach ($ports as $key => $port) {

            if (!self::matches($input, $key)) {
                continue;
            }

            $segments = self::segments($key);

            return [
                'port'   => $port,
                'key'    => $key,
                'module' => self::normalize(
                    end($segments)
                ),
            ];
        }

        return null;
    }
}