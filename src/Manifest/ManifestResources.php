<?php

namespace SchoolPalm\ModuleBridge\Manifest;

final class ManifestResources
{
    public function __construct(
        private readonly array $data
    ) {}

    public function raw(): array
    {
        return $this->data;
    }

    public function mainJs_path(): ?string
    {
        return $this->data['js_main'] ?? null;
    }

    public function mainCss_path(): ?string
    {
        return $this->data['css_main'] ?? null;
    }

   

    public function assetsPath(): ?string
    {
        return $this->data['assets'] ?? null;
    }

    public function hasAssets(): bool
    {
        return !empty($this->data['assets']);
    }
}
