<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use Illuminate\Support\Facades\File;
use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;

class VerifyModuleContractDTOComplianceAction extends AbstractInstallerAction
{
    protected string $phase = 'verification';
    protected bool $reversible = false;

    public function key(): string
    {
        return 'verify_module_contract_dto_compliance';
    }

    public function execute(InstallContext $context): void
    {
        $module = $context->module;

        $this->log($context, "Verifying DTO existence for contracts...");

        $isProtected   = $module->isProtected();
        $contracts     = $module->providedContracts;
        $dtoNamespaces = $module->dtos;

        $moduleRoot = $context->transit_path . '/' . $module->root();

        if ($isProtected || empty($contracts)) {
            $this->success($context, "No DTO validation required.");
            return;
        }

        if (empty($dtoNamespaces)) {
            $this->fail($context, "DTO namespaces must be defined in manifest");
        }

        $missingDtos = [];

        foreach ($contracts as $contractFQCN) {

            // Extract class name
            $contractClass = str_contains($contractFQCN, '\\')
                ? class_basename($contractFQCN)
                : $contractFQCN;

            if (!str_ends_with($contractClass, 'Contract')) {
                $this->fail($context, "Invalid contract naming: {$contractClass}");
            }

            // StudentContract → Student
            $baseName = substr($contractClass, 0, -strlen('Contract'));

            // Student → StudentData
            $dtoClass = $baseName . 'Data';

            $dtoFound = false;

            foreach ($dtoNamespaces as $namespace) {

                $dtoFQCN = rtrim($namespace, '\\');
                // Convert FQCN → file path
                $relativePath = str_replace('\\', '/', $dtoFQCN) . '.php';
                $fullPath = $context->transit_path  . '/' . $relativePath;
                if (!File::exists($fullPath)) {
                    continue;
                }

                // Optional but good: confirm class name inside file
                $content = File::get($fullPath);
               
if (preg_match('/\b(?:final\s+|abstract\s+)?class\s+' . preg_quote($dtoClass, '/') . '\b/i', $content)) {
    $dtoFound = true;
    break;
}
            }

            if (!$dtoFound) {
                $missingDtos[] = "{$dtoClass} (for {$contractClass})";
            }
        }

        if (!empty($missingDtos)) {
            $this->fail(
                $context,
                "Missing DTO files: " . implode(', ', $missingDtos)
            );
        }

        $this->success(
            $context,
            "All required DTO files exist for declared contracts."
        );
    }

    public function rollback(InstallContext $context): void
    {
        // No rollback needed
    }
}
