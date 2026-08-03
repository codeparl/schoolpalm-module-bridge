<?php

namespace SchoolPalm\ModuleBridge\Manifest;

use SchoolPalm\ModuleBridge\Support\Helper;

final class ManifestDTOs
{
    public function __construct(
        private readonly array $data
    ) {}

    public function raw(): array
    {
        return $this->data;
    }

    public function namespaces(): array
    {
        return $this->raw()['dtos'] ;
    }

  

    
}
