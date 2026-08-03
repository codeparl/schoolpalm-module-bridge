<?php

namespace SchoolPalm\ModuleBridge\Context;

use Illuminate\Support\Facades\DB;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;
use SchoolPalm\ModuleBridge\Support\Helper;

/**
 * Class InstallContext
 *
 * Represents the full lifecycle context of a module during the installation pipeline.
 *
 * --------------------------------------------------
 * RESPONSIBILITY
 * --------------------------------------------------
 * This class is responsible for:
 * - Managing pipeline execution state (phases & steps)
 * - Persisting installation progress across requests
 * - Handling installation logs and non-fatal errors
 * - Providing execution paths for module deployment
 * - Enforcing installation locking (prevent concurrency)
 *
 * --------------------------------------------------
 * IMPORTANT DESIGN NOTE
 * --------------------------------------------------
 * This context is STRICTLY for installation phase.
 *
 * It MUST NOT be used:
 * - After module installation is complete
 * - In runtime module execution
 * - In events or application logic outside installer
 *
 * After successful installation:
 * → Transition to ModuleRuntimeContext
 * → InstallContext should be discarded
 *
 * --------------------------------------------------
 * STATE STORAGE
 * --------------------------------------------------
 * All installation states are stored in a shared JSON file:
 *
 *   install_states.json
 *
 * Each module has its own state bucket keyed by:
 *   module_key
 *
 * --------------------------------------------------
 * FILE SYSTEM PATHS
 * --------------------------------------------------
 * This context provides resolved paths for:
 *
 * - transit_path
 * - moduleBackendExecutionPath
 * - moduleFrontendExecutionPath
 * - moduleBackendRootPath
 * - moduleBackendPath
 * - moduleCachePath
 *
 * --------------------------------------------------
 * LOCKING MECHANISM
 * --------------------------------------------------
 * Prevents concurrent installation using a lock file:
 *
 *   install_states.json.{module}.lock
 *
 * --------------------------------------------------
 * PIPELINE STRUCTURE
 * --------------------------------------------------
 * [
 *   phase => [
 *     steps => [
 *       step_key => [
 *         name,
 *         status (pending|success|failed),
 *         log
 *       ]
 *     ]
 *   ]
 * ]
 */
class InstallContext
{
    public ModuleManifest $module;

    protected array $state = [];
    protected string $moduleKey;
    protected string $stateFile;
    protected string $lockFile;
    protected bool $booted = false;
    protected array $errors = [];

    public string $transit_path;
    public string $moduleBackendExecutionPath;
    public string $moduleFrontendExecutionPath;
    public string $moduleCachePath;
    public string $moduleBackendPath;
    public string $moduleBackendRootPath;

    public function __construct(ModuleManifest $module)
    {
        $this->module = $module;
        $this->moduleKey = $module->key();

        $basePath = config('sdk.module.submitted_path');

        $this->transit_path = config('sdk.module.ready_path');
        $this->moduleBackendExecutionPath = config('sdk.module.exec_dir.backend');
        $this->moduleFrontendExecutionPath = config('sdk.module.exec_dir.frontend');
        $this->moduleCachePath = config('sdk.module.cache.external');

        $this->moduleBackendPath = $this->moduleBackendExecutionPath;
        $this->moduleBackendRootPath = $this->moduleBackendPath . '/' . $this->module->root();
        $this->moduleBackendPath .= '/' . $this->module->root() . '/Backend/';

        $this->stateFile = $basePath . '/install_states.json';

        $safeKey = preg_replace('/[^A-Za-z0-9_.-]/', '_', $this->moduleKey) ?? 'module';
        $this->lockFile = $this->stateFile . '.' . $safeKey . '.lock';

        $this->boot();
    }

    public function boot(): void
    {
        if ($this->booted) return;

        $allStates = $this->loadAllStates();

        if (!isset($allStates[$this->moduleKey])) {
            $this->state = $this->buildInitialState();
            $this->persist();
        } else {
            $this->state = $allStates[$this->moduleKey];
        }

        $this->booted = true;
    }

    protected function buildInitialState(): array
    {
        $state = [];

        foreach ($this->module->pipeline() as $phase => $data) {
            $state[$phase] = ['steps' => []];

            foreach ($data['steps'] ?? [] as $stepKey => $step) {
                $key = $step['key'] ?? $stepKey;

                $state[$phase]['steps'][$key] = [
                    'name' => $step['name'] ?? $key,
                    'status' => 'pending',
                    'log' => ''
                ];
            }
        }

        return $state;
    }

