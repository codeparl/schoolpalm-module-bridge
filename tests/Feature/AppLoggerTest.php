<?php

declare(strict_types=1);

use SchoolPalm\AppLogger\Context\AppContext;
use SchoolPalm\ModuleBridge\Facades\Host\LoggerHost;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

it('stores a log with application context', function () {
    // 2. Dispatch log entry via facade adapter
    LoggerHost::info(
        'Staff created',
        [
            'staff_id' => 10,
        ]
    );

    // 3. Query logs via the repository
    $logs = LoggerHost::logs()->search('Staff');

    // 4. Correct Pest assertion syntax
    expect($logs->isEmpty())->toBeFalse();

    $firstLog = $logs->first();

    expect($firstLog['message'])
        ->toBe('Staff created');

    expect($firstLog['context'])
        ->toMatchArray([
            'tenant_id' => 'tenant_demo_001',
        ]);

    expect($firstLog['data'])
        ->toMatchArray([
            'staff_id' => 10,
        ]);
});
