<?php

namespace SchoolPalm\ModuleBridge\Pipeline;

use SchoolPalm\ModuleBridge\Context\InstallContext;

abstract class AbstractInstallerAction implements InstallerActionInterface
{
    protected ?string $key = null;
    protected string $phase = 'general';

    protected bool $reversible = true;
    protected bool $dryRun = false;
    protected bool $silent = false;

    /* =========================================
     * KEY / PHASE
     * ========================================= */

    public function key(): string
    {
        if ($this->key !== null && $this->key !== '') {
            return $this->key;
        }

        $class = class_basename(static::class);
        $name = preg_replace('/Action$/', '', $class) ?? $class;
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

        return $snake;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;
        return $this;
    }

    public function phase(): string
    {
        return $this->phase;
    }

    public function setPhase(string $phase): static
    {
        $this->phase = $phase;
        return $this;
    }

    /* =========================================
     * EXECUTION OPTIONS
     * ========================================= */

    public function setExecutionOptions(bool $dryRun, bool $silent): static
    {
        $this->dryRun = $dryRun;
        $this->silent = $silent;
        return $this;
    }

    /* =========================================
     * CORE HOOKS
     * ========================================= */

    public function canExecute(InstallContext $context): bool
    {
        return true;
    }

    public function rollback(InstallContext $context): void
    {
        // no-op by default
    }

    public function isReversible(): bool
    {
        return $this->reversible;
    }

    /* =========================================
     * RESULT HELPERS (IMPORTANT PART)
     * ========================================= */

  public function success(InstallContext $context, ?string $message = null): void
{
    $message = $message
        ?? ActionRegistry::humanize($this->key())
        . ' completed successfully';

    $context->setStepStatus(
        $this->phase,
        $this->key(),
        'success',
        $message
    );
}
    public function fail(InstallContext $context, string $message): void
    {
        $context->setStepStatus(
            $this->phase,
            $this->key(),
            'failed',
            $message
        );

    
    throw new \RuntimeException($message);

    }

    public function log(InstallContext $context, string $message): void
    {
        $context->addLog(
            $this->phase,
            $this->key(),
            $message
        );
    }
}