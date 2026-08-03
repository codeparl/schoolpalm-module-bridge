<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;

class VerifyModuleDoesNotExistsAction extends AbstractInstallerAction
{
    protected string $phase = 'verification';
    protected bool $reversible = false;

    public function key(): string
    {
        return 'verify_module_does_not_exists';
    }

    public function execute(InstallContext $context): void
    {
     

        // ------------------------------------
        // 1. Check file exists in filesystem
        // ------------------------------------
        $path = $context->module->modulePath();
   
        if (!File::exists($path)) {
            $this->fail($context,"Module not found at: {$path}");
            
        }

        // ------------------------------------
        // 2. Check module exists in database
        // ------------------------------------
        $moduleKey = $context->module->key() ?? null;
        
        if (!$moduleKey) {
        $this->fail($context,"Module key is missing in context");
        return;

        }

        if ($context->moduleExistsInDb()) {
            $this->fail($context,"Module '{$moduleKey}' already exists in the system.");
            return;
        }

        $this->success($context);
    }


}