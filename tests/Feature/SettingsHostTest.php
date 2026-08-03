<?php

declare(strict_types=1);

use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;

it('stores and retrieves a setting with auto-resolved application context', function () {
    // 1. Store a setting via facade adapter (automatically uses active context)
    SettingsHost::put('grading.scale', 'GPA_4_0');

    // 2. Retrieve setting
    $value = SettingsHost::get('grading.scale');

    // 3. Pest assertions
    expect($value)->toBe('GPA_4_0');
    expect(SettingsHost::has('grading.scale'))->toBeTrue();
});

it('returns default value when setting does not exist', function () {
    $value = SettingsHost::get('non_existing_key', 'default_theme');

    expect($value)->toBe('default_theme');
    expect(SettingsHost::has('non_existing_key'))->toBeFalse();
});

it('allows explicit fluent scope overrides for tenant, school, and user', function () {
    // 1. Set settings across different scopes explicitly
    SettingsHost::forTenant('tenant_demo_001')->put('theme.color', 'blue');
    SettingsHost::forSchool('SCH_999')->put('academic_year', '2026-2027');
    SettingsHost::forUser('USR_55')->put('notifications.email', true);

    // 2. Fetch and assert scoped values
    expect(SettingsHost::forTenant('tenant_demo_001')->get('theme.color'))
        ->toBe('blue');

    expect(SettingsHost::forSchool('SCH_999')->get('academic_year'))
        ->toBe('2026-2027');

    expect(SettingsHost::forUser('USR_55')->get('notifications.email'))
        ->toBeTrue();
});

it('forgets a stored setting within context', function () {
    SettingsHost::put('feature.beta_enabled', true);

    expect(SettingsHost::has('feature.beta_enabled'))->toBeTrue();

    SettingsHost::forget('feature.beta_enabled');

    expect(SettingsHost::has('feature.beta_enabled'))->toBeFalse();
    expect(SettingsHost::get('feature.beta_enabled'))->toBeNull();
});

it('retrieves all settings for the active context scope', function () {
    SettingsHost::put('app.timezone', 'Africa/Kampala');
    SettingsHost::put('app.currency', 'UGX');

    $allSettings = SettingsHost::all();

    expect($allSettings)->toMatchArray([
        'app.timezone' => 'Africa/Kampala',
        'app.currency' => 'UGX',
    ]);
});

it('stores and retrieves a setting using default school context', function () {
    // 1. Store setting (auto-resolves default 'school' scope: school-xyz)
    SettingsHost::set('grading.scale', 'GPA_4_0');

    // 2. Fetch setting
    $value = SettingsHost::get('grading.scale');

    // 3. Assertions
    expect($value)->toBe('GPA_4_0');
    expect(SettingsHost::has('grading.scale'))->toBeTrue();
});

it('supports settings under specific groups', function () {
    // 1. Store settings under distinct groups
    SettingsHost::group('report_cards')->set('template', 'modern_v2');
    SettingsHost::group('sms_gateway')->set('provider', 'twilio');

    // 2. Assert group isolation
    expect(SettingsHost::group('report_cards')->get('template'))
        ->toBe('modern_v2');

    expect(SettingsHost::group('sms_gateway')->get('provider'))
        ->toBe('twilio');

    // Key without group context should not exist
    expect(SettingsHost::has('template'))->toBeFalse();
});

it('isolates settings across custom database connections', function () {
    // 1. Store setting on a specific connection
    SettingsHost::withConnection('testing')->set('cache.ttl', 3600);

    // 2. Assert retrieval on connection
    expect(SettingsHost::withConnection('testing')->get('cache.ttl'))
        ->toBe(3600);
});

it('combines connection, group, and context scopes fluently', function () {
    // 1. Fluent chain: connection + group + explicit tenant
    SettingsHost::withConnection('testing')
        ->withGroup('finance')
        ->forTenant('tenant-abc')
        ->set('currency', 'UGX');

    // 2. Retrieve with identical chain
    $currency = SettingsHost::withConnection('testing')
        ->withGroup('finance')
        ->forTenant('tenant-abc')
        ->get('currency');

    expect($currency)->toBe('UGX');
});

it('allows explicit scope overrides for tenant, school, and user', function () {
    // 1. Explicitly target different context tiers
    SettingsHost::forTenant('tenant-abc')->set('branding.logo', 'tenant_logo.png');
    SettingsHost::forSchool('school-xyz')->set('branding.logo', 'school_logo.png');
    SettingsHost::forUser('user-001')->set('branding.logo', 'user_avatar.png');

    // 2. Assert each context layer maintains its own distinct value
    expect(SettingsHost::forTenant('tenant-abc')->get('branding.logo'))
        ->toBe('tenant_logo.png');

    expect(SettingsHost::forSchool('school-xyz')->get('branding.logo'))
        ->toBe('school_logo.png');

    expect(SettingsHost::forUser('user-001')->get('branding.logo'))
        ->toBe('user_avatar.png');
});

it('forgets a stored setting within its specific group scope', function () {
    SettingsHost::withGroup('notifications')->set('email_enabled', true);

    expect(SettingsHost::withGroup('notifications')->has('email_enabled'))->toBeTrue();

    SettingsHost::withGroup('notifications')->forget('email_enabled');

    expect(SettingsHost::withGroup('notifications')->has('email_enabled'))->toBeFalse();
    expect(SettingsHost::withGroup('notifications')->get('email_enabled'))->toBeNull();
});

it('retrieves all settings under the active school context', function () {
    SettingsHost::set('academic.term', 'Term 1');
    SettingsHost::set('academic.year', '2026');

    $allSettings = SettingsHost::all();

    expect($allSettings)->toMatchArray([
        'academic.term' => 'Term 1',
        'academic.year' => '2026',
    ]);
});
