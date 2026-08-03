<?php

namespace SchoolPalm\ModuleBridge\Events;

use SchoolPalm\ModuleBridge\Context\ModuleRuntimeContext;

class ModuleCleanupCompletedEvent
{
    /**
     * Indicates whether the module directory was removed from transit.
     */
    public bool $moduleRemoved;

    /**
     * Indicates whether transit root directory still exists (it should always be true).
     */
    public bool $transitExists;

    /**
     * Path of the module that was cleaned.
     */
    public string $modulePath;

    public string $message;

    public function __construct(
        public ModuleRuntimeContext $context,
        bool $moduleRemoved = true,
        bool $transitExists = true,
        string $modulePath = '',
        string $message = 'Module removed from transit successfully'
    ) {
        $this->moduleRemoved = $moduleRemoved;
        $this->transitExists = $transitExists;
        $this->modulePath = $modulePath;
        $this->message = $message;
    }
}