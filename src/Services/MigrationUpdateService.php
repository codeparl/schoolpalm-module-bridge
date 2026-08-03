<?php

namespace SchoolPalm\ModuleBridge\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;

class MigrationUpdateService
{
    private string $basePath;
    private string $phpBinary;

    public function __construct()
    {
        $this->basePath = base_path();
        $this->phpBinary = PHP_BINARY;
    }

    /**
     * Execute migration updates using a fresh Symfony Process
     */
    public function executeMigrationUpdate(string $moduleKey, ?string $tableName = null): array
    {
        // Build the Artisan command
        $command = [
            $this->phpBinary,
            $this->basePath . '/artisan',
            'module:migration-update',
            $moduleKey,
        ];

        if ($tableName) {
            $command[] = $tableName;
        }

        // Set timeout to 5 minutes (migrations can take time)
        $process = new Process($command);
        $process->setTimeout(300);
        $process->setIdleTimeout(300);

        // Capture output
        $output = 'output: ';
        $errorOutput = '';

        $process->run(function ($type, $buffer) use (&$output, &$errorOutput) {
            if ($type === Process::ERR) {
                $errorOutput .= $buffer;
            } else {
                $output .= $buffer;
            }
        });
dd( $output,$this->basePath );
        // Log the execution
        Log::info('Migration update executed', [
            'module_key' => $moduleKey,
            'table_name' => $tableName,
            'exit_code' => $process->getExitCode(),
            'output' => $output,
            'error' => $errorOutput,
        ]);

        return [
            'success' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'output' => $output,
            'error_output' => $errorOutput,
        ];
    }

    /**
     * Asynchronous execution using queue worker
     */
    public function dispatchMigrationUpdate(string $moduleKey, ?string $tableName = null): void
    {
        dispatch(new \App\Jobs\RunMigrationUpdateJob($moduleKey, $tableName));
    }
}