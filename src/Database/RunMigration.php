<?php

namespace SchoolPalm\ModuleBridge\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use SchoolPalm\ModuleBridge\Database\MigrationSignatureValidator as Validator;
use Illuminate\Support\Str;
use RuntimeException;
use SchoolPalm\ModuleBridge\Support\Helper;

class RunMigration
{
    public static function execute(
        string $path,
        bool $validate = true,
        ?string $option = null
    ): void {
        if (!File::isDirectory($path)) {
            throw new RuntimeException(
                "Migration path does not exist: {$path}"
            );
        }

        /*
    |--------------------------------------------------------------------------
    | 1. Resolve module root
    |--------------------------------------------------------------------------
    */

        $migrationPath = realpath($path);

        if ($migrationPath === false) {
            throw new RuntimeException(
                "Unable to resolve migration path: {$path}"
            );
        }

        $moduleRoot = dirname(
            dirname(
                dirname($migrationPath)
            )
        );

        $manifestPath =
            $moduleRoot .
            DIRECTORY_SEPARATOR .
            'manifest.json';

        if (!File::exists($manifestPath)) {
            throw new RuntimeException(
                "Module manifest not found: {$manifestPath}"
            );
        }

        $manifest = Helper::loadJson($manifestPath);

        if (!is_array($manifest)) {
            throw new RuntimeException(
                "Invalid module manifest: {$manifestPath}"
            );
        }

        /*
    |--------------------------------------------------------------------------
    | 2. Get tables declared by this module
    |--------------------------------------------------------------------------
    */

        $manifestTables =
            $manifest['migrations']['tables'] ?? [];

        if (!is_array($manifestTables)) {
            $manifestTables = [];
        }

        /*
    |--------------------------------------------------------------------------
    | 3. Build lookup by logical table name
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | contacts => [
    |     'name' => 'contacts',
    |     'depends_on' => []
    | ]
    |
    | staff => [
    |     'name' => 'staff',
    |     'depends_on' => ['contacts']
    | ]
    |
    */

        $declaredTables = [];

        foreach ($manifestTables as $tableDefinition) {
            if (!is_array($tableDefinition)) {
                continue;
            }

            $logicalName = $tableDefinition['name'] ?? null;

            if (
                !is_string($logicalName) ||
                $logicalName === ''
            ) {
                continue;
            }

            $declaredTables[$logicalName] = $tableDefinition;
        }

        /*
    |--------------------------------------------------------------------------
    | 4. Build migration file lookup
    |--------------------------------------------------------------------------
    |
    | Convert:
    |
    | 2026_08_31_130234_create_schoolpalm_common_staff_staff_table.php
    |
    | into:
    |
    | schoolpalm_common_staff_staff
    |
    */

        $migrationFiles = [];

        $files = File::files($migrationPath);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filename = pathinfo(
                $file->getFilename(),
                PATHINFO_FILENAME
            );

            $physicalTableName = preg_replace(
                '/^\d{4}_\d{2}_\d{2}_\d{6}_/',
                '',
                $filename
            );

            $physicalTableName = preg_replace(
                '/^create_/',
                '',
                $physicalTableName
            );

            $physicalTableName = preg_replace(
                '/_table$/',
                '',
                $physicalTableName
            );

            /*
        |--------------------------------------------------------------------------
        | Resolve the logical table name.
        |--------------------------------------------------------------------------
        |
        | Manifest:
        |
        |     staff
        |     contacts
        |
        | Migration:
        |
        |     schoolpalm_common_staff_staff
        |     schoolpalm_common_staff_contacts
        |
        */

            $logicalName = null;

            /*
         * Exact match first.
         */
            if (isset($declaredTables[$physicalTableName])) {
                $logicalName = $physicalTableName;
            }

            /*
         * Otherwise match the physical table suffix
         * against a declared logical table name.
         */
            if ($logicalName === null) {
                foreach ($declaredTables as $declaredName => $definition) {
                    if (
                        $physicalTableName === $declaredName ||
                        str_ends_with(
                            $physicalTableName,
                            '_' . $declaredName
                        )
                    ) {
                        $logicalName = $declaredName;
                        break;
                    }
                }
            }

            /*
         * Migration does not belong to this module.
         */
            if ($logicalName === null) {
                continue;
            }

            $filePath = $file->getRealPath();

            if ($filePath === false) {
                continue;
            }

            $migrationFiles[$logicalName] = [
                'path' => $filePath,
                'physical_name' => $physicalTableName,
            ];
        }

        /*
    |--------------------------------------------------------------------------
    | 5. Execute migrations recursively according to depends_on
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | staff
    |   depends_on: [contacts]
    |
    | contacts
    |   depends_on: []
    |
    | Result:
    |
    | contacts
    | staff
    |
    */

