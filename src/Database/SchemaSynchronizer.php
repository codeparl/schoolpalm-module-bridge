<?php

namespace SchoolPalm\ModuleBridge\Database;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SchemaSynchronizer
{
    protected array $schema;

    public function __construct(string $jsonPath)
    {
        if (!File::exists($jsonPath)) {
            throw new \Exception("Schema file not found: {$jsonPath}");
        }

        $this->schema = json_decode(File::get($jsonPath), true);
    }

    /*
    |--------------------------------------------------------------------------
    | Sync Table
    |--------------------------------------------------------------------------
    */

    public function sync(): void
    {
        $table = $this->schema['table'];

        if (!Schema::hasTable($table)) {
            $this->createTable();
        } else {
            $this->updateTable();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create Table
    |--------------------------------------------------------------------------
    */

    protected function createTable(): void
    {
        $tableName = $this->schema['table'];
        $columns = $this->schema['columns'];

        Schema::create($tableName, function (Blueprint $table) use ($columns) {

            foreach ($columns as $column) {
                $this->addColumn($table, $column);
            }
        });

            /*
    |--------------------------------------------------------------------------
    | Register migration
    |--------------------------------------------------------------------------
    */

    $this->registerMigration($tableName);
    }




protected function registerMigration(string $table): void
{
    $migrationName = 'create_' . $table . '_table';

    $batch = DB::table('migrations')->max('batch') + 1;

    DB::table('migrations')->insert([
        'migration' => $migrationName,
        'batch' => $batch
    ]);
}

    /*
    |--------------------------------------------------------------------------
    | Update Table Structure
    |--------------------------------------------------------------------------
    */

    protected function updateTable(): void
    {
        $tableName = $this->schema['table'];
        $columns = $this->schema['columns'];

        $existingColumns = Schema::getColumnListing($tableName);

        $schemaColumns = array_column($columns, 'name');

        /*
        |--------------------------------------------------------------------------
        | Add Missing Columns
        |--------------------------------------------------------------------------
        */

        Schema::table($tableName, function (Blueprint $table) use ($columns, $existingColumns) {
            foreach ($columns as $column) {
                if (!in_array($column['name'], $existingColumns)) {
                    $this->addColumn($table, $column);
                }
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Drop Columns Not In Schema
        |--------------------------------------------------------------------------
        */

        $columnsToDrop = array_diff($existingColumns, $schemaColumns);

        if (!empty($columnsToDrop)) {

            Schema::table($tableName, function (Blueprint $table) use ($columnsToDrop) {

                foreach ($columnsToDrop as $column) {

                    // protect primary keys
                    if ($column === 'id') {
                        continue;
                    }

                    $table->dropColumn($column);
                }
            });
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Column Builder
    |--------------------------------------------------------------------------
    */

    protected function addColumn(Blueprint $table, array $column): void
    {
        $type = $column['type'];
        $name = $column['name'];

        $length = $column['length'] ?? null;

        if ($length) {
            $col = $table->$type($name, $length);
        } else {
            $col = $table->$type($name);
        }

        if ($column['nullable'] ?? false) {
            $col->nullable();
        }

        if (array_key_exists('default', $column) && $column['default'] !== null) {
            $col->default($column['default']);
        }
    }
}