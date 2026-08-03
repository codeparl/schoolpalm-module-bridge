<?php

namespace SchoolPalm\ModuleBridge\Database;

use RuntimeException;
use Illuminate\Support\Facades\Schema;
use SchoolPalm\ModuleBridge\Database\MigrationSignatureValidator as Validator;

class UpdateMigration
{
    protected string $migrationClass;
    protected string $migrationPath;
    protected string $table;

    public function __construct(
        string $migrationClass,
        string $migrationPath,
        ?string $table = null
    ) {
        $this->migrationClass = $migrationClass;
        $this->migrationPath = $migrationPath;
        $this->table = $table;
    }

    public function execute(): void
    {
        if (!class_exists($this->migrationClass)) {

            $file = rtrim($this->migrationPath, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . class_basename($this->migrationClass) . '.php';

            if (!file_exists($file)) {
                throw new RuntimeException("Migration file not found.");
            }

            require_once $file;
        }

        if (!class_exists($this->migrationClass)) {
            throw new RuntimeException("Migration class not found.");
        }

        $migration = new ($this->migrationClass)();

   
        $tableName = method_exists($migration, 'tableName')
            ? $migration->tableName()
            : $this->table;

        /*
        |--------------------------------------------------------------------------
        | Execute Modifier Methods Only If Table Exists
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasTable($tableName)) {
            throw new RuntimeException(
                "Cannot update migration. Table {$tableName} does not exist."
            );
        }

        foreach (['update', 'drop', 'rebuild'] as $modifier) {

            if (method_exists($migration, $modifier)) {
                $migration->$modifier();
            }
        }
    }
}