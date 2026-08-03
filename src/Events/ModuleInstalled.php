<?php

namespace SchoolPalm\ModuleBridge\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SchoolPalm\ModuleBridge\Context\ModuleRuntimeContext;

class ModuleInstalled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ModuleRuntimeContext $moduleContext
    ) {}
}