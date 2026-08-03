<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters\Document;

use SchoolPalm\ModuleBridge\Services\ContextResolver;
use SchoolPalm\ModuleBridge\Support\Helper;
use UnnovateBrains\DocumentBuilder\Contracts\ContextHandler;
use UnnovateBrains\DocumentBuilder\Pipelines\Contracts\PipelineContext;

final class SchoolPalmDocumentContextHandler implements ContextHandler
{
    public function __construct(
        private readonly ContextResolver $resolver
    ) {}



    /**
     * Restore SchoolPalm runtime context.
     *
     * Called before document pipeline execution.
     */
    public function enter(
        PipelineContext $context
    ): void {

        /*
        |--------------------------------------------------------------------------
        | SDK runtime
        |--------------------------------------------------------------------------
        |
        | When modules are running outside SchoolPalm,
        | there is no tenant or school runtime to restore.
        |
        */
        if (Helper::isSdkRuntime()) {
            return;
        }



        $plan =
            $context->getPlan();


        $documentContext =
            $plan->getContext();



        /*
        |--------------------------------------------------------------------------
        | Tenant initialization
        |--------------------------------------------------------------------------
        */

        if (
            isset($documentContext['tenant_id'])
        ) {

            $this->resolver->initializeTenant(
                $documentContext['tenant_id']
            );
        }



        /*
        |--------------------------------------------------------------------------
        | School initialization
        |--------------------------------------------------------------------------
        */

        if (
            isset($documentContext['school_id'])
        ) {

            $this->resolver->initializeSchool(
                $documentContext['school_id']
            );
        }



        /*
        |--------------------------------------------------------------------------
        | Locale
        |--------------------------------------------------------------------------
        */

        if (
            isset($documentContext['locale'])
        ) {

            app()->setLocale(
                $documentContext['locale']
            );
        }



        /*
        |--------------------------------------------------------------------------
        | Timezone
        |--------------------------------------------------------------------------
        */

        if (
            isset($documentContext['timezone'])
        ) {

            config([
                'app.timezone' =>
                $documentContext['timezone']
            ]);
        }
    }



    /**
     * Cleanup SchoolPalm runtime context.
     */
    public function leave(
        PipelineContext $context
    ): void {

        if (Helper::isSdkRuntime()) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Cleanup runtime context
        |--------------------------------------------------------------------------
        */

        $this->resolver->clear();



        /*
        |--------------------------------------------------------------------------
        | Restore application locale
        |--------------------------------------------------------------------------
        */

        app()->setLocale(
            config('app.locale')
        );
    }
}
