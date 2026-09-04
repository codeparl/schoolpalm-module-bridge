<?php

namespace SchoolPalm\ModuleBridge\Packaging;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SchoolPalm\ModuleBridge\Facades\CreatedRegistry;
use SchoolPalm\ModuleBridge\Manifest\ManifestValidator;
use SchoolPalm\ModuleBridge\Pipeline\ActionRegistry;
use SchoolPalm\ModuleBridge\Support\Helper;
use Throwable;
use ZipArchive;

class ModulePackager
{
    private array $module;
    private array $manifest;

    private string $modulePath;
    private string $backendPath;
    private string $frontendDistPath;

    public function __construct(
        private string $module_key
    ) {
        $this->module = CreatedRegistry::get($module_key);

        $this->modulePath = Str::beforeLast($this->module['path'], DIRECTORY_SEPARATOR);
        $this->backendPath = $this->modulePath . '/Backend';
        $this->frontendDistPath = $this->modulePath . '/Frontend/dist';

        $this->manifest = Helper::loadJson($this->module['manifest']);
    }

    /**
     * Build and publish the module package using fallback strategy:
     * 1. Try dev-server build (http://127.0.0.1:5199/build)
     * 2. If dev-server is offline, fallback to existing Frontend/dist folder
     * 3. Fail if neither is available
     *
     * Returns clear publish metrics including target paths, formatted file size, and method used.
     *
     * @param string|null $devServerUrl Custom dev server URL
     * @param string|null $transitPath Custom transit ZIP path
     * @return array Standardized response payload for dev tools & Vue components
     * @throws RuntimeException
     */
    public function buildAndPublish(?string $devServerUrl = null, ?string $transitPath = null): array
    {
        $devServerUrl = rtrim($devServerUrl ?: config('sdk.modules.dev_server_url', 'http://127.0.0.1:5199'), '/');
        $buildOutput = '';
        $builtViaServer = false;
        $buildStrategy = 'dev-server';

        // -------------------------------------------------------------
        // Step 1: Attempt build via Dev Server
        // -------------------------------------------------------------
        try {
            set_time_limit(0);

            $response = Http::timeout(600)
                ->acceptJson()
                ->contentType('application/json')
                ->post("{$devServerUrl}/build", [
                    'module' => $this->module_key,
                ]);

            $responseData = $response->json() ?? [];
            $statusCode = $response->status();

            // Evaluate success payload safely across variations
            $isSuccessPayload = false;
            if (isset($responseData['success'])) {
                $isSuccessPayload = (bool) $responseData['success'];
            } elseif (isset($responseData['status'])) {
                $isSuccessPayload = in_array(strtolower((string) $responseData['status']), ['success', 'ok', 'completed', 'true'], true);
            } elseif (isset($responseData['ok'])) {
                $isSuccessPayload = (bool) $responseData['ok'];
            } else {
                $isSuccessPayload = $response->successful();
            }

            if ($response->successful() && $isSuccessPayload) {
                $builtViaServer = true;
                $buildOutput = $responseData['output']
                    ?? $responseData['message']
                    ?? 'Build completed successfully via dev-server.';

                // Micro-delay (500ms) to allow OS file handles to flush on disk before packaging
                usleep(500000);
            } else {
                $errorMessage = $responseData['message'] ?? $responseData['error'] ?? 'Unknown dev-server response';
                $buildOutput = "Dev server build returned failure (HTTP {$statusCode}): {$errorMessage}";
            }
        } catch (Throwable $e) {
            $buildOutput = "Dev server unreachable (" . $e->getMessage() . ").";
        }

        // -------------------------------------------------------------
        // Step 2 & 3: Fallback check for Frontend/dist or Fail
        // -------------------------------------------------------------
        if (!$builtViaServer) {
            if (is_dir($this->frontendDistPath) && count(File::files($this->frontendDistPath)) > 0) {
                $buildStrategy = 'dist-fallback';
                $buildOutput .= " Fallback to existing dist directory [{$this->frontendDistPath}].";
            } else {
                throw new RuntimeException(
                    "Module publish failed for [{$this->module_key}]: {$buildOutput} | No pre-built 'Frontend/dist' directory was found."
                );
            }
        }

        // -------------------------------------------------------------
        // Step 4: Package & Publish ZIP
        // -------------------------------------------------------------
        $zipFile = $this->publish($transitPath);
        $rawBytes = file_exists($zipFile) ? filesize($zipFile) : 0;
        $savedDirectory = dirname($zipFile);
        $fileName = basename($zipFile);

        return [
            'success'         => true,
            'module'          => $this->module_key,
            'zip_file'        => $zipFile,
            'file_name'       => $fileName,
            'file_size'       => $rawBytes,
            'file_size_human' => $this->formatBytes($rawBytes),
            'download_url'    => url('/sdk/ajax/download-module'),
            'target_dir'      => $savedDirectory,
            'build_strategy'  => $buildStrategy,
            'output'          => $buildOutput,
            'published'       => true,
            'details'         => [
                'archive'      => $fileName,
                'path'         => $zipFile,
                'download_url' => url('/sdk/ajax/download-module'),
                'directory'    => $savedDirectory,
                'size'         => $this->formatBytes($rawBytes) . " ({$rawBytes} bytes)",
                'built_via'    => $buildStrategy,
            ],
        ];
    }
    public function publish(?string $transitPath = null): string
    {
        $transitPath = $transitPath ?: config('sdk.modules.transit_path');

        $key      = $this->manifest['module_key'];
        $vendor   = Helper::modulePart($key, 'vendor');
        $name     = Helper::modulePart($key, 'module');
        $context  = Helper::modulePart($key, 'context');
        $version  = $this->manifest['version'];

        /*
     * Published package:
     * This is the compiled/production-ready module.
     *
     * Example:
     * vendor-context-module-1.2.0-published-20260826-110635.zip
     */
        $timestamp = now()->format('Ymd-His');

        $zipName = strtolower(
            "{$vendor}-{$context}-{$name}-{$version}-published-{$timestamp}.zip"
        );

        $zipFile = rtrim($transitPath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $zipName;

        if (!is_dir($transitPath)) {
            File::ensureDirectoryExists($transitPath);
        }

        $zip = new ZipArchive();

        if (
            $zip->open(
                $zipFile,
                ZipArchive::CREATE | ZipArchive::OVERWRITE
            ) !== true
        ) {
            throw new \Exception(
                "Unable to create zip archive: {$zipName}"
            );
        }

        // -------------------------------------------------------------
        // Manifest
        // -------------------------------------------------------------
        $zip->addFile(
            $this->module['manifest'],
            'manifest.json'
        );

        // -------------------------------------------------------------
        // Backend
        // -------------------------------------------------------------
        $this->addFolder(
            $zip,
            $this->backendPath,
            'Backend'
        );

        // -------------------------------------------------------------
        // Compiled Frontend
        // -------------------------------------------------------------
        $this->addFolder(
            $zip,
            $this->frontendDistPath,
            'Frontend/dist'
        );

        if (!$zip->close()) {
            throw new \Exception(
                "Unable to finalize published ZIP archive: {$zipFile}"
            );
        }

        CreatedRegistry::update(
            $this->module_key,
            ['published' => true]
        );

        return $zipFile;
    }


    public function export(?string $transitPath = null): string
    {
        $transitPath = $transitPath ?: config('sdk.exports_path');

        // Ensure directory exists
        if (!is_dir($transitPath)) {
            File::ensureDirectoryExists(
                $transitPath,
                0755,
                true
            );
        }

        // Normalize path separators
        $transitPath = str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $transitPath
        );

        $key     = $this->manifest['module_key'];
        $vendor  = Helper::modulePart($key, 'vendor');
        $name    = Helper::modulePart($key, 'module');
        $context = Helper::modulePart($key, 'context');
        $version = $this->manifest['version'];

        $path = Helper::namespaceToPath(
            Str::beforeLast(
                $this->manifest['namespace'],
                '\\Backend'
            )
        );

        /*
     * Raw source package:
     * This contains the uncompiled module source.
     *
     * Example:
     * vendor-context-module-1.2.0-raw-20260826-110635.zip
     */
        $timestamp = now()->format('Ymd-His');

        $zipName = strtolower(
            "{$vendor}-{$context}-{$name}-{$version}-raw-{$timestamp}.zip"
        );

        $zipFile = $transitPath
            . DIRECTORY_SEPARATOR
            . $zipName;

        // Remove only if an extremely unlikely same-second collision occurs
        if (file_exists($zipFile)) {
            @unlink($zipFile);
        }

        $zip = new ZipArchive();

        $openStatus = $zip->open(
            $zipFile,
            ZipArchive::CREATE | ZipArchive::OVERWRITE
        );

        if ($openStatus !== true) {
            throw new \Exception(
                "Unable to create zip archive [{$zipName}]. " .
                    "ZipArchive open status code: {$openStatus}"
            );
        }

        // -------------------------------------------------------------
        // Add entire RAW module directory
        // -------------------------------------------------------------
        $this->addFolder(
            $zip,
            $this->modulePath,
            $path
        );

        // -------------------------------------------------------------
        // RAW export must NOT contain compiled Frontend/dist
        // -------------------------------------------------------------
        $this->deleteArchiveEntry(
            $path . '/Frontend/dist',
            $zip
        );

        // -------------------------------------------------------------
        // Finalize archive
        // -------------------------------------------------------------
        if (!$zip->close()) {
            throw new \Exception(
                "ZipArchive failed to write temporary file to " .
                    "[{$transitPath}]. " .
                    "Check directory write permissions or ensure the " .
                    "system temp folder is accessible."
            );
        }

        CreatedRegistry::update(
            $this->module_key,
            ['exported' => true]
        );

        return $zipFile;
    }


