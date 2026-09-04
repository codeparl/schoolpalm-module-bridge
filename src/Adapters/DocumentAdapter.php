<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use Illuminate\Support\Str;
use UnnovateBrains\DocumentBuilder\Facades\Document as DocumentManagerFacade;
use SchoolPalm\ModuleBridge\Adapters\Document\DocumentBuilderProxy;
use SchoolPalm\ModuleBridge\Facades\Host\SettingsHost;
use SchoolPalm\ModuleBridge\Services\ContextResolver;
use UnnovateBrains\DocumentBuilder\DocumentBuilder;

class DocumentAdapter
{
    protected array $contextData = [];
    protected ?string $tenantIdOverride = null;
    protected ?string $schoolIdOverride = null;

    public function __construct(
        protected ContextResolver $contextResolver
    ) {}

    public function forSchool(?string $schoolId = null): static
    {
        $clone = clone $this;
        $clone->schoolIdOverride = $schoolId ?? $this->contextResolver->schoolId();
        $clone->contextData['school_code'] = $clone->schoolIdOverride;
        $clone->contextData['tenant_id'] ??= $this->tenantIdOverride ?? $this->contextResolver->tenantId();

        return $clone;
    }

    public function forTenant(?string $tenantId = null): static
    {
        $clone = clone $this;
        $clone->tenantIdOverride = $tenantId ?? $this->contextResolver->tenantId();
        $clone->contextData['tenant_id'] = $clone->tenantIdOverride;

        return $clone;
    }




    public function withContext(array $context): static
    {
        $clone = clone $this;
        $ambient = array_filter([
            'tenant_id' => $this->tenantIdOverride ?? $this->contextResolver->tenantId(),
            'school_id' => $this->schoolIdOverride ?? $this->contextResolver->schoolId(),
            'user_id'   => $this->contextResolver->userId(),
        ], fn($val) => $val !== null && $val !== '');

        $clone->contextData = array_merge($ambient, $clone->contextData, $context);

        return $clone;
    }

    public function withAutoScope(): static
    {
        $ambient = array_filter([
            'tenant_id' => $this->tenantIdOverride ?? $this->contextResolver->tenantId(),
            'school_id' => $this->schoolIdOverride ?? $this->contextResolver->schoolCode(),
            'user_id'   => $this->contextResolver->userId(),
            'module_key' => $this->contextResolver->currentModuleKey(),
            'module_name' => $this->contextResolver->currentModule(),
            'module_namespace' => $this->contextResolver->currentModuleNamespace(),
        ], fn($val) => $val !== null && $val !== '');

        $clone = clone $this;
        $clone->contextData = array_merge($ambient, $this->contextData);

        return $clone;
    }

    public function pdf(): DocumentBuilderProxy
    {
        return $this->initBuilder(DocumentManagerFacade::pdf());
    }

    public function excel(): DocumentBuilderProxy
    {
        return $this->initBuilder(DocumentManagerFacade::excel());
    }

    public function csv(): DocumentBuilderProxy
    {
        return $this->initBuilder(DocumentManagerFacade::csv());
    }

    public function word(): DocumentBuilderProxy
    {
        return $this->initBuilder(DocumentManagerFacade::word());
    }

    public function html(): DocumentBuilderProxy
    {
        return $this->initBuilder(DocumentManagerFacade::html());
    }

    public function image(): DocumentBuilderProxy
    {
        return $this->initBuilder(DocumentManagerFacade::image());
    }



    public function type(string $type): DocumentBuilderProxy
    {
        $builder = match ($type) {
            'pdf' => DocumentManagerFacade::pdf(),
            'excel', 'xlsx' => DocumentManagerFacade::excel(),
            'csv' => DocumentManagerFacade::csv(),
            'word', 'docx' => DocumentManagerFacade::word(),
            'html' => DocumentManagerFacade::html(),
            'image' => DocumentManagerFacade::image(),
            default => throw new \InvalidArgumentException("Unsupported document type [{$type}]"),
        };

        return $this->initBuilder($builder);
    }




    protected function initBuilder(DocumentBuilder $builder): DocumentBuilderProxy
    {
        if ($this->tenantIdOverride !== null || $this->schoolIdOverride !== null) {
            $this->contextResolver->forContext($this->tenantIdOverride, $this->schoolIdOverride);
        }

        $scoped = $this->withAutoScope();
        $cleanContext = array_filter($scoped->contextData, fn($val) => $val !== null && $val !== '');

        $tenantId = $cleanContext['tenant_id'] ?? null;
        $schoolId = $cleanContext['school_id'] ?? null;

        if (isset($cleanContext['module_key']) && method_exists($builder, 'prefixKey')) {
            $prefix = Str::kebab(str_replace('\\', '-', $cleanContext['module_key']));
            $builder->prefixKey($prefix);
        }

        $brandingConfig = SettingsHost::forTenant($tenantId)
            ->forSchool($schoolId)
            ->group('document_branding')
            ->get('defaults', []);

        $builder->context($cleanContext);

        if (method_exists($builder, 'setContextResolver')) {
            $builder->setContextResolver($this->contextResolver);
        }

        if (!empty($brandingConfig)) {
            $builder->options(['branding' => $brandingConfig]);
        }

        return new DocumentBuilderProxy($builder, $this->contextResolver);
    }
}
