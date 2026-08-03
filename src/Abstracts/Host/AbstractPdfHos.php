<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Abstracts\Host;

use SchoolPalm\ModuleBridge\Contracts\Host\PdfHost;
use SchoolPalm\ModuleBridge\Jobs\PdfJob;
use DateTimeInterface;

/**
 * AbstractPdfHost
 *
 * Shared logic for all PDF engines (mPDF, Chrome, TCPDF).
 */
abstract class AbstractPdfHost implements PdfHost
{
    protected PdfJob $job;

    public function __construct()
    {
        $this->job = new PdfJob();
    }

    /* -------------------------
     | Content
     |------------------------- */

    public function view(string $view, array $data = []): static
    {
        $this->job->view = $view;
        $this->job->data = $data;

        return $this;
    }

    public function html(string $html): static
    {
        $this->job->html = $html;

        return $this;
    }

    /* -------------------------
     | Config
     |------------------------- */

    public function paper(string $size): static
    {
        $this->job->paper = $size;
        return $this;
    }

    public function portrait(): static
    {
        $this->job->orientation = 'portrait';
        return $this;
    }

    public function landscape(): static
    {
        $this->job->orientation = 'landscape';
        return $this;
    }

    public function title(string $title): static
    {
        $this->job->metadata['title'] = $title;
        return $this;
    }

    public function author(string $author): static
    {
        $this->job->metadata['author'] = $author;
        return $this;
    }

    public function margins(int $top, int $right, int $bottom, int $left): static
    {
        $this->job->margins = compact('top', 'right', 'bottom', 'left');
        return $this;
    }

    /* -------------------------
     | Async
     |------------------------- */

    public function later(DateTimeInterface $time): string
    {
        $this->job->executeAt = $time;

        return $this->dispatchJob($this->job);
    }

    /* -------------------------
     | Batch entry point
     |------------------------- */

    public function batch(): \SchoolPalm\ModuleBridge\Contracts\Host\PdfBatchHost
    {
        return app(\SchoolPalm\ModuleBridge\Contracts\Host\PdfBatchHost::class);
    }

    /* -------------------------
     | Abstract engine methods
     |------------------------- */

    abstract protected function render(PdfJob $job): string;

    abstract protected function dispatchJob(PdfJob $job): string;
}