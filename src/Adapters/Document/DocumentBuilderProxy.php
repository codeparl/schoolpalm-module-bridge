<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters\Document;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use UnnovateBrains\DocumentBuilder\Contracts\Source;
use UnnovateBrains\DocumentBuilder\DocumentBuilder;
use UnnovateBrains\DocumentBuilder\Support\DocumentMetadata;
use UnnovateBrains\DocumentBuilder\Support\DocumentResult;
use UnnovateBrains\DocumentBuilder\Support\ExecutionPlan;
use SchoolPalm\ModuleBridge\Services\ContextResolver;
use UnnovateBrains\DocumentBuilder\Support\DocumentResponse;

/**
 * @method self fromArray(array $data)
 * @method self fromCollection(Collection $collection)
 * @method self fromQuery(string $model)
 * @method self fromModel(Model $model)
 * @method self fromJson(string $json)
 * @method self fromSource(Source $source)
 *
 * @method self view(string $view, array $data = [], bool $useKey = true)
 * @method self templateEngine(string $engine)
 * @method self filename(string $filename)
 * @method self saveTo(string $path)
 *
 * @method self context(array $context)
 * @method self withContext(array $context)
 * @method self tenant(mixed $tenant)
 * @method self school(mixed $school)
 * @method self academicYear(mixed $year)
 * @method self term(mixed $term)
 * @method self user(mixed $user)
 * @method self locale(string $locale)
 * @method self timezone(string $timezone)
 * @method self metadata(array|DocumentMetadata $metadata)
 *
 * @method self chunk(int $size)
 * @method self merge(bool $merge = true)
 *
 * @method self queue()
 * @method self sync()
 * @method self withoutQueue()
 *
 * @method self engine(string $engine)
 * @method self option(string $key, mixed $value)
 * @method self options(array $options)
 *
 * @method self driverConfig(array $config)
 * @method array getDriverConfig()
 * @method self setDriverOption(string $driver, string $key, mixed $value)
 * @method self setDriverOptions(string $driver, array $options)
 *
 * @method ExecutionPlan compilePlan()
 *
 * @method DocumentResult|mixed save()
 * @method DocumentResponse download()
 * @method Response deleteAfter()
 * @method DocumentResponse stream()
 * @method mixed dispatch()
 *
 * @see DocumentBuilder
 */
final class DocumentBuilderProxy
{
    protected array $contextData = [];
    protected ?string $tenantIdOverride = null;
    protected ?string $schoolIdOverride = null;

    /**
     * @param DocumentBuilder $builder
     * @param ?ContextResolver $contextResolver
     * @param array $contextData
     * @param ?string $tenantIdOverride
     * @param ?string $schoolIdOverride
     */
    public function __construct(
        protected DocumentBuilder $builder,
        protected ?ContextResolver $contextResolver = null,
        array $contextData = [],
        ?string $tenantIdOverride = null,
        ?string $schoolIdOverride = null
    ) {
        $this->contextData = $contextData;
        $this->tenantIdOverride = $tenantIdOverride;
        $this->schoolIdOverride = $schoolIdOverride;
    }

    /**
     * Define the structural view template layout with automatic module namespace prefixing.
     */
    public function view(string $view, array $data = [], bool $useKey = true): self
    {

        $moduleKey = null;

        if ($this->contextResolver !== null) {
            $moduleKey = $this->contextResolver->currentModuleKey();
        }

        if (!$moduleKey) {
            $moduleKey = $this->contextData['module_key'] ?? null;
        }

        if (!str_contains($view, '::') && $moduleKey && $useKey) {
            $prefix = Str::kebab(str_replace('\\', '.', $moduleKey));
            $view = $prefix . '::' . $view;
        }

        // Capture the returned builder instance in case it uses immutability/cloning
        $this->builder = $this->builder->view($view, $data);

        return $this;
    }

    public function download(bool $deleteAfter = true): \Symfony\Component\HttpFoundation\Response
    {
        return $this->builder->download($deleteAfter);
    }

    public function stream(bool $deleteAfter = true): \Symfony\Component\HttpFoundation\Response
    {
        return $this->builder->stream($deleteAfter);
    }


    /**
     * Handle dynamic method calls by proxying them to the underlying DocumentBuilder instance.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     */
    /**
     * Handle dynamic method calls by proxying them to the underlying DocumentBuilder instance.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->builder->{$method}(...$parameters);

        /*
     * Preserve fluent chaining.
     *
     * If DocumentBuilder returns itself, return the proxy
     * so the caller continues working with the proxy.
     */
        if ($result instanceof DocumentBuilder) {
            $this->builder = $result;

            return $this;
        }

        return $result;
    }
}
