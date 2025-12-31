<?php

namespace SchoolPalm\ModuleBridge\Support;

/**
 * Class Helper
 *
 * Generic utility functions shared between SchoolPalm core and the Module SDK.
 *
 * PURPOSE:
 * - Provide pure PHP helper functions for string, module, JSON, and path handling
 * - Fully reusable in SDK and core
 * - Stateless and framework-independent
 *
 * LICENSE:
 * MIT License 
 *
 * @package SchoolPalm\ModuleBridge\Support
 */
final class Helper
{
    /* -------------------------------------------------
     | Path / Route Helpers
     |-------------------------------------------------*/

    /**
     * Get a specific segment from a path.
     *
     * Supported keys:
     * - portal, module, action, id
     *
     * @param string      $key     Segment key
     * @param string|null $path    Example: admin/students/edit/5
     * @param bool        $central Whether the route is central-style
     *
     * @return string|null
     */
    public static function getPathSegment(string $key, ?string $path, bool $central = false): ?string
    {
        if (!$path) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        $map = $central
            ? ['module' => 0, 'action' => 1, 'id' => 2]
            : ['portal' => 0, 'module' => 1, 'action' => 2, 'id' => 3];

        return $segments[$map[$key]] ?? null;
    }

    /* -------------------------------------------------
     | JSON Helpers
     |-------------------------------------------------*/

    /**
     * Load and decode a JSON file.
     *
     * @param string      $fileName File name (with or without .json)
     * @param string|null $key      Optional top-level key to extract
     * @param string|null $path     Full file path (overrides default)
     *
     * @return array<mixed>
     */
    public static function loadJson(string $fileName, ?string $key = null, ?string $path = null): array
    {
        if (substr($fileName, -5) !== '.json') {
            $fileName .= '.json';
        }

        $filePath = $path ?? $fileName;

        if (!is_file($filePath) || !is_readable($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        return $key !== null && array_key_exists($key, $data) && is_array($data[$key])
            ? $data[$key]
            : $data;
    }

    /**
     * Store an array as a JSON file.
     *
     * @param string $fileName File name (with or without .json)
     * @param array  $data     Data to encode as JSON
     * @param string $path     Directory path where file will be stored
     *
     * @return bool True on success, false on failure
     */
    public static function storeJson(string $fileName, array $data, string $path): bool
    {
        if (substr($fileName, -5) !== '.json') {
            $fileName .= '.json';
        }

        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            return false;
        }

        $filePath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($content === false) {
            return false;
        }

        return file_put_contents($filePath, $content) !== false;
    }

    /* -------------------------------------------------
     | Module / Folder Helpers
     |-------------------------------------------------*/

    public static function normalizeModuleName(string $module): string
    {
        if (strpos($module, '-') !== false) {
            $module = preg_replace('/-+/', ' ', $module);
        }

        return self::studly($module);
    }

   

    public static function roleFolderName(string $role): string
    {
        $role = str_replace(['-', '_'], ' ', $role);
        $role = self::title($role);
        return str_replace(' ', '', $role);
    }

    public static function moduleFolderName(string|array|object $module): string
    {
        if (is_string($module)) {
            $moduleKey = $module;
        } elseif (is_array($module) && isset($module['module_key'])) {
            $moduleKey = $module['module_key'];
        } elseif (is_object($module) && isset($module->module_key)) {
            $moduleKey = $module->module_key;
        } else {
            throw new \InvalidArgumentException('Invalid module parameter provided.');
        }

        $parts = explode('.', $moduleKey);
        if (count($parts) > 1) array_shift($parts);

        $combined = implode(' ', $parts);
        $combined = str_replace(['.', '_', '-'], ' ', $combined);

        return self::studly($combined);
    }


        /**
     * Get academic levels from encrypted config.
     *
     * Vendors cannot write or modify this file directly.
     *
     * @return array<int,array{label:string,code:string}>
     */
    public static function getAcademicLevels(): array
    {
        // Read and return data
        return EncryptedConfig::read('academic_levels');
    }
    /* -------------------------------------------------
     | String Helpers (Laravel-like, PHP-pure)
     |-------------------------------------------------*/

    public static function contains(string $haystack, string|array $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && strpos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0) {
                return true;
            }
        }
        return false;
    }

    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && substr($haystack, -strlen($needle)) === $needle) {
                return true;
            }
        }
        return false;
    }

    public static function before(string $subject, string $search): string
    {
        if ($search === '') return $subject;
        $pos = strpos($subject, $search);
        return $pos === false ? $subject : substr($subject, 0, $pos);
    }

    public static function after(string $subject, string $search): string
    {
        if ($search === '') return $subject;
        $pos = strpos($subject, $search);
        return $pos === false ? $subject : substr($subject, $pos + strlen($search));
    }

    public static function beforeLast(string $subject, string $search): string
    {
        if ($search === '') return $subject;
        $pos = strrpos($subject, $search);
        return $pos === false ? $subject : substr($subject, 0, $pos);
    }

    public static function afterLast(string $subject, string $search): string
    {
        if ($search === '') return $subject;
        $pos = strrpos($subject, $search);
        return $pos === false ? $subject : substr($subject, $pos + strlen($search));
    }

    public static function between(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') return '';
        return self::before(self::after($subject, $from), $to);
    }

    public static function kebab(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($value)));
    }

    public static function snake(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($value)));
    }

    public static function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        return str_replace(' ', '', ucwords($value));
    }

    public static function title(string $value): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $value));
    }
}
