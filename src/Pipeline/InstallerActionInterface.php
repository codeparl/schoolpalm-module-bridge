<?php

namespace SchoolPalm\ModuleBridge\Pipeline;

use SchoolPalm\ModuleBridge\Context\InstallContext;

interface InstallerActionInterface
{
    public function key(): string;   
    public function phase(): string;

    public function execute(InstallContext $context): void;

    public function canExecute(InstallContext $context): bool;

    public function rollback(InstallContext $context): void;

    public function isReversible(): bool;
}
