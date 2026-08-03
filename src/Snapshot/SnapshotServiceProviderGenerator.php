<?php

namespace SchoolPalm\ModuleBridge\Snapshot;

use Illuminate\Support\Facades\File;
use SchoolPalm\ModuleBridge\Support\Helper;

class SnapshotServiceProviderGenerator
{
    /**
     * Generate SnapshotServiceProvider from stub
     */
    public function generate(
        string $namespace,
        string $stubPath,
        string $outputPath
    ): void {
        if (!File::exists($stubPath)) {
            throw new \Exception("Stub not found at: {$stubPath}");
        }

        $stub = File::get($stubPath);

        $stub = $this->replacePlaceholders($stub, $namespace);

        File::ensureDirectoryExists(dirname($outputPath));

        File::put($outputPath, $stub);
    }

    /**
     * Replace stub placeholders
     */
    private function replacePlaceholders(string $stub, string $namespace): string
    {
        return str_replace(
            [
                '{{ namespace }}',
                 '{{ module_path }}'
            ],
            [
                 $namespace,
                Helper::namespaceToPath(rtrim($namespace,'\\Backend'))

            ],
            $stub
        );
    }
}