        $executed = [];
        $executing = [];

        $executeTable = function (string $logicalName) use (
            &$executeTable,
            &$executed,
            &$executing,
            $declaredTables,
            $migrationFiles,
            $validate
        ): void {
            /*
         * Already processed.
         */
            if (isset($executed[$logicalName])) {
                return;
            }

            /*
         * Detect circular dependencies.
         *
         * Example:
         *
         * staff -> contacts
         * contacts -> staff
         */
            if (isset($executing[$logicalName])) {
                throw new RuntimeException(
                    "Circular migration dependency detected involving table: {$logicalName}"
                );
            }

            /*
         * The dependency may not belong to this module.
         *
         * In that case it is assumed to be supplied
         * externally and does not need to be executed here.
         */
            if (!isset($declaredTables[$logicalName])) {
                return;
            }

            /*
         * Mark as currently executing so circular
         * dependencies can be detected.
         */
            $executing[$logicalName] = true;

            $tableDefinition =
                $declaredTables[$logicalName];

            /*
        |--------------------------------------------------------------------------
        | Execute dependencies first
        |--------------------------------------------------------------------------
        */

            $dependsOn =
                $tableDefinition['depends_on'] ?? [];

            if (!is_array($dependsOn)) {
                $dependsOn = [];
            }

            foreach ($dependsOn as $dependency) {
                if (!is_string($dependency)) {
                    continue;
                }

                $dependency = trim($dependency);

                if ($dependency === '') {
                    continue;
                }

                $executeTable($dependency);
            }

            /*
        |--------------------------------------------------------------------------
        | Execute this table
        |--------------------------------------------------------------------------
        */

            if (isset($migrationFiles[$logicalName])) {
                $filePath =
                    $migrationFiles[$logicalName]['path'];

                if ($validate) {
                    Validator::validate($filePath);
                }

                \Illuminate\Support\Facades\Artisan::call(
                    'migrate',
                    [
                        '--path' => $filePath,
                        '--realpath' => true,
                        '--force' => true,
                    ]
                );
            }

            /*
         * No longer in the dependency stack.
         */
            unset($executing[$logicalName]);

            /*
         * Mark as completed.
         */
            $executed[$logicalName] = true;
        };

        /*
    |--------------------------------------------------------------------------
    | 6. Execute all declared tables
    |--------------------------------------------------------------------------
    |
    | The order here no longer matters.
    |
    | If staff appears before contacts in the manifest:
    |
    |     staff -> contacts
    |
    | the recursive dependency resolver executes:
    |
    |     contacts
    |     staff
    |
    */

