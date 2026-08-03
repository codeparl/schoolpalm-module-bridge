<?php 

namespace SchoolPalm\ModuleBridge\Pipeline;

use SchoolPalm\ModuleBridge\Context\InstallContext;

class PipelineBuilder
{
    public static function build(InstallContext $context, bool $dryRun = false, bool $silent = false): array
    {
        $pipeline = $context->module->pipeline();

        $actions = [];

        foreach ($pipeline as $phase) {
            foreach ($phase['steps'] as $step) {

                $actionClass = ActionRegistry::resolve($step['key']);

                if (!$actionClass) {
                    throw new \RuntimeException("No action mapped for: {$step['key']}");
                }

                $action = new $actionClass();

                if (method_exists($action, 'setKey')) {
                    $action->setKey($step['key']);
                }

                if (method_exists($action, 'setPhase')) {
                    $action->setPhase($phase['phase']);
                }

                if (method_exists($action, 'setExecutionOptions')) {
                    $action->setExecutionOptions($dryRun, $silent);
                }

                $actions[] = $action;
            }
        }

        return $actions;
    }
}