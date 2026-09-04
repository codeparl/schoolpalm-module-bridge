<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Facades\Host;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Facade;
use SchoolPalm\ModuleBridge\Adapters\DocumentAdapter;
use UnnovateBrains\DocumentBuilder\DocumentBuilder;
use UnnovateBrains\DocumentBuilder\Support\DocumentResponse;

/**
 * @method static DocumentAdapter forSchool(?string $schoolId = null)
 * @method static DocumentAdapter forTenant(?string $tenantId = null)
 * @method static DocumentAdapter withContext(array $context)
 * @method static DocumentAdapter withAutoScope()
 * @method static DocumentBuilder type(string $type)
 * @method static DocumentBuilder pdf()
 * @method static DocumentBuilder xlsx()
 * @method static mixed generate(\UnnovateBrains\DocumentBuilder\Support\ExecutionPlan $plan)
 * @method static mixed dispatch(\UnnovateBrains\DocumentBuilder\Support\ExecutionPlan $plan)
 * @method static string createBatch(\UnnovateBrains\DocumentBuilder\Support\ExecutionPlan $plan)
 * @method static array status(string $batchId)
 * @method static void updateStatus(string $batchId, array $status)
 * @method static string final(string $batchId, string $type = 'pdf')
 * @method static void cleanup(string $batchId)
 * @method DocumentResponse download()
 * @method Response deleteAfter()
 * @method DocumentResponse stream()
 * @method self view(string $view, array $data = [], bool $useKey = true)
 * @see DocumentAdapter
 */
class DocumentHost extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DocumentAdapter::class;
    }
}
