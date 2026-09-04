<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use SchoolPalm\MessageDelivery\Context\MessageContext;
use SchoolPalm\ModuleBridge\Services\ContextResolver;
use SchoolPalm\QueuedJobs\Builders\JobBuilder;
use SchoolPalm\QueuedJobs\Builders\JobResultBuilder;
use SchoolPalm\QueuedJobs\Context\QueueContext;
use SchoolPalm\QueuedJobs\Managers\QueuedJobsManager;
use SchoolPalm\QueuedJobs\Models\QueueJobResult;
use SchoolPalm\QueuedJobs\Resources\JobResultResource;

class QueuedJobsAdapter
{
    protected array $contextData = [];

    protected ?JobBuilder $activeBuilder = null;

    public function __construct(
        protected ContextResolver $contextResolver,
        protected QueuedJobsManager $queuedJobsManager
    ) {
        $this->initializeContextCallbacks();
    }

    protected function initializeContextCallbacks(): void
    {
        $this->queuedJobsManager->resolveContextUsing(function () {
            return array_filter([
                'tenant_id' => $this->contextResolver->tenantId(),
                'school_id' => $this->contextResolver->schoolId(true),
                'user_id'   => $this->contextResolver->userId(),
                'module'    => $this->contextResolver->currentModule(),
            ], fn($val) => $val !== null && $val !== '');
        });

        $this->queuedJobsManager->restoreContextUsing(function (QueueContext $context) {
            if ($tenantId = $context->tenantId()) {
                $this->contextResolver->initializeTenant($tenantId);
            }

            if ($schoolId = $context->schoolId(true)) {
                $this->contextResolver->initializeSchool($schoolId);
            }
        });
    }

    public function forSchool($schoolId = null): static
    {
        $clone = clone $this;
        $clone->contextData['school_id'] = $schoolId ?? $this->contextResolver->schoolId(true);
        $clone->contextData['tenant_id'] ??= $this->contextResolver->tenantId();

        return $clone;
    }

    public function forTenant(?string $tenantId = null): static
    {
        $clone = clone $this;
        $clone->contextData['tenant_id'] = $tenantId ?? $this->contextResolver->tenantId();

        return $clone;
    }

    public function withContext(array|MessageContext $context): static
    {
        $clone = clone $this;
        $data = $context instanceof MessageContext ? $context->all() : $context;
        $clone->contextData = array_merge($clone->contextData, $data);

        return $clone;
    }

    public function withAutoScope(): static
    {
        $ambient = array_filter([
            'tenant_id' => $this->contextResolver->tenantId(),
            'school_id' => $this->contextResolver->schoolId(true),
            'user_id'   => $this->contextResolver->userId(),
            'module'    => $this->contextResolver->currentModule(),
        ], fn($val) => $val !== null && $val !== '');

        $clone = clone $this;
        $clone->contextData = array_merge($ambient, $this->contextData);

        return $clone;
    }

    /**
     * Start building a queued job using JobBuilder with auto-scoped context.
     */
    public function job(object $job): static
    {
        $clone = clone $this;
        $scoped = $clone->withAutoScope();
        $cleanContext = array_filter($scoped->contextData, fn($val) => $val !== null && $val !== '');

        $clone->activeBuilder = $clone->queuedJobsManager->job($job)->withContext($cleanContext);

        return $clone;
    }

    /**
     * Start querying persisted job result models/resources.
     */
    public function results(): JobResultBuilder
    {
        return $this->queuedJobsManager->jobs();
    }

    /**
     * Get the created job result model for the active job builder.
     */
    public function result(): ?QueueJobResult
    {
        return $this->activeBuilder?->result();
    }

    /**
     * Get the created job result resource for the active job builder.
     */
    public function resultResource(): ?JobResultResource
    {
        return $this->activeBuilder?->resultResource();
    }

    /**
     * Get the created job result array for the active job builder.
     */
    public function resultArray(): ?array
    {
        return $this->activeBuilder?->resultArray();
    }

    /**
     * Dispatch the job.
     */
    public function dispatch(?object $job = null): mixed
    {
        if ($job !== null) {
            return $this->job($job)->dispatch();
        }

        if ($this->activeBuilder) {
            return $this->activeBuilder->dispatch();
        }

        throw new \BadMethodCallException('No job active to dispatch.');
    }

    /**
     * Proxy builder execution calls (withMetadata, delay, onQueue, etc.) directly to JobBuilder.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if ($this->activeBuilder && method_exists($this->activeBuilder, $method)) {
            $result = $this->activeBuilder->{$method}(...$parameters);

            if ($result instanceof JobBuilder) {
                return $this;
            }

            return $result;
        }

        if (method_exists($this->queuedJobsManager, $method)) {
            return $this->queuedJobsManager->{$method}(...$parameters);
        }

        throw new \BadMethodCallException("Method [{$method}] does not exist on " . static::class);
    }
}
