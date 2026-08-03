<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Contracts\Host;

/**
 * Interface StorageHost
 *
 * Framework-agnostic filesystem storage contract exposed by the SchoolPalm host to modules.
 *
 * All paths are host-managed and must be treated as opaque identifiers by modules.
 *
 * @package SchoolPalm\ModuleBridge\Contracts\Host
 */
interface StorageHost
{
    /**
     * Store contents at the given storage path.
     *
     * @param string $path Storage path / key.
     * @param string $contents File contents.
     * @return bool True on success, false otherwise.
     */
    public function put(string $path, string $contents): bool;

    /**
     * Retrieve stored contents.
     *
     * @param string $path Storage path / key.
     * @return string File contents.
     */
    public function get(string $path): string;

    /**
     * Delete the stored file at the given path.
     *
     * @param string $path Storage path / key.
     * @return bool True on success, false otherwise.
     */
    public function delete(string $path): bool;

    /**
     * Check if a file exists at the given path.
     *
     * @param string $path Storage path / key.
     * @return bool True if exists, false otherwise.
     */
    public function exists(string $path): bool;

    /**
     * Generate a URL for a stored file.
     *
     * @param string $path Storage path / key.
     * @return string Fully-qualified URL.
     */
    public function url(string $path): string;

    /**
     * Get the absolute filesystem path for a stored file.
     *
     * @param string $path Storage path / key.
     * @return string Absolute filesystem path.
     */
    public function path(string $path): string;
}

