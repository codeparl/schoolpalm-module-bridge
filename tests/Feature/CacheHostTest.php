<?php

declare(strict_types=1);

use SchoolPalm\ModuleBridge\Facades\Host\CacheHost;

it('stores and retrieves cache value using auto-resolved context', function () {
    CacheHost::put('academic_year', '2026');

    expect(CacheHost::get('academic_year'))->toEqual('2026');
});

it('isolates cache values between schools', function () {
    CacheHost::forSchool('school-100')->put('max_students', 500);
    CacheHost::forSchool('school-200')->put('max_students', 1200);

    expect(CacheHost::forSchool('school-100')->get('max_students'))->toBe(500);
    expect(CacheHost::forSchool('school-200')->get('max_students'))->toBe(1200);
});

it('executes remember helper successfully', function () {
    $value = CacheHost::remember('grading_rules', 3600, function () {
        return ['pass' => 50, 'distinction' => 80];
    });

    expect($value)->toBe(['pass' => 50, 'distinction' => 80]);
    expect(CacheHost::get('grading_rules'))->toBe(['pass' => 50, 'distinction' => 80]);
});

it('handles atomic operations like increment and decrement', function () {
    CacheHost::put('login_attempts', 1);

    expect(CacheHost::increment('login_attempts'))->toBe(2);
    expect(CacheHost::decrement('login_attempts'))->toBe(1);
});
