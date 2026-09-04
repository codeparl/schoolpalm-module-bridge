<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Support;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final class ContextData implements ArrayAccess, Arrayable, JsonSerializable
{
    public function __construct(protected array $attributes = []) {}

    public static function make(array $attributes = []): self
    {
        return new self($attributes);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            $value = $this->attributes[$key];

            if (is_array($value)) {
                return static::make($value);
            }

            return $value;
        }

        return $default;
    }

    public function toArray(): array
    {
        return array_map(function ($value) {
            if ($value instanceof Arrayable) {
                return $value->toArray();
            }

            if (is_array($value)) {
                return array_map(
                    fn($item) => $item instanceof Arrayable ? $item->toArray() : $item,
                    $value
                );
            }

            return $value;
        }, $this->attributes);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Magic property getter ($tenant->id, $module->name)
     */
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Allows direct string rendering inside Blade without TypeError: {{ $tenant }} or {{ $module }}
     */
    public function __toString(): string
    {
        return (string) (
            $this->attributes['id']
            ?? $this->attributes['school_code']
            ?? $this->attributes['name']
            ?? $this->attributes['module_key']
            ?? ''
        );
    }

    /* ArrayAccess Implementation */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }
}
