<?php

namespace SchoolPalm\ModuleBridge\Support;

use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Archive Utility
 *
 * Provides helper methods for creating and extracting zip archives.
 * Designed for use in module packaging, backups, and installer pipelines.
 *
 * Features:
 * - Supports zipping both files and directories
 * - Deterministic file ordering for consistent archives
 * - Optional ignore list when zipping
 * - Safe extraction with Zip Slip protection
 * - Optional deletion of source after zip or zip file after extraction
 *
 * Usage:
 *
 *   Create a zip from a directory
 *  Archive::zip($sourcePath, $zipPath);
 *
 *   Create a zip and delete the source after success
 *  Archive::zip($sourcePath, $zipPath, [], true);
 *
 *   Create a zip while ignoring specific files
 *  Archive::zip($sourcePath, $zipPath, ['.gitignore']);
 *
 *   Extract a zip archive
 *  Archive::unzip($zipPath, $destinationPath);
 *
 *   Extract and delete the zip file after success
 *  Archive::unzip($zipPath, $destinationPath, true);
 *
 * Notes:
 * - Deletion flags are destructive; use with caution.
 * - Source is only deleted after a successful zip operation.
 * - Zip file is only deleted after a successful extraction.
 */
class Archive
{
    /**
     * Create a zip archive from a file or directory.
     *
     * @param string $source Path to file or directory to archive
     * @param string $zipPath Destination zip file path
     * @param array $ignore List of filenames to ignore
     * @param bool $deleteSource Whether to delete the source after successful zipping
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public static function zip(
        string $source,
        string $zipPath,
        array $ignore = [],
        bool $deleteSource = false
    ): void {
        if (!File::exists($source)) {
            throw new \InvalidArgumentException("Source not found: {$source}");
        }

        File::ensureDirectoryExists(dirname($zipPath));

        $zip = new ZipArchive();

        
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create zip: {$zipPath}");
        }

        $sourceReal = realpath($source);

        // Single file
        if (is_file($sourceReal)) {
            $zip->addFile($sourceReal, basename($sourceReal));
            $zip->close();

            if ($deleteSource) {
                File::delete($sourceReal);
            }

            return;
        }

        // Directory
        $files = File::allFiles($sourceReal);

        // Deterministic order
        usort($files, fn($a, $b) => strcmp($a->getRealPath(), $b->getRealPath()));

        foreach ($files as $file) {

            $filename = $file->getFilename();

            if (in_array($filename, $ignore)) {
                continue;
            }

            $filePath = $file->getRealPath();

            $relativePath = ltrim(str_replace($sourceReal, '', $filePath), '/\\');

            $zip->addFile($filePath, $relativePath);
        }

        $zip->close();

        // Delete source after successful zip
        if ($deleteSource) {
            is_file($sourceReal)
                ? File::delete($sourceReal)
                : File::deleteDirectory($sourceReal);
        }
    }

    /**
     * Extract a zip archive safely (prevents Zip Slip attacks).
     *
     * @param string $zipPath Path to the zip file
     * @param string $destination Extraction directory
     * @param bool $deleteZip Whether to delete the zip file after extraction
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public static function unzip(
        string $zipPath,
        string $destination,
        bool $deleteZip = false
    ): void {
        if (!File::exists($zipPath)) {
            throw new \InvalidArgumentException("Zip file not found: {$zipPath}");
        }

        File::ensureDirectoryExists($destination);

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("Cannot open zip: {$zipPath}");
        }

        $destinationReal = realpath($destination);

        for ($i = 0; $i < $zip->numFiles; $i++) {

            $entry = $zip->getNameIndex($i);

            $entry = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $entry);

            $targetPath = $destinationReal . DIRECTORY_SEPARATOR . $entry;

            $targetDir = dirname($targetPath);

            File::ensureDirectoryExists($targetDir);

            $realTargetDir = realpath($targetDir);

            // Prevent Zip Slip
            if ($realTargetDir === false || !str_starts_with($realTargetDir, $destinationReal)) {
                continue;
            }

            if (substr($entry, -1) === DIRECTORY_SEPARATOR) {
                File::ensureDirectoryExists($targetPath);
            } else {
                copy("zip://{$zipPath}#{$entry}", $targetPath);
            }
        }

        $zip->close();

        // Delete zip after successful extraction
        if ($deleteZip) {
            File::delete($zipPath);
        }
    }


    /**
 * Generate a safe archive filename from a dot-notated module key and version.
 *
 * Example:
 *  makeFileName('unnovatebrains.school.student', '1.0.0')
 *  => unnovatebrains-school-student-1.0.0.zip
 *
 * @param string $moduleKey Format: vendor.context.module
 * @param string $version
 * @param string $extension Default: zip
 *
 * @return string
 */
public static function makeFileName(
    string $moduleKey,
    string $version,
    string $extension = 'zip'
): string {
    // Normalize module key
    $moduleKey = strtolower(trim($moduleKey));

    // Replace dots with hyphens (cleaner for filenames)
    $moduleKey = str_replace('.', '-', $moduleKey);

    // Replace any remaining invalid characters
    $moduleKey = preg_replace('/[^a-z0-9\-]/', '_', $moduleKey);

    // Normalize version (keep semver-friendly format)
    $version = strtolower(trim($version));
    $version = preg_replace('/[^a-z0-9\.\-]/', '', $version);
    // Ensure version is prefixed with "v"
if (!str_starts_with($version, 'v')) {
    $version = 'v' . $version;
}

    return "{$moduleKey}-{$version}.{$extension}";
}
}