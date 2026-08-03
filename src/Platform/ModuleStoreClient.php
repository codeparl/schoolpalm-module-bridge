<?php

namespace SchoolPalm\ModuleBridge\Platform;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use SchoolPalm\ModuleBridge\Facades\SnapshotRegistry;
use SchoolPalm\ModuleBridge\Support\Helper;
use SchoolPalm\ModuleBridge\Support\Archive;
use Illuminate\Http\Client\Response;
class ModuleStoreClient
{
    protected string $baseUrl;
    protected string $endpoint;
    protected string $downloadPath;
    protected string $executionPath;

    public function __construct(
        ?string $baseUrl = null,
        ?string $downloadPath = null,
        ?string $executionPath = null
    ) {
        $this->baseUrl = rtrim($baseUrl ?? config('sdk.app.store.url'), '/');
        $this->endpoint = "{$this->baseUrl}/api/sdk/modules";

        $this->downloadPath = $downloadPath ?? Helper::dataFolder() . 'snapshots/';
        $this->executionPath = $executionPath ?? base_path('modules/runtime/');

        File::ensureDirectoryExists($this->downloadPath);
        File::ensureDirectoryExists($this->executionPath);
    }

    /**
     * MAIN ENTRY: resolve module (stop early if exists)
     */
    public function resolve(string $moduleKey, string $version): string
    {
        // 1. Check registry first (fastest path)
        
        $snapshot = SnapshotRegistry::get($moduleKey, $version);

        $zipPath = $snapshot['path'] ?? null;
           
        if ($zipPath && file_exists($zipPath)) {
            $this->install($moduleKey, $version, $zipPath);
            return $this->executionPath;
        }

  
        // 2. If not exists → download
        $zipPath = $this->download($moduleKey, $version, true);

        if (!$zipPath) {
            throw new \RuntimeException("Failed to resolve module: {$moduleKey}@{$version}");
        }

        // 3. Install after download
         $this->install($moduleKey, $version, $zipPath);

        return $this->executionPath;
    }

    /**
     * Download snapshot
     */
    public function download(string $moduleKey, string $version, bool $register = true): ?string
    {
        $fileName = $this->makeFileName($moduleKey, $version);
        $savePath = $this->downloadPath . $fileName;

        $url = "{$this->endpoint}/{$moduleKey}/{$version}/snapshot";
              
        try {
            /** @var Response $response */
            $response = Http::timeout(120)
                ->retry(3, 500)
                ->withHeaders([
                    'Accept' => 'application/zip',
                ])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            File::put($savePath, $response->body());

            if (!file_exists($savePath)) {
                return null;
            }

           

            return $savePath;

        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Install module into execution path
     */
    public function install(string $moduleKey, string $version, string $zipPath)
    {
        $path  = str_replace('.','/',$moduleKey);
        $targetPath = $this->executionPath . "{$path}";
        if($this->isInstalled($moduleKey,$version))
            return;
        File::ensureDirectoryExists($targetPath);
        Archive::unzip($zipPath,$targetPath);
    
                $namespace  =  Helper::moduleKeyToNamespace($moduleKey);
                SnapshotRegistry::add($moduleKey, $version, [
                    'module_key' => $moduleKey,
                    'namespace'=> $namespace ,
                    'zip_path' => $zipPath,
                    'execution_path'=>$targetPath,
                    'root'=>$this->executionPath,
                    'source' => 'remote-store',
                    'created_at' => now()->toDateTimeString(),
                ]);
            
        
    }

    /**
     * Check if already installed
     */
    public function isInstalled(string $moduleKey,string $version): bool
    {
        $path  = str_replace('.','/',$moduleKey);
        $marker = $this->executionPath . "{$path}";
        $module= SnapshotRegistry::get($moduleKey, $version);
        return file_exists($marker) && $module;
    }

    /**
     * Build deterministic filename
     */
    protected function makeFileName(string $moduleKey, string $version): string
    {
        $moduleKey = strtolower(str_replace('.', '-', $moduleKey));

        if (!str_starts_with($version, 'v')) {
            $version = 'v' . $version;
        }

        return "{$moduleKey}-{$version}.zip";
    }
}