    protected function loadAllStates(): array
    {
        if (!file_exists($this->stateFile)) return [];

        $json = Helper::loadJson($this->stateFile);

        return is_array($json) ? $json : [];
    }

    protected function persist(): void
    {
        $allStates = $this->loadAllStates();
        $allStates[$this->moduleKey] = $this->state;

        Helper::storeJson($this->stateFile, $allStates);
    }

    /* ========================= STATE ========================= */

    public function getState(): array
    {
        return $this->state;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getPhase(string $phase): array
    {
        return $this->state[$phase] ?? [];
    }

    public function isCompleted(string $phase, string $key): bool
    {
        return ($this->state[$phase]['steps'][$key]['status'] ?? null) === 'success';
    }

    public function isFailed(): bool
    {
        foreach ($this->state as $phase) {
            foreach ($phase['steps'] as $step) {
                if ($step['status'] === 'failed') return true;
            }
        }
        return false;
    }

    public function isFailedStep(string $phase, string $key): bool
    {
        return ($this->state[$phase]['steps'][$key]['status'] ?? null) === 'failed';
    }

    public function isFullyInstalled(?array $state = null): bool
    {
        $state ??= $this->state;

        foreach ($this->module->pipeline() as $phase => $data) {
            foreach ($data['steps'] ?? [] as $stepKey => $step) {
                $key = $step['key'] ?? $stepKey;

                if (($state[$phase]['steps'][$key]['status'] ?? null) !== 'success') {
                    return false;
                }
            }
        }

        return true;
    }

    /* ========================= STEP ========================= */

    public function setStepStatus(string $phase, string $key, string $status, string $log = ''): void
    {
        if (!isset($this->state[$phase]['steps'][$key])) return;

        $this->state[$phase]['steps'][$key]['status'] = $status;
        $this->state[$phase]['steps'][$key]['log'] = $log;

        $this->persist();
    }

    public function markCompleted(string $phase, string $key, $message = 'Completed successfully'): void
    {
        $this->setStepStatus($phase, $key, 'success', $message);
    }

    public function markFailed(string $phase, string $key, string $log): void
    {
        $this->setStepStatus($phase, $key, 'failed', $log);
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function addLog(string $phase, string $key, string $message): void
    {
        if (!isset($this->state[$phase]['steps'][$key])) return;

        $existing = $this->state[$phase]['steps'][$key]['log'] ?? '';
        $line = '[' . now()->toDateTimeString() . '] ' . $message;

        $this->state[$phase]['steps'][$key]['log'] = $existing
            ? $existing . PHP_EOL . $line
            : $line;

        $this->persist();
    }

    public function removePhaseState(string $phase): bool
    {
        foreach ($this->state[$phase]['steps'] ?? [] as $step) {
            if (($step['status'] ?? null) !== 'success') return false;
        }

        unset($this->state[$phase]);
        $this->persist();

        return true;
    }

    public function removeInstallStateIfFullyInstalled(bool $fullState = true): bool
    {
        if (!$this->isFullyInstalled() && $fullState) return false;

        $allStates = $this->loadAllStates();
        unset($allStates[$this->moduleKey]);

        Helper::storeJson($this->stateFile, $allStates);

        $this->state = [];
        $this->booted = false;

        return true;
    }

    /* ========================= LOCK ========================= */

    public function acquireLock(): void
    {
        if (file_exists($this->lockFile)) {
            throw new \RuntimeException("Another installation is in progress.");
        }

        file_put_contents($this->lockFile, 'locked');
    }

    public function releaseLock(): void
    {
        if (file_exists($this->lockFile)) unlink($this->lockFile);
    }

    /* ========================= PROGRESS ========================= */

    public function getProgress(): array
    {
        $total = 0;
        $done = 0;

        foreach ($this->state as $phase) {
            foreach ($phase['steps'] as $step) {
                $total++;
                if ($step['status'] === 'success') $done++;
            }
        }

        return [
            'total' => $total,
            'completed' => $done,
            'percentage' => $total ? round(($done / $total) * 100) : 0
        ];
    }

    public function moduleExistsInDb()
    {
        return DB::table('modules')
            ->where('module_key', $this->moduleKey)
            ->exists();
    }
}