    public function deleteArchiveEntry(string|array $entry, string|ZipArchive $zipPath)
    {
        $zip = $zipPath;
        if (is_string($zipPath)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \Exception("Could not open ZIP");
            }
        }

        $entries = function ($entry) {
            $entry = is_string($entry) ? [$entry] : $entry;
            $entries = [];
            foreach ($entry as $e) {
                $entries[] = rtrim(str_replace('\\', '/', $e), '/');
            }
            return $entries;
        };
        $entries = $entries($entry);

        for ($i = $zip->numFiles - 1; $i >= 0; $i--) {
            $name = str_replace('\\', '/', $zip->getNameIndex($i));
            foreach ($entries as $e) {
                if ($name === $e || str_starts_with($name, $e)) {
                    $zip->deleteIndex($i);
                }
            }
        }

        if (is_string($zipPath)) {
            $zip->close();
        }
    }

    protected function addFolder(ZipArchive $zip, string $folder, string $zipPath): void
    {
        if (!is_dir($folder)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = $zipPath . '/' . substr($filePath, strlen($folder) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    /**
     * Format raw file size bytes to human readable format.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Unpack a module ZIP into a namespace-based folder structure
     * and register it in module-transit.json
     *
     * @param string $zipFilePath
     * @param string|null $destinationPath
     * @param array $extra
     * @return string Full path of the module folder
     * @throws \Exception
     */
    public static function unpackModule(string $zipFilePath, ?string $destinationPath = null, array $extra = []): string
    {
        $destinationPath = $destinationPath ?: getcwd();

        if (!file_exists($zipFilePath)) {
            throw new \Exception("Module ZIP file does not exist: {$zipFilePath}");
        }

        if (!is_dir($destinationPath)) {
            File::ensureDirectoryExists($destinationPath);
        }

        $zip = new \ZipArchive();

        if ($zip->open($zipFilePath) !== true) {
            throw new \Exception("Unable to open ZIP file: {$zipFilePath}");
        }

        // Extract to temporary folder
        $tempPath = $destinationPath . '/temp_unpack_' . uniqid();
        File::ensureDirectoryExists($tempPath);

        if (!$zip->extractTo($tempPath)) {
            $zip->close();
            throw new \Exception("Failed to extract ZIP to temp folder: {$tempPath}");
        }

        $zip->close();

        // Read manifest.json
        $manifestFile = $tempPath . '/manifest.json';

        if (!file_exists($manifestFile)) {
            File::deleteDirectory($tempPath);
            throw new \Exception("manifest.json not found in module ZIP");
        }

        $manifest = Helper::loadJson($manifestFile);

        if (!isset($manifest['namespace'])) {
            File::deleteDirectory($tempPath);
            throw new \Exception("Namespace not defined in manifest.json");
        }

        // Validate Manifest
        $errors = ManifestValidator::validate($manifest);

        if (!empty($errors)) {
            File::deleteDirectory($tempPath);
            throw new \RuntimeException(
                "Invalid Manifest:\n" . implode("\n", $errors)
            );
        }

        // Build module path
        $namespacePath = $manifest['root'];
        $modulePath = rtrim($destinationPath, '/') . '/' . $namespacePath;

        File::ensureDirectoryExists($modulePath);

        // Move extracted files
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $fullPath = str_replace('\\', '/', $file->getPathname());
            $tempNormalized = str_replace('\\', '/', $tempPath);
            $relativePath = ltrim(str_replace($tempNormalized, '', $fullPath), '/');
            $destPath = $modulePath . '/' . $relativePath;

            if ($file->isDir()) {
                File::ensureDirectoryExists($destPath, 0777);
            } else {
                File::ensureDirectoryExists(dirname($destPath), 0777);
                rename($file->getPathname(), $destPath);
            }
        }

        // Cleanup temp folder
        File::deleteDirectory($tempPath);

        // Generate pipeline dynamically
        $pipelinePath = $modulePath . '/pipeline.json';
        $pipeline = ActionRegistry::generatePipeline();

        Helper::storeJson($pipelinePath, $pipeline);

        // Register module in transit
        $transit = new ModuleTransit();

        $moduleData = [
            'zip_file'      => $zipFilePath,
            'zip_hash'      => hash('sha256', $zipFilePath),
            'namespace'     => $manifest['namespace'],
            'relative_path' => $manifest['root'],
            'module_path'   => $modulePath,
            'pipeline_path' => $pipelinePath,
            'manifest_path' => $modulePath . '/manifest.json',
            'submit_date'   => now()->toIso8601String(),
            'plans'         => $extra['plans'] ?? null,
            'extracted'     => true,
        ];

        $transit->add($moduleData);

        return $modulePath;
    }

    public static function deployToExecution2(
        string $namespace,
        string $backendBasePath,
        string $frontendBasePath
    ): array {
        $transit = new ModuleTransit();

        $module = $transit->find($namespace);

        if (!$module) {
            throw new \Exception("Module not found in transit: {$namespace}");
        }

        if (empty($module['extracted']) || !$module['extracted']) {
            throw new \Exception("Module is not extracted yet");
        }

        $modulePath = $module['module_path'];
        $manifestFile = $module['manifest_path'];

        if (!file_exists($manifestFile)) {
            throw new \Exception("Manifest file missing for module");
        }

        $namespacePath = $module['relative_path'];

        $backendExecPath  = rtrim($backendBasePath, '/') . '/' . $namespacePath;
        $frontendExecPath = rtrim($frontendBasePath, '/') . '/' . $namespacePath;

        $backendSource  = $modulePath . '/Backend';
        $frontendSource = $modulePath . '/Frontend/dist';

        try {
            // Deploy Backend
            if (is_dir($backendSource)) {
                if (File::exists($backendExecPath)) {
                    File::deleteDirectory($backendExecPath);
                }

                File::ensureDirectoryExists($backendExecPath);
                $backendTarget = $backendExecPath . DIRECTORY_SEPARATOR . 'Backend';
                File::ensureDirectoryExists($backendTarget);

                File::copyDirectory($backendSource, $backendTarget);
                File::copy($manifestFile, $backendExecPath . DIRECTORY_SEPARATOR . 'manifest.json');
            }

            // Deploy Frontend
            if (is_dir($frontendSource)) {
                if (File::exists($frontendExecPath)) {
                    File::deleteDirectory($frontendExecPath);
                }

                File::ensureDirectoryExists($frontendExecPath);
                File::copyDirectory($frontendSource, $frontendExecPath);

                $transit->remove($namespace);
            }
        } catch (\Throwable $e) {
            if (File::exists($backendExecPath)) {
                File::deleteDirectory($backendExecPath);
            }

            if (File::exists($frontendExecPath)) {
                File::deleteDirectory($frontendExecPath);
            }

            throw $e;
        }

        return [
            'backend_path'  => $backendExecPath,
            'manifest'      => $backendExecPath . DIRECTORY_SEPARATOR . 'manifest.json',
            'frontend_path' => $frontendExecPath,
            'namespace'     => $namespace,
        ];
    }

    public static function deployBackend(
        array $module,
        string $backendBasePath
    ): string {
        $namespacePath = $module['relative_path'];
        $modulePath    = $module['module_path'];
        $manifestFile  = $module['manifest_path'];

        $backendExecPath = rtrim($backendBasePath, '/') . '/' . $namespacePath;
        $backendSource   = $modulePath . '/Backend';

        if (!is_dir($backendSource)) {
            return $backendExecPath;
        }

        if (File::exists($backendExecPath)) {
            File::deleteDirectory($backendExecPath);
        }

        File::ensureDirectoryExists($backendExecPath);

        $backendTarget = $backendExecPath . DIRECTORY_SEPARATOR . 'Backend';
        File::ensureDirectoryExists($backendTarget);

        File::copyDirectory($backendSource, $backendTarget);

        File::copy(
            $manifestFile,
            $backendExecPath . DIRECTORY_SEPARATOR . 'manifest.json'
        );

        return $backendExecPath;
    }

    public static function deployFrontend(
        array $module,
        string $frontendBasePath
    ): string {
        $namespacePath = $module['relative_path'];
        $modulePath    = $module['module_path'];

        $frontendExecPath = rtrim($frontendBasePath, '/') . '/' . $namespacePath;
        $frontendSource   = $modulePath . '/Frontend/dist';

        if (!is_dir($frontendSource)) {
            return $frontendExecPath;
        }

        if (File::exists($frontendExecPath)) {
            File::deleteDirectory($frontendExecPath);
        }

        File::ensureDirectoryExists($frontendExecPath);
        File::copyDirectory($frontendSource, $frontendExecPath);

        return $frontendExecPath;
    }

    public static function deployToExecution(
        string $namespace,
        string $backendBasePath,
        string $frontendBasePath
    ): array {
        $transit = new ModuleTransit();

        $module = $transit->find($namespace);

        if (!$module) {
            throw new \Exception("Module not found in transit: {$namespace}");
        }

        if (empty($module['extracted'])) {
            throw new \Exception("Module is not extracted yet");
        }

        if (!file_exists($module['manifest_path'])) {
            throw new \Exception("Manifest file missing for module");
        }

        $backendExecPath  = null;
        $frontendExecPath = null;

        try {
            $backendExecPath = self::deployBackend($module, $backendBasePath);
            $frontendExecPath = self::deployFrontend($module, $frontendBasePath);

            $transit->remove($namespace);
        } catch (\Throwable $e) {
            if ($backendExecPath && File::exists($backendExecPath)) {
                File::deleteDirectory($backendExecPath);
            }

            if ($frontendExecPath && File::exists($frontendExecPath)) {
                File::deleteDirectory($frontendExecPath);
            }

            throw $e;
        }

        return [
            'backend_path'  => $backendExecPath,
            'manifest'      => $backendExecPath
                ? $backendExecPath . DIRECTORY_SEPARATOR . 'manifest.json'
                : null,
            'frontend_path' => $frontendExecPath,
            'namespace'     => $namespace,
        ];
    }
}
