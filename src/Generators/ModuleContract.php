<?php

namespace SchoolPalm\ModuleBridge\Generators;

use SchoolPalm\ModuleBridge\Manifest\ManifestFactory;
use SchoolPalm\ModuleBridge\Profiles\ContractProfile;
use SchoolPalm\ModuleBridge\Support\Helper;
use Illuminate\Support\Str;

class ModuleContract
{
    private ContractScaffoldGenerator $contractGenerator;
    private FacadeGenerator $facadeGenerator;

    public function __construct()
    {
        $this->contractGenerator = new ContractScaffoldGenerator();
        $this->facadeGenerator = new FacadeGenerator();
    }

    /**
     * Execute multiple contracts (existing method)
     */
    public function executeContractsImplementation(array $contracts, ContractProfile $profile): void
    {
        foreach ($contracts as $contractInterface) {
            $this->executeContract($contractInterface, $profile, null);
        }
    }

    /**
     * Execute a single contract: generate service, events, and facade
     */
    public function executeContract(
        string $contractInterface,
        ContractProfile $profile,
        ?string $customPath = null,
    ): void {

        if (!interface_exists($contractInterface)) {
            return;
        }

        $reflection = new \ReflectionClass($contractInterface);

        if (!$reflection->isInterface()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve manifest path
        |--------------------------------------------------------------------------
        */
        $manifestPath = $this->resolveManifestPath($reflection);

        $shortName = $reflection->getShortName();

        // Remove "Contract"
        $baseName = str_ends_with($shortName, 'Contract')
            ? substr($shortName, 0, -8)
            : $shortName;

        $implementationShortName = $baseName . 'Service';

        $implementationNamespace =
            Helper::beforeLast($contractInterface, 'Contracts') . 'Services';

        $implementationClass =
            $implementationNamespace . '\\' . $implementationShortName;

        /*
        |--------------------------------------------------------------------------
        | Paths
        |--------------------------------------------------------------------------
        */
        $backendPath = $customPath
            ?? Helper::beforeLast($reflection->getFileName(), 'Backend') . 'Backend';

        $outputPath = $customPath ?? rtrim(
            $backendPath . DIRECTORY_SEPARATOR . 'Services',
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR . $implementationShortName . '.php';

        $facadePath = Str::before($backendPath, 'Backend');
        $facadePath .= 'Backend';
        $facadePath = rtrim(
            $facadePath . DIRECTORY_SEPARATOR . 'Facades',
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR . $implementationShortName . '.php';

        /*
        |--------------------------------------------------------------------------
        | Generate Service (EXTENDS AbstractService) WITH EVENTS
        |--------------------------------------------------------------------------
        */
        $this->contractGenerator->generate(
            contractInterface: $contractInterface,
            implementationClass: $implementationClass,
            outputPath: $outputPath,
            profile: $profile,
            manifestPath: $manifestPath  // Pass manifest path
        );

        /*
        |--------------------------------------------------------------------------
        | Generate Facade (UNCHANGED)
        |--------------------------------------------------------------------------
        */
        $facadeNamespace =
            Helper::beforeLast($contractInterface, 'Contracts') . 'Facades';

        $facadeClass = $facadeNamespace . '\\' . $implementationShortName;

        $this->facadeGenerator->generate(
            $facadeClass,
            $contractInterface,
            $facadePath,
            $contractInterface
        );
    }

    /**
     * Resolve the manifest path from the contract reflection.
     * 
     * Tries to find the manifest by traversing up from the contract file.
     */
    protected function resolveManifestPath(\ReflectionClass $reflection): string
    {
        $fileName = $reflection->getFileName();
        
        // Start from the directory of the contract file
        $currentDir = dirname($fileName);
        
        // Go up until we find manifest.json or hit the root
        while ($currentDir !== dirname($currentDir)) {
            $manifestPath = $currentDir . DIRECTORY_SEPARATOR . 'manifest.json';
            if (file_exists($manifestPath)) {
                return $manifestPath;
            }
            
            // Go up one level
            $currentDir = dirname($currentDir);
        }
        
        // Fallback: try the configured modules root
        $root = config('sdk.modules.root');
        if ($root) {
            // Try to find the module root by looking for the contract path
            $moduleRoot = $this->findModuleRoot($reflection);
            if ($moduleRoot) {
                return $moduleRoot . DIRECTORY_SEPARATOR . 'manifest.json';
            }
            
            // Last fallback: use the configured path
            $fallbackPath = $root . DIRECTORY_SEPARATOR . 'manifest.json';
            if (file_exists($fallbackPath)) {
                return $fallbackPath;
            }
        }
        
        // Default fallback
        return null;
    }

    /**
     * Find the module root by traversing up from the contract file.
     */
    protected function findModuleRoot(\ReflectionClass $reflection): ?string
    {
        $fileName = $reflection->getFileName();
        $currentDir = dirname($fileName);
        
        // Look for a directory that contains 'Backend' or 'manifest.json'
        while ($currentDir !== dirname($currentDir)) {
            // Check if this directory has a manifest.json
            if (file_exists($currentDir . DIRECTORY_SEPARATOR . 'manifest.json')) {
                return $currentDir;
            }
            
            // Check if this directory looks like a module root (has Backend folder)
            if (is_dir($currentDir . DIRECTORY_SEPARATOR . 'Backend')) {
                // Check one level up for manifest
                $parentDir = dirname($currentDir);
                if (file_exists($parentDir . DIRECTORY_SEPARATOR . 'manifest.json')) {
                    return $parentDir;
                }
            }
            
            $currentDir = dirname($currentDir);
        }
        
        return null;
    }
}