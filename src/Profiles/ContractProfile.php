<?php

namespace SchoolPalm\ModuleBridge\Profiles;

final class ContractProfile
{
    public function __construct(
        public string $type,
        public bool $useDto,
        public bool $useDataFactory,
        public bool $readonly,
        public bool $exposeRelations,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | PRESETS
    |--------------------------------------------------------------------------
    */

    public static function API(): self
    {
        return new self(
            type: 'api',
            useDto: true,
            useDataFactory: false,
            readonly: true,
            exposeRelations: true,
        );
    }

    public static function INTERNAL(): self
    {
        return new self(
            type: 'internal',
            useDto: false,
            useDataFactory: false,
            readonly: false,
            exposeRelations: false,
        );
    }

    public static function SNAPSHOT(): self
    {
        return new self(
            type: 'snapshot',
            useDto: true,
            useDataFactory: true,
            readonly: true,
            exposeRelations: false,
        );
    }

    public static function READ_ONLY(): self
    {
        return new self(
            type: 'read_only',
            useDto: true,
            useDataFactory: false,
            readonly: true,
            exposeRelations: true,
        );
    }
}