        foreach ($declaredTables as $logicalName => $tableDefinition) {
            $executeTable($logicalName);
        }
    }

    /**
     * Purges all tables associated with the manifest/tables array and removes migration records.
     *
     * @param array $manifestOrTables Full manifest array OR $tables array.
     */
    public static function rollbackAll(array $manifestOrTables): void
    {
        self::dropAllTables($manifestOrTables);
    }

    /**
     * Purges all tables associated with the manifest/tables array and removes migration records.
     *
     * @param array $manifestOrTables Full manifest array OR $tables array.
     */
    public static function rollback(array $manifestOrTables): void
    {
        self::dropAllTables($manifestOrTables);
    }


    protected static function dropForeignKeysReferencingTable(string $tableName): void
    {
        $database = DB::connection()->getDatabaseName();

        $foreignKeys = DB::select(
            "
        SELECT
            TABLE_NAME,
            CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE CONSTRAINT_SCHEMA = ?
          AND REFERENCED_TABLE_NAME = ?
          AND REFERENCED_TABLE_NAME IS NOT NULL
        ",
            [
                $database,
                $tableName,
            ]
        );

        foreach ($foreignKeys as $foreignKey) {
            if (!Schema::hasTable($foreignKey->TABLE_NAME)) {
                continue;
            }

            Schema::table(
                $foreignKey->TABLE_NAME,
                function ($table) use ($foreignKey) {
                    $table->dropForeign($foreignKey->CONSTRAINT_NAME);
                }
            );
        }
    }
    /**
     * Force-drops all database tables declared in the manifest/tables array 
     * and deletes the records from Laravel's `migrations` table.
     *
     * @param array $manifestOrTables Can be full $manifest or direct $tables array.
     */
    public static function dropAllTables(array $manifestOrTables): void
    {
        // 1. Resolve $tables array whether $manifest or $tables was passed
        $tables = $manifestOrTables['migrations']['tables']
            ?? $manifestOrTables['tables']
            ?? $manifestOrTables;

        if (!is_array($tables) || empty($tables)) {
            return;
        }

        $tablesToDrop = [];
        $migrationFiles = [];

        foreach ($tables as $tableItem) {
            // Priority 1: Parse table name from schema path
            if (!empty($tableItem['schema'])) {
                $schemaFilename = pathinfo($tableItem['schema'], PATHINFO_FILENAME);
                // e.g., 2026_08_28_202214_create_schoolpalm_common_student_students_table

                // Keep migration filename for deleting migration table records
                $migrationFiles[] = $schemaFilename;

                // Step A: Strip leading timestamp (e.g., 2026_08_28_202214_)
                $cleanName = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $schemaFilename);

                // Step B: Strip leading "create_" prefix
                $cleanName = preg_replace('/^create_/', '', $cleanName);

                // Step C: Strip trailing "_table" suffix
                $cleanName = preg_replace('/_table$/', '', $cleanName);

                $tablesToDrop[] = $cleanName;
            } elseif (!empty($tableItem['name'])) {
                // Priority 2: Use direct table name property if schema path isn't present
                $tablesToDrop[] = $tableItem['name'];
            }
        }

        $tablesToDrop = array_unique(array_filter($tablesToDrop));
        // 2. Drop identified tables with foreign keys temporarily disabled
        foreach ($tablesToDrop as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            self::dropForeignKeysReferencingTable($tableName);

            Schema::dropIfExists($tableName);
        }

        Schema::enableForeignKeyConstraints();

        // 3. Remove corresponding entries from the `migrations` table
        if (Schema::hasTable('migrations') && !empty($migrationFiles)) {
            DB::table('migrations')
                ->whereIn('migration', $migrationFiles)
                ->delete();
        }
    }


    /**
     * Force-drops a single table, deletes its record from Laravel's `migrations` table,
     * and deletes matching schema and migration files from disk.
     *
     * @param string $tableName Name of the table or full schema path
     * @param string|null $migrationsPath Absolute path to the migrations directory
     * @param string|null $schemasPath Absolute path to the schemas directory
     */
    public static function dropTable(
        string $tableName,
        ?string $migrationsPath = null
    ): void {
        /*
    |--------------------------------------------------------------------------
    | 1. Resolve table name
    |--------------------------------------------------------------------------
    */

        if (str_contains($tableName, '/') || str_contains($tableName, '\\')) {
            $filename = pathinfo($tableName, PATHINFO_FILENAME);

            $cleanTable = preg_replace(
                '/^\d{4}_\d{2}_\d{2}_\d{6}_/',
                '',
                $filename
            );

            $cleanTable = preg_replace(
                '/^create_/',
                '',
                $cleanTable
            );

            $cleanTable = preg_replace(
                '/_table$/',
                '',
                $cleanTable
            );
        } else {
            $cleanTable = $tableName;
        }


        $cleanTable = trim($cleanTable);

        if ($cleanTable === '') {
            return;
        }


        /*
    |--------------------------------------------------------------------------
    | 2. NEVER allow this method to drop Laravel's migration table
    |--------------------------------------------------------------------------
    */

        if ($cleanTable === 'migrations') {
            throw new RuntimeException(
                'Refusing to drop Laravel\'s migrations table.'
            );
        }

        /*
    |--------------------------------------------------------------------------
    | 3. Drop the actual application/module table
    |--------------------------------------------------------------------------
    */

        if (Schema::hasTable($cleanTable)) {
            self::dropForeignKeysReferencingTable($cleanTable);

            Schema::dropIfExists($cleanTable);
        }

        /*
    |--------------------------------------------------------------------------
    | 4. Resolve migration directories
    |--------------------------------------------------------------------------
    */

        if ($migrationsPath === null) {
            return;
        }

        $migrationsDir = rtrim(
            $migrationsPath,
            DIRECTORY_SEPARATOR
        );

        $schemasDir = $migrationsDir
            . DIRECTORY_SEPARATOR
            . 'schemas';

        /*
    |--------------------------------------------------------------------------
    | 5. Find generated migration files
    |--------------------------------------------------------------------------
    */

        $deletedMigrationNames = [];

        $migrationPattern = "*_create_{$cleanTable}_table.php";

        if (File::isDirectory($migrationsDir)) {
            $files = File::glob(
                $migrationsDir . DIRECTORY_SEPARATOR . $migrationPattern
            );

            foreach ($files as $filePath) {
                $deletedMigrationNames[] = pathinfo(
                    $filePath,
                    PATHINFO_FILENAME
                );

                File::delete($filePath);
            }
        }

        /*
    |--------------------------------------------------------------------------
    | 6. Delete generated schema files
    |--------------------------------------------------------------------------
    */

        if (File::isDirectory($schemasDir)) {
            $schemaPattern = "*_create_{$cleanTable}_table.*";

            $schemaFiles = File::glob(
                $schemasDir . DIRECTORY_SEPARATOR . $schemaPattern
            );

            foreach ($schemaFiles as $filePath) {
                File::delete($filePath);
            }
        }

        /*
    |--------------------------------------------------------------------------
    | 7. Remove migration history ONLY for the deleted module migration
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | Do not blindly delete from migrations based on the table name.
    | Only remove records for migration files that we actually
    | found and deleted above.
    |
    */


        if (
            !empty($deletedMigrationNames) &&
            Schema::hasTable('migrations')
        ) {
            DB::table('migrations')
                ->whereIn('migration', $deletedMigrationNames)
                ->delete();
        }
    }





    /**
     * Run all seeders in a module's Seeders folder
     */
    public static function runSeeders(string $backendPath, string $moduleNamespace): void
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


    /**
     * Execute update() and drop() methods on all migration files for a given directory.
     * Must be called BEFORE inspecting or regenerating schema JSONs from the database.
     *
     * @param string $migrationPath Absolute path to the migrations directory
     * @return array List of processed operations (e.g. ['users (update)', 'posts (drop)'])
     * @throws \RuntimeException
     */
    public static function applyMigrationUpdates(string $migrationPath): array
    {
        if (!File::exists($migrationPath)) {
            return [];
        }

        $files = File::files($migrationPath);
        $processed = [];

        foreach ($files as $file) {
            $filepath = $file->getPathname();

            require_once $filepath;

            $filename = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $filenameWithoutTimestamp = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $filename);
            $className = Str::studly($filenameWithoutTimestamp);


            if (!class_exists($className)) {
                continue;
            }

            $migration = new $className();


            if (!$migration instanceof BaseMigration) {
                continue;
            }

            $tableName = $migration->tableName();

            // Ensure table exists in database before running alter operations
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // Execute column modifications / additions
            if (method_exists($migration, 'update')) {
                try {
                    $migration->update();
                    $processed[] = $tableName . ' (update)';
                } catch (\Exception $e) {
                    throw new \RuntimeException(
                        "Migration update failed for table {$tableName}: " . $e->getMessage(),
                        (int) $e->getCode(),
                        $e
                    );
                }
            }

            // Execute column drops
            if (method_exists($migration, 'drop')) {
                try {
                    $migration->drop();
                    $processed[] = $tableName . ' (drop)';
                } catch (\Exception $e) {
                    throw new \RuntimeException(
                        "Migration drop failed for table {$tableName}: " . $e->getMessage(),
                        (int) $e->getCode(),
                        $e
                    );
                }
            }
        }

        return $processed;
    }

    /**
     * Execute update() and drop() methods for a specific table's migration file.
     * Must be called AFTER migration files are updated and BEFORE regenerating schema JSON.
     *
     * @param string $tableName Name of the database table
     * @param string $migrationPath Absolute path to migrations directory
     * @return bool True if a matching migration was executed, false otherwise
     * @throws \RuntimeException
     */
    public static function applyTableMigrationUpdate(string $tableName, string $migrationPath): bool
    {


        $migrationPath =  config('sdk.modules.root') . '/' . $migrationPath;
        $migrationPath =  str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $migrationPath);
        $migrationPath =  dirname($migrationPath);
        if (!File::exists($migrationPath) || !Schema::hasTable($tableName)) {
            return false;
        }


        $files = File::files($migrationPath);

        foreach ($files as $file) {
            $filepath = $file->getPathname();
            $filename = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $filenameWithoutTimestamp = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $filename);
            $className = Str::studly($filenameWithoutTimestamp);

            // Standard laravel convention check or include file to check instance
            require_once $filepath;

            if (!class_exists($className)) {
                continue;
            }

            $migration = new $className();

            if (!$migration instanceof BaseMigration) {
                continue;
            }


            // Match exact table name
            if ($migration->tableName() !== $tableName) {
                continue;
            }

            // 1. Run update()
            if (method_exists($migration, 'update')) {
                try {
                    $migration->update();
                } catch (\Exception $e) {
                    throw new \RuntimeException(
                        "Migration update failed for table {$tableName}: " . $e->getMessage(),
                        (int) $e->getCode(),
                        $e
                    );
                }
            }

            // 2. Run drop()
            if (method_exists($migration, 'drop')) {
                try {
                    $migration->drop();
                } catch (\Exception $e) {
                    throw new \RuntimeException(
                        "Migration drop failed for table {$tableName}: " . $e->getMessage(),
                        (int) $e->getCode(),
                        $e
                    );
                }
            }

            return true;
        }

        return false;
    }
}
