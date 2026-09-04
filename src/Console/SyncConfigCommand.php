<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Console;

use Illuminate\Console\Command;
use SchoolPalm\ModuleBridge\Support\ModuleBridgeConfigSynchronizer;

final class SyncConfigCommand extends Command
{
    protected $signature = 'module-bridge:sync-config';

    protected $description = 'Synchronize SchoolPalm package configuration into Module Bridge configuration files.';

    public function handle(ModuleBridgeConfigSynchronizer $synchronizer): int
    {
        try {
            $result = $synchronizer->synchronize();
            $synchronizer->loadGeneratedConfigs($result['generated_files'] ?? []);
        } catch (\Throwable $exception) {
            $this->error('Configuration synchronization failed: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Discovered %d SchoolPalm configuration source(s).', $result['discovered']));
        $this->info(sprintf('Generated %d new or updated bridge config file(s).', $result['generated']));
        $this->info(sprintf('Skipped %d unchanged bridge config file(s).', $result['skipped']));
        $this->info(sprintf('Bridge config directory: %s', $result['directory']));

        return self::SUCCESS;
    }
}
