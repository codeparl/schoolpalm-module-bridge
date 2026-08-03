<?php

namespace SchoolPalm\ModuleBridge\Manifest;

final class ManifestLicense
{
    public function __construct(
        private readonly array $data
    ) {}

    public function type(): ?string
    {
        return $this->data['license']['type'] ?? null;
    }

    public function path(): ?string
    {
        return $this->data['license']['path'] ?? null;
    }


    public function raw(): array
    {
        return $this->data;
    }
}
