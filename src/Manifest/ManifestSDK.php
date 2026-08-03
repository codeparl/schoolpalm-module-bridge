<?php

namespace SchoolPalm\ModuleBridge\Manifest;

final class ManifestSDK
{
    public function __construct(
        private readonly array $data
    ) {}

    public function raw(): array
    {
        return $this->data;
    }

    public function name(): ?string
    {
        return $this->data['name'] ?? null;
    }

    public function version(): string
    {
       return $this->data['version'] ?? null;
    }

    
}
