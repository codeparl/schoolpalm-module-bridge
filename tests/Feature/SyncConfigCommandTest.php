<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

it('generates module bridge configuration files from SchoolPalm package config', function () {
    $loggerConfig = base_path('src/Support/config/module-bridge/logger.php');

    Artisan::call('module-bridge:sync-config');

    expect(Artisan::output())->toContain('Bridge config directory');
    expect(File::exists($loggerConfig))->toBeTrue();
    expect(File::get($loggerConfig))->toContain('MODULE_BRIDGE_LOGGER_DRIVER');
});

it('loads bridge configuration into underlying package config namespaces', function () {
    Artisan::call('module-bridge:sync-config');

    expect(config('module-bridge.logger.driver'))->toBe(config('app-logger.driver'));
    expect(config('module-bridge.queues.connection'))->toBe(config('queued-jobs.connection'));
});
