<?php

namespace SchoolPalm\ModuleBridge\Packaging;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SchoolPalm\ModuleBridge\Facades\CreatedRegistry;
use SchoolPalm\ModuleBridge\Manifest\ManifestValidator;
use SchoolPalm\ModuleBridge\Pipeline\ActionRegistry;
use SchoolPalm\ModuleBridge\Support\Helper;

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

    public function publish(?string $transitPath = null): string
    {

        $transitPath =   $transitPath ? $transitPath : config('sdk.modules.transit_path');
        $key  = $this->manifest['module_key'];
        $vendor  = Helper::modulePart($key, 'vendor');
        $name    = Helper::modulePart($key, 'module');
        $context =  Helper::modulePart($key, 'context');
        $version = $this->manifest['version'];

        $zipName = strtolower("{$vendor}-{$context}-{$name}-{$version}.zip");
        $zipFile = $transitPath . '/' . $zipName;


        if (!is_dir($transitPath)) {
            File::ensureDirectoryExists($transitPath);
        }

        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Unable to create zip archive: {$zipName}");
        }

        // add manifest
        $zip->addFile($this->module['manifest'], 'manifest.json');

        // backend
        $this->addFolder($zip, $this->backendPath, 'Backend');

        // frontend dist
        $this->addFolder($zip, $this->frontendDistPath, 'Frontend/dist');

        $zip->close();

        CreatedRegistry::update($this->module_key, ['published' => true]);
        return $zipFile;
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
     * Unpack a module ZIP into a namespace-based folder structure
     * and register it in module-transit.json
     *
     * @param string $zipFilePath
     * @param string|null $destinationPath
     * @return string Full path of the module folder
     * @throws \Exception
     */
    public static function unpackModule(string $zipFilePath, ?string $destinationPath = null,array $extra=[]): string
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

        // --------------------------
        // Extract to temporary folder
        // --------------------------
        $tempPath = $destinationPath . '/temp_unpack_' . uniqid();

        File::ensureDirectoryExists($tempPath);

        if (!$zip->extractTo($tempPath)) {
            $zip->close();
            throw new \Exception("Failed to extract ZIP to temp folder: {$tempPath}");
        }

        $zip->close();

        // --------------------------
        // Read manifest.json
        // --------------------------
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

        // --------------------------
        // VALIDATE MANIFEST (STOP HERE IF INVALID)
        // --------------------------
        $errors = ManifestValidator::validate($manifest);

        if (!empty($errors)) {
            File::deleteDirectory($tempPath);
            throw new \RuntimeException(
                "Invalid Manifest:\n" . implode("\n", $errors)
            );
        }
        $errors = [];
        // --------------------------
        // Build module path
        // --------------------------
        $namespacePath = $manifest['root'];
        $modulePath = rtrim($destinationPath, '/') . '/' . $namespacePath;

        File::ensureDirectoryExists($modulePath);

        // --------------------------
        // Move extracted files
        // --------------------------
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

        // --------------------------
        // Cleanup temp folder
        // --------------------------
        File::deleteDirectory($tempPath);

        // --------------------------
        // Generate pipeline dynamically
        // --------------------------
        $pipelinePath = $modulePath . '/pipeline.json';

        $pipeline = ActionRegistry::generatePipeline();

        Helper::storeJson($pipelinePath, $pipeline);

        // --------------------------
        // Register module in transit
        // --------------------------
        $transit = new ModuleTransit();

        $moduleData = [
            'zip_file' => $zipFilePath,
            'zip_hash' => hash('sha256', $zipFilePath),
            'namespace' => $manifest['namespace'],
            'relative_path' => $manifest['root'],
            'module_path' => $modulePath,
            'pipeline_path' => $pipelinePath,
            'manifest_path' => $modulePath . '/manifest.json',
            'submit_date' => now()->toIso8601String(),
            'plans'=> $extra['plans'] ?? null,
            'extracted' => true,
        ];

        $transit->add($moduleData);

        return $modulePath;
    }



    /**
     * Deploy module from transit into execution directories
     *
     * Backend  -> app/packages/{namespace}
     * Frontend -> public/build/modules/{namespace}
     *
     * @param string $modulePath (result from unpackModule)
     * @return array
     * @throws \Exception
     */
    /**
     * Deploy a module from transit into execution directories
     *
     * @param string $namespace
     * @param string $backendBasePath   e.g. app/packages
     * @param string $frontendBasePath  e.g. public/build/modules
     * @return array
     * @throws \Exception
     */
    public static function deployToExecution2(
        string $namespace,
        string $backendBasePath,
        string $frontendBasePath
    ): array {

        $transit = new ModuleTransit();

        // --------------------------
        // Get module from transit
        // --------------------------
        $module = $transit->find($namespace);

        if (!$module) {
            throw new \Exception("Module not found in transit: {$namespace}");
        }

        if (empty($module['extracted']) || !$module['extracted']) {
            throw new \Exception("Module is not extracted yet");
        }

        // ✅ Use stored full path (fix this in unpack step)
        $modulePath = $module['module_path'];
        $manifestFile = $module['manifest_path'];

        if (!file_exists($manifestFile)) {
            throw new \Exception("Manifest file missing for module");
        }

        // --------------------------
        // Namespace → folder path
        // --------------------------

        $namespacePath = $module['relative_path'];

        $backendExecPath  = rtrim($backendBasePath, '/') . '/' . $namespacePath;
        $frontendExecPath = rtrim($frontendBasePath, '/') . '/' . $namespacePath;

        // Source folders
        $backendSource  = $modulePath . '/Backend';
        $frontendSource = $modulePath . '/Frontend/dist';

        try {


            // --------------------------
            // Deploy Backend
            // --------------------------
            if (is_dir($backendSource)) {

                if (File::exists($backendExecPath)) {
                    File::deleteDirectory($backendExecPath);
                }

                // Create root module folder
                File::ensureDirectoryExists($backendExecPath);

                // ✅ Create Backend subfolder
                $backendTarget = $backendExecPath . DIRECTORY_SEPARATOR . 'Backend';
                File::ensureDirectoryExists($backendTarget);

                // ✅ Copy into Backend folder
                File::copyDirectory($backendSource, $backendTarget);

                // ✅ Keep manifest at module root (NOT inside Backend)
                File::copy($manifestFile, $backendExecPath . DIRECTORY_SEPARATOR . 'manifest.json');
            }

            // --------------------------
            // Deploy Frontend
            // --------------------------
            if (is_dir($frontendSource)) {

                if (File::exists($frontendExecPath)) {
                    File::deleteDirectory($frontendExecPath);
                }

                File::ensureDirectoryExists($frontendExecPath);
                File::copyDirectory($frontendSource, $frontendExecPath);
                // --------------------------
                // Remove from transit
                // --------------------------
                $transit->remove($namespace);
            }
        } catch (\Throwable $e) {

            // --------------------------
            // Rollback (VERY IMPORTANT)
            // --------------------------
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
            'manifest'  => $backendExecPath . DIRECTORY_SEPARATOR . 'manifest.json',
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

        // Keep manifest at root
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
            return $frontendExecPath; // nothing to deploy
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
            //  Deploy independently
            $backendExecPath = self::deployBackend($module, $backendBasePath);
            $frontendExecPath = self::deployFrontend($module, $frontendBasePath);

            //Remove only after success
            $transit->remove($namespace);
        } catch (\Throwable $e) {

            // 🔥 Rollback
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
