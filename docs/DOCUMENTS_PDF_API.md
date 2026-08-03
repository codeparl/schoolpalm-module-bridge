# ModuleBridge Documents - PDF API Usage

This document explains how to use the ModuleBridge PDF document generation API.

## High-level flow

A document request passes through a pipeline before reaching the underlying PDF driver (e.g. mPDF).

```
Host::pdf()
  -> view(...)
  -> download();
```

Internally, a document job is created and processed through stages such as:
- resolving Blade view / raw HTML content
- preparing options, metadata, and assets (extension points)
- rendering via the selected driver
- producing the final output (download/save/stream)

## Public API examples

### 1) Render a Blade view and download as PDF
```php
Host::pdf()
    ->view('documents.invoice', [
        'invoice' => $invoice,
    ])
    ->download();
```

### 2) Render raw HTML and save as PDF
```php
Host::pdf()
    ->content($html)
    ->save('invoices/invoice-'.($invoice->id ?? 'x'));
```

### 3) Stream PDF to the browser
```php
Host::pdf()
    ->view('reports.student', [
        'student' => $student,
    ])
    ->stream('report-'.($student->id ?? 'x'));
```

### 4) Customize driver options (orientation / format)
```php
Host::pdf()
    ->content($html)
    ->options([
        'format' => 'A4',
        'orientation' => 'P', // or 'L'
    ])
    ->download();
```

> Note: Exact option keys are driver-defined. The pipeline forwards `DocumentJob::$options` to the driver.

## Extending the pipeline (stages)

The pipeline architecture is designed so other modules can add stages in the future (theme injection, branding, watermarks, QR codes, encryption/signatures, etc.).

### Where stages live
- `src/Documents/Pipelines/DocumentPipeline.php`
- `src/Documents/Pipelines/PipelineStage.php`
- `src/Documents/Pipelines/Stages/*`

### Adding a custom stage
1. Create a class implementing `SchoolPalm\ModuleBridge\Documents\Pipelines\PipelineStage`.
2. In `handle(DocumentContext $context, Closure $next)`, mutate the context as needed.
3. Call `$next($context)`.

## Notes

- The pipeline keeps preprocessing out of drivers; drivers should only render using prepared context.
- This repo already contains the pipeline scaffolding under `src/Documents/Pipelines/`.


