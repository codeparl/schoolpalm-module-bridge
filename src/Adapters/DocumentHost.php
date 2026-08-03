<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use SchoolPalm\ModuleBridge\Services\ContextResolver;
use UnnovateBrains\DocumentBuilder\Facades\Document;
use UnnovateBrains\DocumentBuilder\DocumentBuilder;

final class DocumentHost
{
    public function __construct(
        private readonly ContextResolver $resolver
    ) {}

    /**
     * @return DocumentBuilderProxy
     */

    public function pdf(): DocumentBuilderAdapter
    {
        return $this->prepare(
            Document::pdf()
        );
    }



    public function excel(): DocumentBuilderAdapter
    {
        return $this->prepare(
            Document::excel()
        );
    }



    public function word(): DocumentBuilderAdapter
    {
        return $this->prepare(
            Document::word()
        );
    }



    public function csv(): DocumentBuilderAdapter
    {
        return $this->prepare(
            Document::csv()
        );
    }



    public function image(): DocumentBuilderAdapter
    {
        return $this->prepare(
            Document::image()
        );
    }



    protected function prepare(
        DocumentBuilder $builder
    ): DocumentBuilderAdapter {

        return new DocumentBuilderAdapter(
            $builder->context(
                $this->resolver->resolve()
            )
        );
    }
}
