<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use Illuminate\Support\Str;
use SchoolPalm\MessageDelivery\Notification\DTO\NotificationDispatch;
use SchoolPalm\ModuleBridge\Services\ContextResolver;

/**
 * Class NotificationDispatchProxy
 *
 * Proxy adapter around NotificationDispatch to automatically scope templates
 * to module view namespaces and inject host runtime context ($tenant, $school, $user, $module).
 *
 * @mixin NotificationDispatch
 *
 * @method self to(mixed $recipients)
 * @method self notification(string $notificationClass)
 * @method self channel(string $channel)
 * @method self channels(array $channels)
 * @method self title(string $title)
 * @method self message(string $message)
 * @method self data(array $data)
 * @method self with(array $data)
 * @method self template(?string $template, bool $useKey = true)
 * @method self view(string $view, array $data = [], bool $useKey = true)
 * @method self delay(\DateTimeInterface|\DateInterval|int|null $delay)
 * @method mixed dispatch()
 * @method mixed queue()
 * @method mixed send()
 */
final class NotificationDispatchProxy
{
    /**
     * @param NotificationDispatch $dispatch
     * @param ContextResolver|null $contextResolver
     * @param array<string, mixed> $contextData
     */
    public function __construct(
        protected NotificationDispatch $dispatch,
        protected ?ContextResolver $contextResolver = null,
        protected array $contextData = []
    ) {}

    /**
     * Set or merge payload data onto the notification dispatch,
     * automatically injecting bridge context ($module, $tenant, $school, $user).
     *
     * @param array<string, mixed> $data
     * @return $this
     */
    public function data(array $data): self
    {
        $context = $this->resolveContextData();

        // Merge bridge context with user-supplied data (supplied data overrides context on conflict)
        $mergedData = array_merge($context, $data);

        $this->dispatch->data($mergedData);

        return $this;
    }

    /**
     * Alias for setting payload data.
     *
     * @param array<string, mixed> $data
     * @return $this
     */
    public function with(array $data): self
    {
        return $this->data($data);
    }

    /**
     * Resolve and prefix module namespace for notification templates automatically.
     *
     * @param string|null $template
     * @param bool $useKey
     * @return $this
     */
    public function template(?string $template, bool $useKey = true): self
    {
        if ($template !== null) {
            $template = $this->resolveViewOrTemplateKey($template, $useKey);
        }

        $this->dispatch->template($template);

        return $this;
    }

    /**
     * Convenience view method that binds payload data and resolves module view namespace.
     *
     * @param string $view View template name (e.g. 'emails.welcome' or 'mail.invoice')
     * @param array<string, mixed> $data View variables to pass to the template
     * @param bool $useKey Whether to auto-prefix the view name with kebab-cased module key
     * @return $this
     */
    public function view(string $view, array $data = [], bool $useKey = true): self
    {
        // 1. Merge bridge runtime context ($tenant, $school, $user, $module) with user data
        $this->data($data);

        // 2. Resolve view namespace (e.g., 'fees-management::emails.welcome')
        $resolvedView = $this->resolveViewOrTemplateKey($view, $useKey);

        // 3. Delegate directly to NotificationDispatch's underlying view/template setter
        if (method_exists($this->dispatch, 'view')) {
            $this->dispatch->view($resolvedView, $data);
        } else {
            $this->template($resolvedView, false);
        }

        return $this;
    }

    /**
     * Get the underlying NotificationDispatch instance.
     */
    public function getDispatch(): NotificationDispatch
    {
        return $this->dispatch;
    }

    /**
     * Resolve full context array from resolver or stored context data.
     *
     * @return array<string, mixed>
     */
    protected function resolveContextData(): array
    {
        if ($this->contextResolver !== null) {
            return array_merge($this->contextResolver->toArray(), $this->contextData);
        }

        return $this->contextData;
    }

    /**
     * Prefix template/view key with kebab-cased module namespace if '::' is missing.
     */
    protected function resolveViewOrTemplateKey(string $key, bool $useKey = true): string
    {
        if (str_contains($key, '::') || !$useKey) {
            return $key;
        }

        $context = $this->resolveContextData();

        // Extract module key directly or from nested module array shape
        $moduleKey = $this->contextResolver?->currentModuleKey()
            ?? $context['module_key']
            ?? (is_array($context['module'] ?? null) ? ($context['module']['module_key'] ?? null) : $context['module'])
            ?? null;

        if ($moduleKey) {
            $prefix = Str::kebab(str_replace('\\', '.', (string) $moduleKey));
            return $prefix . '::' . $key;
        }

        return $key;
    }

    /**
     * Forward all other calls to the underlying NotificationDispatch instance.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call(string $method, array $parameters): mixed
    {
        $proxiedMethods = ['data', 'with', 'template', 'view'];

        if (in_array($method, $proxiedMethods, true)) {
            throw new \BadMethodCallException(sprintf(
                'Method [%s] is explicitly implemented on [%s] and should not fall through to magic call.',
                $method,
                static::class
            ));
        }

        $result = $this->dispatch->{$method}(...$parameters);

        if ($result === $this->dispatch || (is_object($result) && get_class($result) === get_class($this->dispatch))) {
            $this->dispatch = $result;
            return $this;
        }

        return $result;
    }
}
