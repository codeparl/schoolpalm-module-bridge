<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Support;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final class ModuleBridgeConfigSynchronizer
{
    private const DEFAULT_PACKAGE_MAP = [
        'app-logger' => 'logger',
        'document-builder' => 'documents',
        'message-delivery' => 'messages',
        'cache-store' => 'cache',
        'app-settings' => 'settings',
        'queued-jobs' => 'queues',
    ];

    public function __construct(
        private readonly Filesystem $files
    ) {}

    /**
     * Scan SchoolPalm packages and synchronize bridge configuration files.
     *
     * @return array<string, int|string>
     */
    public function synchronize(): array
    {
        $vendorRoot = $this->resolveVendorDirectory();
        $bridgeConfigDirectory = $this->bridgeConfigDirectory();

        $this->files->ensureDirectoryExists($bridgeConfigDirectory);

        $sources = $this->discoverPackageConfigSources($vendorRoot);

        $generated = 0;
        $skipped = 0;
        $generatedFiles = [];

        foreach ($sources as $source) {
            $targetPath = $bridgeConfigDirectory . '/' . $source['target'];
            $contents = $this->prepareConfigContents($source['package'], $source['target'], $source['source']);

            if ($this->files->exists($targetPath) && $this->files->get($targetPath) === $contents) {
                $skipped++;
                continue;
            }

            $this->files->put($targetPath, $contents);
            $generated++;
            $generatedFiles[] = $targetPath;
        }

        return [
            'discovered' => count($sources),
            'generated' => $generated,
            'skipped' => $skipped,
            'directory' => $bridgeConfigDirectory,
            'generated_files' => $generatedFiles,
        ];
    }

    /**
     * Load generated bridge configuration files into runtime config.
     *
     * @param array<int, string> $generatedFiles
     */
    public function loadGeneratedConfigs(array $generatedFiles): void
    {
        foreach ($generatedFiles as $filePath) {
            if (! $this->files->exists($filePath)) {
                continue;
            }

            $bridgeFileName = basename($filePath, '.php');
            $configKey = 'module-bridge.' . $bridgeFileName;
            $config = require $filePath;

            if (! is_array($config)) {
                continue;
            }

            config()->set($configKey, array_replace_recursive(config($configKey, []), $config));

            $packageMap = array_merge(self::DEFAULT_PACKAGE_MAP, config('module-bridge.package_map', []));
            $packageName = array_search($bridgeFileName, $packageMap, true) ?: $bridgeFileName;

            config()->set($packageName, array_replace_recursive(config($packageName, []), $config));
        }
    }

    private function resolveVendorDirectory(): string
    {
        $vendorPath = null;

        if (function_exists('base_path')) {
            $candidate = base_path('vendor/schoolpalm');
            if (is_dir($candidate)) {
                $vendorPath = $candidate;
            }
        }

        if ($vendorPath === null) {
            $candidate = dirname(__DIR__, 3) . '/vendor/schoolpalm';
            if (is_dir($candidate)) {
                $vendorPath = $candidate;
            }
        }

        if ($vendorPath === null) {
            $candidate = getcwd() . '/vendor/schoolpalm';
            if (is_dir($candidate)) {
                $vendorPath = $candidate;
            }
        }

        if ($vendorPath === null) {
            throw new RuntimeException('Unable to locate vendor/schoolpalm directory.');
        }

        return rtrim($vendorPath, DIRECTORY_SEPARATOR);
    }

    private function bridgeConfigDirectory(): string
    {
        return __DIR__ . '/config/module-bridge';
    }

    /**
     * @return array<int, array{package:string, source:string, target:string}>
     */
    private function discoverPackageConfigSources(string $vendorRoot): array
    {
        $sources = [];

        foreach (glob($vendorRoot . '/*', GLOB_ONLYDIR) ?: [] as $packagePath) {
            $packageName = basename($packagePath);
            $configPath = $packagePath . '/config';

            if (! is_dir($configPath)) {
                continue;
            }

            foreach (glob($configPath . '/*.php') ?: [] as $sourceFile) {
                $sources[] = [
                    'package' => $packageName,
                    'source' => $sourceFile,
                    'target' => $this->targetFileName($packageName, basename($sourceFile, '.php')),
                ];
            }
        }

        return $sources;
    }

    private function targetFileName(string $packageName, string $sourceName): string
    {
        $bridgeName = $this->bridgeNameForPackage($packageName);

        if ($sourceName !== $packageName) {
            return sprintf('%s-%s.php', $bridgeName, $sourceName);
        }

        return sprintf('%s.php', $bridgeName);
    }

    private function bridgeNameForPackage(string $packageName): string
    {
        $mapping = array_merge(self::DEFAULT_PACKAGE_MAP, config('module-bridge.package_map', []));

        return $mapping[$packageName] ?? $packageName;
    }

    private function prepareConfigContents(string $packageName, string $targetName, string $sourcePath): string
    {
        $contents = $this->files->get($sourcePath);
        $bridgeName = pathinfo($targetName, PATHINFO_FILENAME);

        return $this->renameEnvironmentVariables($contents, $packageName, $bridgeName);
    }

    private function renameEnvironmentVariables(string $contents, string $packageName, string $bridgeName): string
    {
        $packagePrefix = strtoupper(str_replace('-', '_', $packageName));
        $bridgePrefix = strtoupper(str_replace('-', '_', $bridgeName));
        $result = '';
        $length = strlen($contents);
        $position = 0;

        while ($position < $length) {
            $start = strpos($contents, 'env(', $position);

            if ($start === false) {
                $result .= substr($contents, $position);
                break;
            }

            $result .= substr($contents, $position, $start - $position);
            $result .= 'env(';
            $pos = $start + 4;

            while ($pos < $length && ctype_space($contents[$pos])) {
                $result .= $contents[$pos++];
            }

            if ($pos >= $length || ($contents[$pos] !== '\'' && $contents[$pos] !== '"')) {
                $position = $start + 4;
                continue;
            }

            $quote = $contents[$pos++];
            $result .= $quote;
            $envKeyStart = $pos;

            while ($pos < $length) {
                $char = $contents[$pos];

                if ($char === '\\') {
                    $result .= $char;
                    $pos++;

                    if ($pos < $length) {
                        $result .= $contents[$pos++];
                    }

                    continue;
                }

                if ($char === $quote) {
                    break;
                }

                $result .= $char;
                $pos++;
            }

            if ($pos >= $length) {
                break;
            }

            $envKey = substr($contents, $envKeyStart, $pos - $envKeyStart);
            $normalizedKey = $this->normalizeEnvKey($envKey, $packagePrefix, $bridgePrefix);
            $result = substr($result, 0, -strlen($envKey));
            $result .= $normalizedKey;
            $result .= $quote;
            $pos++;

            $depth = 1;

            while ($pos < $length && $depth > 0) {
                $char = $contents[$pos++];
                $result .= $char;

                if ($char === '\'' || $char === '"') {
                    $stringQuote = $char;

                    while ($pos < $length) {
                        $result .= $contents[$pos];

                        if ($contents[$pos] === '\\') {
                            $pos += 2;
                            continue;
                        }

                        if ($contents[$pos] === $stringQuote) {
                            $pos++;
                            break;
                        }

                        $pos++;
                    }

                    continue;
                }

                if ($char === '(') {
                    $depth++;
                    continue;
                }

                if ($char === ')') {
                    $depth--;
                    continue;
                }
            }

            $position = $pos;
        }

        return $result;
    }

    private function isEnvKey(string $key): bool
    {
        return preg_match('/^[A-Z][A-Z0-9_]*$/', $key) === 1;
    }

    private function normalizeEnvKey(string $key, string $packagePrefix, string $bridgePrefix): string
    {
        if (str_starts_with($key, 'MODULE_BRIDGE_')) {
            return $key;
        }

        if (str_starts_with($key, $packagePrefix . '_')) {
            $suffix = substr($key, strlen($packagePrefix) + 1);

            return sprintf('MODULE_BRIDGE_%s_%s', $bridgePrefix, $suffix);
        }

        return sprintf('MODULE_BRIDGE_%s_%s', $bridgePrefix, $key);
    }
}
