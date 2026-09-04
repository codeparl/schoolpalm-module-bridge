<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use Illuminate\Support\Str;
use SchoolPalm\MessageDelivery\Builders\ChannelMessageBuilder;
use SchoolPalm\MessageDelivery\Builders\MultiChannelMessageBuilder;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

/**
 * Class MessageDeliveryProxy
 *
 * Direct proxy for MessageDelivery builders that automatically scopes views to module namespaces
 * and injects active runtime host context ($tenant, $school, $user, $module).
 *
 * @mixin ChannelMessageBuilder
 * @mixin MultiChannelMessageBuilder
 *
 * @method self to(string|array $recipients)
 * @method self title(string $title)
 * @method self content(string $content)
 * @method self with(array $data)
 * @method self view(string $view, array $data = [], bool $useKey = true)
 * @method self delay(\DateTimeInterface|\DateInterval|int|null $delay)
 * @method mixed sync()
 * @method mixed dispatch()
 * @method mixed queue()
 */
final class MessageDeliveryProxy
{
    /**
     * @param ChannelMessageBuilder|MultiChannelMessageBuilder|mixed $builder
     * @param ContextResolver|null $contextResolver
     * @param array<string, mixed> $contextData
     */
    public function __construct(
        protected mixed $builder,
        protected ?ContextResolver $contextResolver = null,
        protected array $contextData = []
    ) {}

    /**
     * Resolve and prefix module namespace for view templates automatically,
     * while merging host context data ($module, $tenant, $school, $user) into the view parameters.
     *
     * @param string $view Template path or Blade view name.
     * @param array<string, mixed> $data View parameters to merge with host context.
     * @param bool $useKey Whether to auto-prefix the view name with the kebab-cased module key.
     * @return $this
     */
    public function view(string $view, array $data = [], bool $useKey = true): self
    {
        $moduleKey = null;

        if ($this->contextResolver !== null) {
            $moduleKey = $this->contextResolver->currentModuleKey();
            $resolvedContext = $this->contextResolver->toArray();
            $this->contextData = array_merge($resolvedContext, $this->contextData);
        }

        if (!$moduleKey) {
            $moduleKey = $this->contextData['module']['module_key']
                ?? $this->contextData['module']
                ?? $this->contextData['module_key']
                ?? null;
        }

        // Prefix module view namespace if not already prefixed with '::'
        if (!str_contains($view, '::') && $moduleKey && $useKey) {
            $prefix = Str::kebab(str_replace('\\', '.', (string) $moduleKey));
            $view = $prefix . '::' . $view;
        }

        // Merge host context data ($module, $tenant, $school, $user) with the passed $data array
        $mergedData = array_merge($this->contextData, $data);

        $result = $this->builder->view($view, $mergedData);

        if (is_object($result) && get_class($result) === get_class($this->builder)) {
            $this->builder = $result;
        }

        return $this;
    }

    /**
     * Forward all other builder methods to the underlying builder instance,
     * maintaining the proxy wrapper on fluent returns.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call(string $method, array $parameters): mixed
    {
        $proxiedMethods = ['view'];

        if (in_array($method, $proxiedMethods, true)) {
            throw new \BadMethodCallException(sprintf(
                'Method [%s] is explicitly implemented on [%s] and should not fall through to magic call.',
                $method,
                static::class
            ));
        }

        $result = $this->builder->{$method}(...$parameters);

        if ($result === $this->builder || (is_object($result) && get_class($result) === get_class($this->builder))) {
            $this->builder = $result;
            return $this;
        }

        return $result;
    }
}
