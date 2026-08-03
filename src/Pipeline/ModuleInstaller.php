<?php

namespace SchoolPalm\ModuleBridge\Pipeline;

use RuntimeException;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;

/**
 * High-level facade that hides manifest/context/pipeline wiring.
 */
final class ModuleInstaller
{
    protected ModuleManifest $manifest;

    protected InstallContext $context;

    protected ModuleInstallerPipeline $pipeline;

    protected bool $dryRun = false;

    protected bool $silent = false;

    public function __construct(ModuleManifest|string|array $manifest)
    {
        $this->manifest = $manifest instanceof ModuleManifest
            ? $manifest
            : new ModuleManifest($manifest);

        $this->context = new InstallContext($this->manifest);
        $this->pipeline = new ModuleInstallerPipeline($this->context);

        $this->syncPipelineOptions();
    }

    public static function make(ModuleManifest|string|array $manifest): self
    {
        try {
            new self($manifest);
        } catch (\Throwable $th) {
            dd($th);
        }
        return new self($manifest);
    }

    public function dryRun(bool $enabled = true): static
    {
        $this->dryRun = $enabled;
        $this->syncPipelineOptions();
        return $this;
    }

    public function silent(bool $enabled = true): static
    {
        $this->silent = $enabled;
        $this->syncPipelineOptions();
        return $this;
    }

    public function run(): static
    {
        $this->pipeline->installModule($this->context);
        return $this;
    }

    public function runStep(string $stepKey): static
    {
        $this->pipeline->runStep($stepKey, $this->context);
        return $this;
    }

    public function runPhase(string $phase): static
    {
        $this->pipeline->runPhase($phase, $this->context);
        return $this;
    }

    public function runFrom(string $startStepKey): static
    {
        $this->pipeline->runStepChain($startStepKey, $this->context);
        return $this;
    }

    public function retry(string $stepKey): static
    {
        $this->pipeline->retryStep($stepKey, $this->context);
        return $this;
    }

    public function rollback(): static
    {
        $this->pipeline->rollback($this->context);
        return $this;
    }

    public function state(): array
    {
        return $this->context->getState();
    }

    public function progress(): array
    {
        return $this->context->getProgress();
    }

    public function errors(): array
    {
        return $this->context->getErrors();
    }

    public function removePhaseState(string $phase): bool
    {
        return $this->context->removePhaseState($phase);
    }

    public function removeInstallStateIfFullyInstalled(bool $fullState=true): bool
    {
        return $this->context->removeInstallStateIfFullyInstalled($fullState);
    }


 
    public function manifest(): ModuleManifest
    {
        return $this->manifest;
    }

    public function context(): InstallContext
    {
        return $this->context;
    }

    public function pipeline(): ModuleInstallerPipeline
    {
        return $this->pipeline;
    }

    protected function syncPipelineOptions(): void
    {
        $this->pipeline
            ->setDryRun($this->dryRun)
            ->setSilent($this->silent);
    }
}

