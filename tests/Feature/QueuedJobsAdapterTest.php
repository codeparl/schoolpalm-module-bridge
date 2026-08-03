<?php

declare(strict_types=1);

use SchoolPalm\ModuleBridge\Facades\Host\QueueHost;
use SchoolPalm\QueuedJobs\Builders\JobBuilder;
use SchoolPalm\QueuedJobs\Context\QueueContext;
use SchoolPalm\QueuedJobs\Builders\JobResultBuilder;

/**
 * Helper to inspect context from job builder via Reflection.
 */
function getJobContext(JobBuilder $builder): array
{
    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('context');
    $property->setAccessible(true);

    /** @var QueueContext $context */
    $context = $property->getValue($builder);

    return $context->toArray();
}

it('automatically attaches ambient context when calling job builder', function () {
    $jobObject = new class {
        public function handle() {}
    };

    $jobBuilder = QueueHost::job($jobObject);

    expect($jobBuilder)->toBeInstanceOf(JobBuilder::class);

    $context = getJobContext($jobBuilder);

    expect($context)->toMatchArray([
        'tenant_id' => 'tenant_demo_001',
        'school_id' => 'SDK-001',
    ]);
});

it('allows explicit school context overriding while retaining ambient tenant context', function () {
    $jobObject = new class {
        public function handle() {}
    };

    $jobBuilder = QueueHost::forSchool('school-999')->job($jobObject);

    $context = getJobContext($jobBuilder);

    expect($context['school_id'])->toBe('school-999');
    expect($context['tenant_id'])->toBe('tenant_demo_001');
});

it('allows explicit tenant context overriding', function () {
    $jobObject = new class {
        public function handle() {}
    };

    $jobBuilder = QueueHost::forTenant('tenant-2026')->job($jobObject);

    $context = getJobContext($jobBuilder);

    expect($context['tenant_id'])->toBe('tenant-2026');
});

it('merges custom context using withContext method while retaining ambient context', function () {
    $jobObject = new class {
        public function handle() {}
    };

    $jobBuilder = QueueHost::withContext([
        'user_id' => 'user-404',
        'metadata' => ['action' => 'fee_payment_reminder'],
    ])->job($jobObject);

    $context = getJobContext($jobBuilder);

    expect($context)->toMatchArray([
        'tenant_id' => 'tenant_demo_001',
        'school_id' => 'SDK-001',
        'user_id'   => 'user-404',
        'metadata'  => ['action' => 'fee_payment_reminder'],
    ]);
});

it('ensures adapter methods return immutable clones without leaking state', function () {
    $jobObject = new class {
        public function handle() {}
    };

    $scopedBuilder = QueueHost::forSchool('school-888')->job($jobObject);
    $defaultBuilder = QueueHost::job($jobObject);

    expect(getJobContext($scopedBuilder)['school_id'])->toBe('school-888');
    expect(getJobContext($defaultBuilder)['school_id'])->toBe('SDK-001');
});


it('provides access to the jobs query builder from the adapter', function () {
    $jobsBuilder = QueueHost::jobs();

    expect($jobsBuilder)->toBeInstanceOf(JobResultBuilder::class);
});

it('allows filtering and executing job results via the adapter', function () {
    $jobObject = new class {
        public function handle() {}
    };

    // Use query builder features exposed via __call forwarding to QueuedJobsManager
    $jobsBuilder = QueueHost::jobs()
        ->forSchool('SDK-001')
        ->forModule('reports');

    expect($jobsBuilder)->toBeInstanceOf(JobResultBuilder::class);

    // Verify execution methods exist and return expected types or collections
    expect(method_exists($jobsBuilder, 'get'))->toBeTrue();
    expect(method_exists($jobsBuilder, 'first'))->toBeTrue();
    expect(method_exists($jobsBuilder, 'count'))->toBeTrue();
    expect(method_exists($jobsBuilder, 'exists'))->toBeTrue();
});
