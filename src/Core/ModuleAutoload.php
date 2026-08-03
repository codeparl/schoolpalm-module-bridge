<?php

namespace SchoolPalm\ModuleBridge\Core;

use Composer\Autoload\ClassLoader;
use SchoolPalm\ModuleBridge\Facades\CreatedRegistry;

class ModuleAutoload
{
    protected static array $registered = [];

    protected ClassLoader $loader;

    public function __construct(ClassLoader $loader)
    {
        $this->loader = $loader;
    }

    public function boot(): void
    {
        $modules = CreatedRegistry::all();

        foreach ($modules as $moduleKey => $module) {
            $this->registerModule($module);
        }

        
    }

    protected function registerModule(array $module): void
    {
        if (empty($module['namespace']) || empty($module['path'])) {
            return;
        }

        $namespace = rtrim($module['namespace'], '\\') . '\\';
        $path = rtrim($module['path'], DIRECTORY_SEPARATOR).'\\Backend';

        if (isset(self::$registered[$namespace])) {
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        self::$registered[$namespace] = $path;

        $this->loader->addPsr4($namespace, $path);
    }

    // 👇 ADD THIS
    public function registered(): array
    {
        return self::$registered;
    }

    // 👇 ADD THIS
    public function isRegistered(string $namespace): bool
    {
        return isset(self::$registered[rtrim($namespace, '\\') . '\\']);
    }
}