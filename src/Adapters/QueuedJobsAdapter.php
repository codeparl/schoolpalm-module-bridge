<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use SchoolPalm\MessageDelivery\Context\MessageContext;
use SchoolPalm\ModuleBridge\Services\ContextResolver;
use SchoolPalm\QueuedJobs\Builders\JobBuilder;
use SchoolPalm\QueuedJobs\Managers\QueuedJobsManager;
use SchoolPalm\QueuedJobs\Context\QueueContext;

class QueuedJobsAdapter
{
    protected array $contextData = [];

    public function __construct(
        protected ContextResolver $contextResolver,
        protected QueuedJobsManager $queuedJobsManager
    ) {
        $this->initializeContextCallbacks();
    }

    /**
     * Initialize automatic context resolution and restoration using ContextResolver.
     */
    protected function initializeContextCallbacks(): void
    {
        $this->queuedJobsManager->resolveContextUsing(function () {
            return array_filter([
                'tenant_id' => $this->contextResolver->tenantId(),
                'school_id' => $this->contextResolver->schoolId(),
                'user_id'   => $this->contextResolver->userId(),
                'module'    => $this->contextResolver->currentModule(),
            ], fn($val) => $val !== null && $val !== '');
        });

        $this->queuedJobsManager->restoreContextUsing(function (QueueContext $context) {
            if ($tenantId = $context->tenantId()) {
                $this->contextResolver->initializeTenant($tenantId);
            }

            if ($schoolId = $context->schoolId()) {
                $this->contextResolver->initializeSchool($schoolId);
            }
        });
    }

    /**
     * Explicitly scope execution to a specific school.
     */
    public function forSchool(?string $schoolId = null): static
    {
        $clone = clone $this;
        $clone->contextData['school_id'] = $schoolId ?? $this->contextResolver->schoolId();
        $clone->contextData['tenant_id'] ??= $this->contextResolver->tenantId();

        return $clone;
    }

    /**
     * Explicitly scope execution to a specific tenant.
     */
    public function forTenant(?string $tenantId = null): static
    {
        $clone = clone $this;
        $clone->contextData['tenant_id'] = $tenantId ?? $this->contextResolver->tenantId();

        return $clone;
    }

    /**
     * Set explicit custom context key-value pairs.
     */
    public function withContext(array|MessageContext $context): static
    {
        $clone = clone $this;
        $data = $context instanceof MessageContext ? $context->all() : $context;
        $clone->contextData = array_merge($clone->contextData, $data);

        return $clone;
    }

    /**
     * Merge ambient application context with explicit overrides.
     */
    public function withAutoScope(): static
    {
        $ambient = array_filter([
            'tenant_id' => $this->contextResolver->tenantId(),
            'school_id' => $this->contextResolver->schoolId(),
            'user_id'   => $this->contextResolver->userId(),
            'module'    => $this->contextResolver->currentModule(),
        ], fn($val) => $val !== null && $val !== '');

        $clone = clone $this;
        $clone->contextData = array_merge($ambient, $this->contextData);

        return $clone;
    }

    /**
     * Start building a queued job using the manager, pre-configured with auto-scoped context.
     */
    public function job(object $job): JobBuilder
    {
        $scoped = $this->withAutoScope();
        $cleanContext = array_filter($scoped->contextData, fn($val) => $val !== null && $val !== '');

        return $this->queuedJobsManager->job($job)->withContext($cleanContext);
    }

    /**
     * Shortcut to build and dispatch a job immediately with the adapter's context.
     */
    public function dispatch(object $job): mixed
    {
        return $this->job($job)->dispatch();
    }

    /**
     * Delegate any other unresolved calls directly to the underlying QueuedJobsManager instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->queuedJobsManager->{$method}(...$parameters);
    }
}
