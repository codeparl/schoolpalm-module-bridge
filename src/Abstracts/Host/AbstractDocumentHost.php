<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Abstracts\Host;

use DateTimeInterface;
use SchoolPalm\ModuleBridge\Documents\DocumentJob;

abstract class AbstractDocumentHost
{
    protected DocumentJob $job;

    public function __construct()
    {
        $this->job = new DocumentJob();
    }

    public function filename(string $name): static
    {
        $this->job->filename = $name;

        return $this;
    }

    public function metadata(array $metadata): static
    {
        $this->job->metadata = $metadata;

        return $this;
    }

    public function option(string $key, mixed $value): static
    {
        $this->job->options[$key] = $value;

        return $this;
    }

    public function temporary(string $directory): static
    {
        $this->job->temporaryDirectory = $directory;

        return $this;
    }

    public function later(DateTimeInterface $time): static
    {
        $this->job->later = $time;

        return $this;
    }

    public function job(): DocumentJob
    {
        return $this->job;
    }
}

