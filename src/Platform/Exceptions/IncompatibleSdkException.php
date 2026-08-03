<?php

namespace SchoolPalm\ModuleBridge\Platform\Exceptions;

use RuntimeException;

final class IncompatibleSdkException extends RuntimeException
{
    public function __construct(string $required, string $current)
    {
        parent::__construct(
            "Module requires SDK version {$required}, " .
            "but SchoolPalm is running {$current}."
        );
    }
}
