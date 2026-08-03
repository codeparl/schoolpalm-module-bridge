<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use Illuminate\Support\Facades\File;

class PrepareExecutionDirectoriesAction extends AbstractInstallerAction
{
    protected string $phase = 'Host Preparation';

    public function execute(InstallContext $context): void
    {
        $paths = [
            'Module Execution Path'   => $context->moduleExecutionPath,
            'Frontend Execution Path' => $context->frontendExecutionPath,
            'Routes Execution Path'   => $context->routesExecutionPath,
        ];

        foreach ($paths as $label => $path) {
            if (empty($path)) continue;

            File::ensureDirectoryExists($path);

            if (!$this->silent) {
                echo "[OK] {$label} ensured: {$path}\n";
            }
        }
    }
}
