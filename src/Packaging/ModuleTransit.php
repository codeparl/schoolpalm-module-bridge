<?php
namespace SchoolPalm\ModuleBridge\Packaging;

use Illuminate\Support\Facades\File;
use SchoolPalm\ModuleBridge\Support\Helper;

class ModuleTransit
{
    protected string $transitFile;

    /**
     * Constructor.
     *
     * @param string|null $transitFile Optional path to transit JSON
     */
    public function __construct(?string $transitFile = null)
    {
        if(!$transitFile || !config('sdk.modules.transit_file')){
        $transitFile =  Helper::dataPath();
        }
        $this->transitFile = $transitFile ?? config('sdk.modules.transit_file');


    }

    /**
     * Read the full transit file.
     *
     * @return array
     */
    public function read(): array
    {
        if (!File::exists($this->transitFile)) {
            return [
                'modules_root_path' => '',
                'modules' => []
            ];
        }

        return Helper::loadJson($this->transitFile) ?? [
                'modules_root_path' => '',
                'modules' => []
            ];
    }

    /**
     * Add or update a module in transit.
     *
     * @param array $moduleData
     */
    public function add(array $moduleData): void
    {
        $transit = $this->read();

        $found = false;
        foreach ($transit['modules'] as &$module) {
            if ($module['namespace'] === $moduleData['namespace']) {
                $module = array_merge($module, $moduleData);
                $found = true;
                break;
            }
        }
        unset($module);

        if (!$found) {
            $transit['modules'][] = $moduleData;
        }

        File::ensureDirectoryExists(dirname($this->transitFile));
        Helper::storeJson($this->transitFile, $transit);
    }

    /**
     * Update a module by namespace, e.g., extracted status or any fields.
     *
     * @param string $namespace
     * @param array $updateData
     */
    public function update(string $namespace, array $updateData): void
    {
        $transit = $this->read();
        $updated = false;

        foreach ($transit['modules'] as &$module) {
            if ($module['namespace'] === $namespace) {
                $module = array_merge($module, $updateData);
                $updated = true;
                break;
            }
        }
        unset($module);

        if ($updated) {
            Helper::storeJson($this->transitFile, $transit);
        }
    }

    /**
     * Remove a module from transit by namespace.
     *
     * @param string $namespace
     */
    public function remove(string $namespace): void
    {
        $transit = $this->read();

        $transit['modules'] = array_values(array_filter(
            $transit['modules'],
            fn($m) => $m['namespace'] !== $namespace
        ));

        Helper::storeJson($this->transitFile, $transit);
    }

    /**
     * Find a module by namespace.
     *
     * @param string $namespace
     * @return array|null
     */
    public function find(string $namespace): ?array
    {
        $transit = $this->read();
        foreach ($transit['modules'] as $module) {
            if ($module['namespace'] === $namespace) {
                return $module;
            }
        }
        return null;
    }
}