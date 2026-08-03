<?php

namespace SchoolPalm\ModuleBridge\Database;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class SchemaBuilder
{
    protected string $table;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /*
    |--------------------------------------------------------------------------
    | Column Types
    |--------------------------------------------------------------------------
    */

    public function id(string $column = 'id'): ColumnDefinition
    {
        return $this->addColumn($column, 'id');
    }

    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($column, 'string', [$length]);
    }

    public function char(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($column, 'char', [$length]);
    }

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'text');
    }

    public function mediumText(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'mediumText');
    }

    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'longText');
    }

    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'integer');
    }

    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'bigInteger');
    }

    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'unsignedBigInteger');
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'boolean');
    }

    public function float(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn($column, 'float', [$precision, $scale]);
    }

    public function double(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn($column, 'double', [$precision, $scale]);
    }

    public function decimal(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn($column, 'decimal', [$precision, $scale]);
    }

    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'json');
    }

    public function jsonb(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'jsonb');
    }

    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'date');
    }

    public function dateTime(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'dateTime');
    }

    public function time(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'time');
    }

    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'timestamp');
    }

    public function uuid(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'uuid');
    }

    public function foreignId(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'foreignId');
    }

    /*
    |--------------------------------------------------------------------------
    | Column Updates
    |--------------------------------------------------------------------------
    */

    public function renameColumn(string $from, string $to): self
    {
        if (
            Schema::hasColumn($this->table, $from) &&
            !Schema::hasColumn($this->table, $to)
        ) {
            Schema::table($this->table, function (Blueprint $table) use ($from, $to) {
                $table->renameColumn($from, $to);
            });
        }

        return $this;
    }

    public function changeColumn(
        string $column,
        string $type,
        array $params = []
    ): self {
        if (!Schema::hasColumn($this->table, $column)) {
            return $this;
        }

        Schema::table($this->table, function (Blueprint $table) use (
            $column,
            $type,
            $params
        ) {
            $table->$type($column, ...$params)->change();
        });

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Column Removal
    |--------------------------------------------------------------------------
    */

    public function dropColumn(string $column): self
    {
        if (Schema::hasColumn($this->table, $column)) {
            Schema::table($this->table, function (Blueprint $table) use ($column) {
                $table->dropColumn($column);
            });
        }

        return $this;
    }

    public function dropColumns(array $columns): self
    {
        $existing = [];

        foreach ($columns as $column) {
            if (Schema::hasColumn($this->table, $column)) {
                $existing[] = $column;
            }
        }

        if ($existing) {
            Schema::table($this->table, function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Indexes
    |--------------------------------------------------------------------------
    */

    public function index(string $column, ?string $name = null): self
    {
        if (!Schema::hasColumn($this->table, $column)) {
            return $this;
        }

        Schema::table($this->table, function (Blueprint $table) use ($column, $name) {
            $table->index($column, $name);
        });

        return $this;
    }

    public function unique(string $column, ?string $name = null): self
    {
        if (!Schema::hasColumn($this->table, $column)) {
            return $this;
        }

        Schema::table($this->table, function (Blueprint $table) use ($column, $name) {
            $table->unique($column, $name);
        });

        return $this;
    }

    public function fullText(string $column, ?string $name = null): self
    {
        Schema::table($this->table, function (Blueprint $table) use ($column, $name) {
            $table->fullText($column, $name);
        });

        return $this;
    }

    public function dropIndex(string $index): self
    {
        Schema::table($this->table, function (Blueprint $table) use ($index) {
            $table->dropIndex($index);
        });

        return $this;
    }

    public function dropUnique(string $index): self
    {
        Schema::table($this->table, function (Blueprint $table) use ($index) {
            $table->dropUnique($index);
        });

        return $this;
    }

    public function renameIndex(string $from, string $to): self
    {
        Schema::table($this->table, function (Blueprint $table) use ($from, $to) {
            $table->renameIndex($from, $to);
        });

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Foreign Keys
    |--------------------------------------------------------------------------
    */

    public function foreign(
        string $column,
        string $references,
        string $onTable
    ): self {
        Schema::table($this->table, function (Blueprint $table) use (
            $column,
            $references,
            $onTable
        ) {
            $table->foreign($column)
                ->references($references)
                ->on($onTable);
        });

        return $this;
    }

    public function dropForeign(string $column): self
    {
        Schema::table($this->table, function (Blueprint $table) use ($column) {
            $table->dropForeign([$column]);
        });

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Table Operations
    |--------------------------------------------------------------------------
    */

    public function renameTable(string $newName): self
    {
        if (
            Schema::hasTable($this->table) &&
            !Schema::hasTable($newName)
        ) {
            Schema::rename($this->table, $newName);
            $this->table = $newName;
        }

        return $this;
    }

    public function truncate(): self
    {
        \DB::table($this->table)->truncate();

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function hasColumn(string $column): bool
    {
        return Schema::hasColumn($this->table, $column);
    }

    public function hasTable(): bool
    {
        return Schema::hasTable($this->table);
    }

    public function getColumnType(string $column): ?string
    {
        if (!$this->hasColumn($column)) {
            return null;
        }

        return Schema::getColumnType(
            $this->table,
            $column
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Timestamps
    |--------------------------------------------------------------------------
    */

    public function timestamps(): self
    {
        if (!Schema::hasColumn($this->table, 'created_at')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->timestamps();
            });
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Internal
    |--------------------------------------------------------------------------
    */

    protected function addColumn(
        string $column,
        string $type,
        array $params = []
    ): ColumnDefinition {
        if (!Schema::hasColumn($this->table, $column)) {

            Schema::table($this->table, function (Blueprint $table) use (
                $column,
                $type,
                $params
            ) {
                $table->$type($column, ...$params);
            });
        }

        return new ColumnDefinition(
            $this->table,
            $column,
            $type
        );
    }
}