<?php

namespace SchoolPalm\ModuleBridge\Manifest;

final class ManifestActionItem
{
    public function __construct(
        private readonly array $data
    ) {}

    public function name(): string
    {
        return $this->data['name'];
    }

    public function permission(): string
    {
        return $this->data['permission'];
    }

    public function route(): string
    {
        return $this->data['route'];
    }

    public function description(): string
    {
        return $this->data['description'] ?? '';
    }

    public function raw(): array
    {
        return $this->data;
    }
}
