<?php

namespace SchoolPalm\ModuleBridge\Pipeline;

class ActionRegistry
{
    public static function map(): array
    {
        return [

            //  VERIFICATION
            'verify_module_does_not_exists' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\VerifyModuleDoesNotExistsAction::class,
                'phase' => 'verification',
            ],

            'verify_not_installed' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\VerifyModuleNotInstalledAction::class,
                'phase' => 'verification',
            ],
            'verify_module_structure' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\VerifyModuleStructureAction::class,
                'phase' => 'verification',
            ],
            'verify_module_contract_dto_compliance' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\VerifyModuleContractDTOComplianceAction::class,
                'phase' => 'verification',
            ],
            
            'verify_sdk_compatibility' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\VerifySdkCompatibilityAction::class,
                'phase' => 'verification',
            ],
            'verify_backend_dependencies' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\VerifyBackendDependenciesAction::class,
                'phase' => 'verification',
            ],

            'verify_dependency_contracts' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\VerifyDependencyContractsAction::class,
                'phase' => 'verification',
            ],

            'verify_frontend_resources' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\VerifyFrontendDependenciesAction::class,
                'phase' => 'verification',
            ],



            'deploy_backend_to_execution_directory' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\MoveModuleToExecutionDirectoryAction::class,
                'phase' => 'installation',
            ],

            'deploy_frontend_to_execution_directory' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\InstallFrontendDependenciesAction::class,
                'phase' => 'installation',
            ],

            'install_backend_dependencies' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\InstallBackendDependenciesAction::class,
                'phase' => 'installation',
            ],
            'run_migrations' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\RunModuleMigrationsAction::class,
                'phase' => 'installation',
            ],

            'run_module_DB_seeders' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\RunModuleDBSeedersAction::class,
                'phase' => 'installation',
            ],



            // 📦 REGISTRATION
            'register_module_for_execution' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\RegisterModuleAction::class,
                'phase' => 'registration',
            ],


            //  FINALIZATION
            'emit_module_installation_event' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\EmitModuleInstalledEventAction::class,
                'phase' => 'finalization',
            ],
            'cleanup_transit_module' => [
                'class' => \SchoolPalm\ModuleBridge\Pipeline\Actions\CleanupTransitModuleAction::class,
                'phase' => 'finalization',
            ]


        ];
    }

    public static function resolve(string $key): ?string
    {
        return self::map()[$key]['class'] ?? null;
    }

    public static function generatePipeline(): array
    {
        $registry = self::map();
        $grouped = [];

        foreach ($registry as $key => $meta) {

            $phase = $meta['phase'];

            $grouped[$phase][] = [
                'key' => $key,
                'name' => self::humanize($key),
            ];
        }

        $pipeline = [];

        foreach ($grouped as $phase => $steps) {
            $pipeline[] = [
                'phase' => $phase,
                'steps' => $steps,
            ];
        }

        return $pipeline;
    }

    public  static function humanize(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
