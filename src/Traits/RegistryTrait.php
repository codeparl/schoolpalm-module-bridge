<?php
namespace  SchoolPalm\ModuleBridge\Traits;

use Illuminate\Support\Str;
use SchoolPalm\ModuleBridge\Support\Helper;

trait RegistryTrait{


        /**
     * load manifest file for this module.
     * You can pass module key or manifest path
     * @param string $moduleKey Canonical module identifier.
     */
    public function loadManifest(string $moduleKey):array{
         // If moduleKey contains a directory separator, assume it's a full path to JSON
    if (strpos($moduleKey, DIRECTORY_SEPARATOR) !== false) {
        return Helper::loadJson($moduleKey); // Helper should return [] if file not found
    }
         $module  =  $this->get($moduleKey);
        return Helper::loadJson($module['manifest']);
    
    }

    
   /**
 * Read module setup pipeline by module key.
 *
 * Loads the pipeline JSON from the module path.
 *
 * @param string $moduleKey Canonical module identifier.
 * @return array Pipeline steps as an array
 */
public function getSetupPipeline(string $moduleKey): array
{
    
    // If moduleKey contains a directory separator, assume it's a full path to JSON
    if (strpos($moduleKey, DIRECTORY_SEPARATOR) !== false) {
     $pipelineFile = Str::beforeLast($moduleKey, 'Backend') . 'pipeline.json';
    return Helper::loadJson($pipelineFile ); // Helper should return [] if file not found
    }

    $module = $this->get($moduleKey);
    $pipelineFile = Str::beforeLast($module['path'], 'Backend') . 'pipeline.json';

    return Helper::loadJson($pipelineFile);
}

}