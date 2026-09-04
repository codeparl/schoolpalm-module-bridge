<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Services\Host;

use Illuminate\Contracts\Support\Arrayable;
use SchoolPalm\ModuleBridge\Contracts\Host\ContextHost;
use SchoolPalm\ModuleBridge\Contracts\Host\ModuleHost;
use SchoolPalm\ModuleBridge\Contracts\Host\SchoolHost;
use SchoolPalm\ModuleBridge\Contracts\Host\TenantHost;
use SchoolPalm\ModuleBridge\Contracts\Host\UserHost;
use SchoolPalm\ModuleBridge\Support\ContextData;

class ContextHostService implements ContextHost
{
    public function __construct(
        protected TenantHost $tenant,
        protected SchoolHost $school,
        protected UserHost $user,
        protected ModuleHost $module,
    ) {}

    public function tenant(bool $asArray = false): ContextData|array|null
    {
        return $this->normalizeContext($this->tenant->current(), $asArray);
    }

    public function school(bool $asArray = false): ContextData|array|null
    {
        return $this->normalizeContext($this->school->current(), $asArray);
    }

    public function user(bool $asArray = false): ContextData|array|null
    {
        return $this->normalizeContext($this->user->current(), $asArray);
    }

    public function module(bool $asArray = false): ContextData|array|null
    {
        return $this->normalizeContext($this->module->current(), $asArray);
    }

    /**
     * Export entire context as a flat array for background queues and loggers.
     */
    public function toArray(): array
    {
        return array_filter([
            'tenant' => $this->tenant(true),
            'school' => $this->school(true),
            'user'   => $this->user(true),
            'module' => $this->module(true),
        ]);
    }

    /**
     * Safely normalize context values without raw (array) object casting.
     */
    protected function normalizeContext(mixed $data, bool $asArray): ContextData|array|null
    {
        if ($data === null) {
            return null;
        }

        if ($data instanceof ContextData) {
            return $asArray ? $data->toArray() : $data;
        }

        if ($data instanceof Arrayable) {
            $array = $data->toArray();
            return $asArray ? $array : ContextData::make($array);
        }

        if (is_array($data)) {
            return $asArray ? $data : ContextData::make($data);
        }

        if (is_object($data) && method_exists($data, 'toArray')) {
            $array = $data->toArray();
            return $asArray ? $array : ContextData::make($array);
        }

        return null;
    }
}
