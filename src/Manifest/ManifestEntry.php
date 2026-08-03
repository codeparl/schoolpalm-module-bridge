<?php

namespace SchoolPalm\ModuleBridge\Manifest;

final class ManifestEntry
{
    public function __construct(
        private readonly array $data
    ) {}

    public function raw(): array
    {
        return $this->data;
    }

    public function provider(): ?string
    {
        return $this->data['provider'] ?? null;
    }

 public function providerClass(): ?string
{
    $provider = $this->data['provider'] ?? null;

    if (!$provider || !is_string($provider)) {
        return null;
    }

    return class_basename($provider);
}

 public function providerFile(): ?string
{
    $provider = $this->data['provider'] ?? null;

    if (!$provider || !is_string($provider)) {
        return null;
    }

    return class_basename($provider).'.php';
}


    public function hasProvider(): bool
    {
        return !empty($this->provider());
    }

    public function hasEntry(): bool
    {
        return $this->hasProvider();
    }
}
