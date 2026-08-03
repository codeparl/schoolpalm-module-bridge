<?php
namespace SchoolPalm\ModuleBridge\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use SchoolPalm\ModuleBridge\Database\MigrationSignatureValidator as Validator;

class RunMigration {




public static function execute(string $path, bool $validate = true, string $option = null)
{
    if (!File::exists($path)) {
        throw new \Exception("Migration path does not exist: {$path}");
    }

    $files = File::files($path);

    foreach ($files as $file) {

        // Only process PHP migration files
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $filePath = $file->getRealPath();

        // Step 1: Validate (skip if invalid)
        if ($validate) {
        Validator::validate($filePath);
        }

        // Step 2: Run only this valid migration
        Artisan::call('migrate', [
            '--path' => $filePath,
            '--realpath' => true,
            '--force' => true
        ]);
    }
}

public static function rollbackAll(string $path): void
{
    if (!File::exists($path)) {
        return;
    }

    while (true) {
        // Check if there are migrations from this path still applied
        $ran = collect(DB::table('migrations')->get())
            ->pluck('migration')
            ->filter(function ($migration) use ($path) {
                return str_contains($migration, basename($path));
            });

        if ($ran->isEmpty()) {
            break;
        }

        Artisan::call('migrate:rollback', [
            '--path' => $path,
            '--realpath' => true,
            '--force' => true
        ]);
    }
}

public static function rollback(string $path): void
{
    if (!File::exists($path)) {
        return;
    }

    // Rollback only migrations from this path
    Artisan::call('migrate:rollback', [
        '--path' => $path,
        '--realpath' => true,
        '--force' => true
    ]);
}



 /**
     * Run all seeders in a module's Seeders folder
     */
    public static function runSeeders(string $backendPath, string $moduleNamespace): void
    {
        $seedersPath = rtrim($backendPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Seeders';
        $seedersNamespace = rtrim($moduleNamespace, '\\') . '\\Database\\Seeders';

        if (!File::exists($seedersPath)) {
            return; // no seeders folder
        }

        $files = File::files($seedersPath);

        foreach ($files as $file) {
            $seederClass = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $fullClass = $seedersNamespace . '\\' . $seederClass;
 
            if (!class_exists($fullClass)) {
                continue;
            }

            $seederInstance = app($fullClass);
             

            if (!method_exists($seederInstance, 'run')) {
                continue;
            }

            // Truncate table
            if (method_exists($seederInstance, 'getTableName')) {
                $table = $seederInstance->getTableName();
                if (Schema::hasTable($table)) {
                    Schema::disableForeignKeyConstraints();
                    DB::table($table)->truncate();
                    Schema::enableForeignKeyConstraints();
                }
            }

            // Run seeder
            $seederInstance->run();
        }
    }


    public static function rollbackSeeders(string $backendPath, string $moduleNamespace): void
{
    $seedersPath = rtrim($backendPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Seeders';
    $seedersNamespace = rtrim($moduleNamespace, '\\') . '\\Database\\Seeders';

    if (!File::exists($seedersPath)) {
        return;
    }

    $files = File::files($seedersPath);

    foreach ($files as $file) {

        $seederClass = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        $fullClass = $seedersNamespace . '\\' . $seederClass;

        if (!class_exists($fullClass)) {
            continue;
        }

        $seederInstance = app($fullClass);

        // Only rollback seeders that define a table
        if (!method_exists($seederInstance, 'getTableName')) {
            continue;
        }

        $table = $seederInstance->getTableName();

        if (!Schema::hasTable($table)) {
            continue;
        }

        Schema::disableForeignKeyConstraints();

        DB::table($table)->truncate();

        Schema::enableForeignKeyConstraints();
    }
}
}





