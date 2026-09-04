<?php

namespace SchoolPalm\ModuleBridge\Database;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

abstract class BaseMigration extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Lifecycle Hooks
    |--------------------------------------------------------------------------
    */

    public function update()
    {
        // Vendor overrides when schema update is needed
    }

    public function drop()
    {
        // Vendor overrides when module schema is removed
    }

    public function rebuild()
    {
        // Vendor overrides when schema rebuild is required
    }

    /*
    |--------------------------------------------------------------------------
    | Table Prefix Resolver
    |--------------------------------------------------------------------------
    */

    protected string $prefix = '';

    protected SchemaBuilder $schema;

    public function __construct()
    {
        $this->schema = new SchemaBuilder(
            $this->tableName()
        );
    }

    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    abstract public function tableName(): string;

    /*
    |--------------------------------------------------------------------------
    | Schema Builder Entry
    |--------------------------------------------------------------------------
    */

    protected function schemaBuilder(): SchemaBuilder
    {
        return $this->schema;
    }

    /*
    |--------------------------------------------------------------------------
    | Table Creation
    |--------------------------------------------------------------------------
    */

    protected function createTable(
        string $name,
        callable $callback
    ): void {
        $table = $this->table($name);

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create(
            $table,
            function (Blueprint $blueprint) use ($callback, $table) {

                /*
                 * Create a SchemaBuilder in CREATE mode.
                 *
                 * The builder writes to the active Blueprint instead
                 * of calling Schema::table(), because the table does
                 * not exist yet.
                 */
                $builder = new SchemaBuilder(
                    $table,
                    $blueprint
                );

                /*
                 * SchoolPalm standard primary key.
                 */
                $blueprint->ulid('id')->primary();

                /*
                 * Tenant/school ownership.
                 */
                $builder
                    ->foreignId('school_id')
                    ->index();

                /*
                 * Developer schema.
                 */
                $callback($builder);

                /*
                 * Standard audit timestamps.
                 */
                $blueprint->timestamps();
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Column Helpers
    |--------------------------------------------------------------------------
    */

    protected function addColumnIfNotExists(
        string $column,
        callable $callback
    ): void {
        $table = $this->table(
            $this->tableName()
        );

        if (!Schema::hasColumn($table, $column)) {
            Schema::table(
                $table,
                function (Blueprint $tableBlueprint) use ($callback) {
                    $callback($tableBlueprint);
                }
            );
        }
    }

    protected function dropColumnIfExists(
        string $column
    ): void {
        $table = $this->table(
            $this->tableName()
        );

        if (Schema::hasColumn($table, $column)) {
            Schema::table(
                $table,
                function (Blueprint $tableBlueprint) use ($column) {
                    $tableBlueprint->dropColumn($column);
                }
            );
        }
    }

    protected function addIndexIfNotExists(
        string $column
    ): void {
        $table = $this->table(
            $this->tableName()
        );

        Schema::table(
            $table,
            function (Blueprint $tableBlueprint) use ($column) {
                $tableBlueprint->index($column);
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Table State
    |--------------------------------------------------------------------------
    */

    protected function tableExists(
        string $name
    ): bool {
        return Schema::hasTable(
            $this->table($name)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Migration Record
    |--------------------------------------------------------------------------
    */

    private function removeMigrationRecord(
        string $migrationName
    ): void {
        DB::table('migrations')
            ->where('migration', $migrationName)
            ->delete();
    }

    public function down(
        string $migrationName
    ): void {
        Schema::dropIfExists(
            $this->tableName()
        );

        $this->removeMigrationRecord(
            $migrationName
        );
    }
}
