<?php

namespace SchoolPalm\ModuleBridge\Manifest;

final class ManifestMenuItem
{
    public function __construct(
        private readonly array $data
    ) {}

    public function name(): string
    {
        return $this->data['name'];
    }

    public function label(): string
    {
        return $this->data['label'];
    }

    public function icon(): string
    {
        return $this->data['icon'];
    }

    public function permission(): string
    {
        return $this->data['permission'];
    }

    public function route(): ?string
    {
        return $this->data['route'] ?? null;
    }

    public function description(): string
    {
        return $this->data['description'] ?? '';
    }

    /** @return ManifestMenuItem[] */
    public function children(): array
    {
        $children = $this->data['children'] ?? [];
        return array_map(fn($item) => new self($item), $children);
    }

    public function raw(): array
    {
        return $this->data;
    }
}
