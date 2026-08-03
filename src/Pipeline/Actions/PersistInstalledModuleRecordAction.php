<?php

namespace SchoolPalm\ModuleBridge\Pipeline\Actions;

use Illuminate\Support\Carbon;
use SchoolPalm\ModuleBridge\Pipeline\AbstractInstallerAction;
use SchoolPalm\ModuleBridge\Context\InstallContext;
use Illuminate\Support\Facades\DB;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;

class PersistInstalledModuleRecordAction extends AbstractInstallerAction
{
    protected string $phase = 'Registration & Finalization';
    protected bool $reversible = true;

    public function execute(InstallContext $context): void
    {
        if ($this->dryRun || ($context->isSdkHost ?? false)) {
            if (!$this->silent) {
                echo "[SKIP] PersistInstalledModuleRecordAction skipped (SDK or dry run).\n";
            }
            return;
        }

        /** @var ModuleManifest $module */
        $module = $context->module;

        if (!$module) {
            throw new \RuntimeException("No module selected to persist.");
        }

        $info = $module->info(); // ManifestInfo object

        $table = 'modules'; 
        $record = [
            'module_key' => $module->key(),
            'vendor'     => $info->vendor ?? null,
            'module'     => $info->module ?? null,
            'folder'     => $info->folder ?? null,
            'is_common'  => $module->isCommon(),
            'installed'  => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        $id = DB::table($table)->insertGetId($record);

        $context->installedModuleRecord = array_merge($record, ['id' => $id]);

        if (!$this->silent) {
            echo "[OK] Installed module persisted in DB with ID {$id}.\n";
        }
    }

    public function rollback(InstallContext $context): void
    {
        if (($context->isSdkHost ?? false) || !$context->installedModuleRecord) {
            return;
        }

        $id = $context->installedModuleRecord['id'] ?? null;

        if ($id) {
            DB::table('modules')->where('id', $id)->delete();

            if (!$this->silent) {
                echo "[Rollback] Installed module record with ID {$id} deleted.\n";
            }
        }

        $context->installedModuleRecord = null;
    }
}
