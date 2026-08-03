<?php

namespace SchoolPalm\ModuleBridge\Pipeline;

use SchoolPalm\ModuleBridge\Context\InstallContext;
class ModuleInstallerPipeline extends InstallerPipeline
{
    protected bool $dryRun = false;
    protected bool $silent = false;

    public function __construct(InstallContext $context)
    {
        $actions = PipelineBuilder::build(
            $context,
            $this->dryRun,
            $this->silent
        );

        foreach ($actions as $action) {
            $this->addAction($action);
        }
    }

    public function setDryRun(bool $dryRun = true): static
    {
        $this->dryRun = $dryRun;
        return $this;
    }

    public function setSilent(bool $silent = true): static
    {
        $this->silent = $silent;
        return $this;
    }

    public function installModule(InstallContext $context): void
    {
        $this->run($context);
    }
}
