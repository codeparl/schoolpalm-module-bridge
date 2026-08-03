<?php

namespace SchoolPalm\ModuleBridge\Platform;

final class SdkVersion
{
    private const VERSION = '2.1.0'; 

    private function __construct() {}

    public static function current(): string
    {
        return self::VERSION;
    }

    public static function major(): int
    {
        return (int) explode('.', self::VERSION)[0];
    }

    public static function minor(): int
    {
        return (int) explode('.', self::VERSION)[1];
    }

    public static function patch(): int
    {
        return (int) explode('.', self::VERSION)[2];
    }

    public static function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'major'   => self::major(),
            'minor'   => self::minor(),
            'patch'   => self::patch(),
        ];
    }

    public static function matches(string $constraint): bool
    {
        return version_compare(self::VERSION, $constraint, '>=');
    }
}
