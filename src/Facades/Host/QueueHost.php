<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Facades\Host;

use Illuminate\Support\Facades\Facade;
use SchoolPalm\ModuleBridge\Adapters\QueuedJobsAdapter;

/**
 * @method static \SchoolPalm\QueuedJobs\Builders\JobBuilder job(object $job)
 * @method static mixed dispatch(object $job)
 * @method static \SchoolPalm\ModuleBridge\Adapters\QueuedJobsAdapter forSchool(?string $schoolId = null)
 * @method static \SchoolPalm\ModuleBridge\Adapters\QueuedJobsAdapter forTenant(?string $tenantId = null)
 * @method static \SchoolPalm\ModuleBridge\Adapters\QueuedJobsAdapter withContext(array|\SchoolPalm\MessageDelivery\Context\MessageContext|\SchoolPalm\QueuedJobs\Context\QueueContext $context)
 * @method static \SchoolPalm\ModuleBridge\Adapters\QueuedJobsAdapter withAutoScope()
 * 
 * @method static \SchoolPalm\QueuedJobs\Builders\JobBuilder withTenant(string|int $tenantId)
 * @method static \SchoolPalm\QueuedJobs\Builders\JobBuilder withSchool(string|int $schoolId)
 * @method static \SchoolPalm\QueuedJobs\Builders\JobBuilder withUser(string|int $userId)
 * @method static \SchoolPalm\QueuedJobs\Builders\JobBuilder withModule(string $module)
 * @method static \SchoolPalm\QueuedJobs\Builders\JobBuilder withMetadata(array $metadata)
 * @method static \SchoolPalm\QueuedJobs\Builders\JobBuilder prepare()
 * 
 * @see \SchoolPalm\ModuleBridge\Adapters\QueuedJobsAdapter
 */
class QueueHost extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return QueuedJobsAdapter::class;
    }
}
