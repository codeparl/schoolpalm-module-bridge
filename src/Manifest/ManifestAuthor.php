<?php

namespace SchoolPalm\ModuleBridge\Manifest;

final class ManifestAuthor
{
    public function __construct(
        private readonly array $data
    ) {}

    public function name(): ?string
    {
        return $this->data['author']['name'] ?? null;
    }

    public function email(): ?string
    {
        return $this->data['author']['email'] ?? null;
    }

    public function website(): ?string
    {
        return $this->data['author']['website'] ?? null;
    }

    public function author(): ?string
    {
        return $this->data['author'] ?? null;
    }

    public function raw(): array
    {
        return $this->data;
    }
}
