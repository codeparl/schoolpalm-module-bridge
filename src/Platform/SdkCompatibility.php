<?php

namespace SchoolPalm\ModuleBridge\Platform;

use Composer\Semver\Semver;
use SchoolPalm\ModuleBridge\Platform\Exceptions\IncompatibleSdkException;

final class SdkCompatibility
{
    /**
     * Rules are intentional, not inferred.
     */
    private const COMPATIBILITY_MATRIX = [
        '1.x' => '>=1.0 <2.0',
        '2.x' => '>=2.0 <3.0',
    ];

    private function __construct() {}

    public static function assertCompatible(string $requiredSdk): void
    {
        if (! self::isCompatible($requiredSdk)) {
            throw new IncompatibleSdkException(
                $requiredSdk,
                SdkVersion::current()
            );
        }
    }

public static function isCompatible(string $requiredSdk): bool
{
    return Semver::satisfies(SdkVersion::current(), '^' . $requiredSdk);
}
}
