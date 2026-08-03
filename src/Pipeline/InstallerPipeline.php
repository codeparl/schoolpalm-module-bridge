<?php

namespace SchoolPalm\ModuleBridge\Pipeline;

use SchoolPalm\ModuleBridge\Context\InstallContext;

class InstallerPipeline
{
    protected array $actions = [];

    public function addAction(InstallerActionInterface $action): self
    {
        $this->actions[] = $action;
        return $this;
    }

    /* =========================================
     * FULL RUN
     * ========================================= */
    public function run(InstallContext $context): void
    {
        $context->acquireLock();

        try {
            $context->boot();

            foreach ($this->actions as $action) {

                $phase = $action->phase();
                $key   = $action->key();

                if ($context->isCompleted($phase, $key)) {
                    continue;
                }

                if (!$this->canRunStep($context, $phase, $key)) {
                    throw new \RuntimeException("Previous step must be completed first: {$key}");
                }

                try {

                    if ($action->canExecute($context)) {

                        $action->execute($context);
                        $context->markCompleted($phase, $key);

                    } else {

                        $context->setStepStatus($phase, $key, 'skipped', 'Condition not met');
                    }

                } catch (\Throwable $e) {

                    $context->markFailed($phase, $key, $e->getMessage());

                    $this->rollback($context);

                    throw $e;
                }
            }

            if (!$context->isFailed()) {
                $context->setStepStatus('system', 'pipeline', 'success', 'Installation completed');
            }

        } finally {
            $context->releaseLock();
        }
    }

    /* =========================================
     * STEP
     * ========================================= */
    public function runStep(string $targetKey, InstallContext $context): void
    {
      // $context->acquireLock();
//$context->releaseLock();
        try {
            
            $context->boot();
            foreach ($this->actions as $action) {

                $phase = $action->phase();
                $key   = $action->key();

                if ($key !== $targetKey) continue;

            //    if ($context->isCompleted($phase, $key)){
            //     $context->releaseLock();
            //      return;
            //      }

                if (!$this->canRunStep($context, $phase, $key)) {
                    throw new \RuntimeException("Previous step must be completed first.");
                }

                try {
                    if ($action->canExecute($context)) {
                        $action->execute($context);
                  
                    }
                } catch (\Throwable $e) {
                    $context->markFailed($phase, $key, $e->getMessage());
                    throw $e;
                }

                return;
            }

            throw new \RuntimeException("Step not found: {$targetKey}");

        } finally {
            $context->releaseLock();
        }
    }

    /* =========================================
     * PHASE
     * ========================================= */
    public function runPhase(string $targetPhase, InstallContext $context): void
    {
        $context->acquireLock();

        try {
            $context->boot();

            foreach ($this->actions as $action) {

                if ($action->phase() !== $targetPhase) continue;

                $phase = $action->phase();
                $key   = $action->key();

                if ($context->isCompleted($phase, $key)) continue;

                if (!$this->canRunStep($context, $phase, $key)) {
                    throw new \RuntimeException("Previous step must be completed first.");
                }

                try {
                    if ($action->canExecute($context)) {
                        $action->execute($context);
                    }
                } catch (\Throwable $e) {
                    $context->markFailed($phase, $key, $e->getMessage());
                    throw $e;
                }
            }

        } finally {
            $context->releaseLock();
        }
    }

    /* =========================================
     * CHAIN (AUTO-RUN)
     * ========================================= */
    public function runStepChain(string $startKey, InstallContext $context): void
    {
        $context->acquireLock();

        try {
            $context->boot();

            $start = false;

            foreach ($this->actions as $action) {

                if ($action->key() === $startKey) {
                    $start = true;
                }

                if (!$start) continue;

                $phase = $action->phase();
                $key   = $action->key();

                if ($context->isCompleted($phase, $key)) continue;

                if (!$this->canRunStep($context, $phase, $key)) break;

                try {
                    if ($action->canExecute($context)) {
                        $action->execute($context);
                        $context->markCompleted($phase, $key);
                    }
                } catch (\Throwable $e) {
                    $context->markFailed($phase, $key, $e->getMessage());
                    throw $e;
                }
            }

        } finally {
            $context->releaseLock();
        }
    }

    /* =========================================
     * RETRY
     * ========================================= */
    public function retryStep(string $targetKey, InstallContext $context): void
    {
        $context->acquireLock();

        try {
            $context->boot();

            foreach ($this->actions as $action) {

                $phase = $action->phase();
                $key   = $action->key();

                if ($key !== $targetKey) continue;

                if (!$context->isFailedStep($phase, $key)) {
                    throw new \RuntimeException("Step is not failed.");
                }

                if (!$this->canRunStep($context, $phase, $key)) {
                    throw new \RuntimeException("Previous step must be completed first.");
                }

                try {
                    $action->execute($context);
                    $context->markCompleted($phase, $key);
                } catch (\Throwable $e) {
                    $context->markFailed($phase, $key, $e->getMessage());
                    throw $e;
                }

                return;
            }

            throw new \RuntimeException("Step not found");

        } finally {
            $context->releaseLock();
        }
    }

    /* =========================================
     * ORDER ENFORCEMENT
     * ========================================= */
    protected function canRunStep(InstallContext $context, string $phase, string $key): bool
    {
        $phaseData = $context->getPhase($phase);
        $steps = $phaseData['steps'] ?? [];

        $keys = array_keys($steps);
        $index = array_search($key, $keys, true);

        if ($index === false || $index === 0) return true;

        $prevKey = $keys[$index - 1];

        return ($steps[$prevKey]['status'] ?? null) === 'success';
    }

    /* =========================================
     * ROLLBACK
     * ========================================= */
    public function rollback(InstallContext $context): void
    {
        foreach (array_reverse($this->actions) as $action) {

            $phase = $action->phase();
            $key   = $action->key();

            if ($action->isReversible() && $context->isCompleted($phase, $key)) {
                try {
                    $action->rollback($context);
                } catch (\Throwable $e) {
                    $context->markFailed($phase, $key, "Rollback failed: ".$e->getMessage());
                }
            }
        }
    }
}