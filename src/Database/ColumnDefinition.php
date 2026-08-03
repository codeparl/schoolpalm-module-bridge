<?php

namespace SchoolPalm\ModuleBridge\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ColumnDefinition
{
    protected string $table;

    protected string $column;

    protected string $type;

    protected array $params;

    public function __construct(
        string $table,
        string $column,
        string $type,
        array $params = []
    ) {
        $this->table = $table;
        $this->column = $column;
        $this->type = $type;
        $this->params = $params;
    }

    /*
    |--------------------------------------------------------------------------
    | Internal Modifier Handler
    |--------------------------------------------------------------------------
    */

    protected function modify(callable $callback): static
    {
        Schema::table($this->table, function (Blueprint $table) use ($callback) {

            $column = $table->{$this->type}(
                $this->column,
                ...$this->params
            );

            $callback($column);

            $column->change();
        });

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Column Modifiers
    |--------------------------------------------------------------------------
    */

    public function nullable(bool $value = true): static
    {
        return $this->modify(function ($column) use ($value) {

            if ($value) {
                $column->nullable();
            }

        });
    }

    public function default(mixed $value): static
    {
        return $this->modify(function ($column) use ($value) {
            $column->default($value);
        });
    }

    public function comment(string $comment): static
    {
        return $this->modify(function ($column) use ($comment) {
            $column->comment($comment);
        });
    }

    public function after(string $column): static
    {
        return $this->modify(function ($col) use ($column) {
            $col->after($column);
        });
    }

    public function first(): static
    {
        return $this->modify(function ($column) {
            $column->first();
        });
    }

    public function unsigned(): static
    {
        return $this->modify(function ($column) {
            $column->unsigned();
        });
    }

    public function charset(string $charset): static
    {
        return $this->modify(function ($column) use ($charset) {
            $column->charset($charset);
        });
    }

    public function collation(string $collation): static
    {
        return $this->modify(function ($column) use ($collation) {
            $column->collation($collation);
        });
    }

    public function invisible(): static
    {
        return $this->modify(function ($column) {
            $column->invisible();
        });
    }

    public function storedAs(string $expression): static
    {
        return $this->modify(function ($column) use ($expression) {
            $column->storedAs($expression);
        });
    }

    public function virtualAs(string $expression): static
    {
        return $this->modify(function ($column) use ($expression) {
            $column->virtualAs($expression);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Indexes
    |--------------------------------------------------------------------------
    */

    public function index(?string $name = null): static
    {
        Schema::table($this->table, function (Blueprint $table) use ($name) {
            $table->index($this->column, $name);
        });

        return $this;
    }

    public function unique(?string $name = null): static
    {
        Schema::table($this->table, function (Blueprint $table) use ($name) {
            $table->unique($this->column, $name);
        });

        return $this;
    }

    public function fullText(?string $name = null): static
    {
        Schema::table($this->table, function (Blueprint $table) use ($name) {
            $table->fullText($this->column, $name);
        });

        return $this;
    }

    public function spatialIndex(?string $name = null): static
    {
        Schema::table($this->table, function (Blueprint $table) use ($name) {
            $table->spatialIndex($this->column, $name);
        });

        return $this;
    }

    public function dropIndex(): static
    {
        Schema::table($this->table, function (Blueprint $table) {
            $table->dropIndex([$this->column]);
        });

        return $this;
    }

    public function dropUnique(): static
    {
        Schema::table($this->table, function (Blueprint $table) {
            $table->dropUnique([$this->column]);
        });

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Foreign Keys
    |--------------------------------------------------------------------------
    */

    public function constrained(
        string $table,
        string $column = 'id'
    ): static {
        Schema::table($this->table, function (Blueprint $tbl) use (
            $table,
            $column
        ) {

            $tbl->foreign($this->column)
                ->references($column)
                ->on($table);

        });

        return $this;
    }

    public function cascadeOnDelete(
        string $table,
        string $column = 'id'
    ): static {
        Schema::table($this->table, function (Blueprint $tbl) use (
            $table,
            $column
        ) {

            $tbl->dropForeign([$this->column]);

            $tbl->foreign($this->column)
                ->references($column)
                ->on($table)
                ->cascadeOnDelete();

        });

        return $this;
    }

    public function cascadeOnUpdate(
        string $table,
        string $column = 'id'
    ): static {
        Schema::table($this->table, function (Blueprint $tbl) use (
            $table,
            $column
        ) {

            $tbl->dropForeign([$this->column]);

            $tbl->foreign($this->column)
                ->references($column)
                ->on($table)
                ->cascadeOnUpdate();

        });

        return $this;
    }

    public function nullOnDelete(
        string $table,
        string $column = 'id'
    ): static {
        Schema::table($this->table, function (Blueprint $tbl) use (
            $table,
            $column
        ) {

            $tbl->dropForeign([$this->column]);

            $tbl->foreign($this->column)
                ->references($column)
                ->on($table)
                ->nullOnDelete();

        });

        return $this;
    }

    public function restrictOnDelete(
        string $table,
        string $column = 'id'
    ): static {
        Schema::table($this->table, function (Blueprint $tbl) use (
            $table,
            $column
        ) {

            $tbl->dropForeign([$this->column]);

            $tbl->foreign($this->column)
                ->references($column)
                ->on($table)
                ->restrictOnDelete();

        });

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Column Removal
    |--------------------------------------------------------------------------
    */

    public function drop(): bool
    {
        if (!Schema::hasColumn($this->table, $this->column)) {
            return false;
        }

        Schema::table($this->table, function (Blueprint $table) {
            $table->dropColumn($this->column);
        });

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Information
    |--------------------------------------------------------------------------
    */

    public function exists(): bool
    {
        return Schema::hasColumn(
            $this->table,
            $this->column
        );
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getType(): string
    {
        return $this->type;
    }
}