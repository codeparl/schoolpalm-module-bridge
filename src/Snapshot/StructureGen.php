<?php

namespace SchoolPalm\ModuleBridge\Snapshot;

use Illuminate\Support\Facades\File;
use RuntimeException;
use SchoolPalm\ModuleBridge\Context\ModuleRuntimeContext;
use SchoolPalm\ModuleBridge\Support\Helper;

class StructureGen  
{

    public function generate(ModuleRuntimeContext $context):array
    {
        $manifest = $context->manifest;
        
        $module_relative_path  =  $manifest->root().'/Backend';
       $root = $context->snapshot_root.'/'.$module_relative_path;
        $paths = [
            'root'=>$root,
            'contracts'   => "{$root}/Contracts",
            'dtos'      => "{$root}/DTOs",
            'events'      => "{$root}/Events",
            'schemas'  => "{$root}/Database/schemas",
            'providers'   => "{$root}/Providers",
            'services'   => "{$root}/Services",
            'facades'   => "{$root}/Facades",
            'data'   => "{$root}/data",

        ];


        $this->ensureDirectory($paths);
        $file = $context->snapshot_root.'/'.$manifest->root() .'/structure.json';
        $paths['structure.json'] = $file;
        Helper::storeJson($file,$paths);
        return $paths;
    }

        private function ensureDirectory(array $paths)
    {
        foreach ($paths as $key => $path) {
            File::ensureDirectoryExists($path);
        }
    }

    /**
     * Resolve DTOs from manifest or fallback to scanning
     */
    protected function resolveDTOs(array $manifest, string $modulePath): array
    {
        if (!empty($manifest['dtos'])) {
            return $manifest['dtos'];
        }

        // fallback scan
        return $this->scanNamespace($modulePath . '/DTOs');
    }

    protected function scanNamespace(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $classes = [];

        foreach (glob($path . '/*.php') as $file) {
            $class = $this->extractClassFromFile($file);
            if ($class) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    protected function extractClassFromFile(string $file): ?string
    {
        $content = file_get_contents($file);

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+(.+?);/', $content, $ns)) {
            $namespace = trim($ns[1]);
        }

        if (preg_match('/(class|interface)\s+(\w+)/', $content, $cl)) {
            $class = trim($cl[2]);
        }

        return ($namespace && $class) ? $namespace . '\\' . $class : null;
    }

    protected function loadMocks(string $modulePath): array
    {
        $file = $modulePath . '/snapshot.php';

        if (!file_exists($file)) {
            return [];
        }

        $config = require $file;

        return $config['mocks'] ?? [];
    }

    protected function fail(string $message)
    {
        throw new RuntimeException($message);
    }
}