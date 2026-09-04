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

    protected ?SchemaBuilder $builder = null;

    protected ?string $foreignTable = null;

    protected string $foreignColumn = 'id';

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct(
        string $table,
        string $column,
        string $type,
        array $params = [],
        ?SchemaBuilder $builder = null
    ) {
        $this->table = $table;
        $this->column = $column;
        $this->type = $type;
        $this->params = $params;
        $this->builder = $builder;
    }

    /*
    |--------------------------------------------------------------------------
    | Builder
    |--------------------------------------------------------------------------
    */

    public function setBuilder(
        SchemaBuilder $builder
    ): static {
        $this->builder = $builder;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Internal Modifier Handler
    |--------------------------------------------------------------------------
    */

    protected function modify(
        callable $callback
    ): static {

        Schema::table(
            $this->table,
            function (Blueprint $table) use ($callback) {

                $column = $table->{$this->type}(
                    $this->column,
                    ...$this->params
                );

                $callback($column);

                $column->change();
            }
        );

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Change
    |--------------------------------------------------------------------------
    */

    public function change(): static
    {
        Schema::table(
            $this->table,
            function (Blueprint $table) {

                $column = $table->{$this->type}(
                    $this->column,
                    ...$this->params
                );

                $column->change();
            }
        );

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Column Modifiers
    |--------------------------------------------------------------------------
    */

    public function nullable(
        bool $value = true
    ): static {
        return $this->modify(
            function ($column) use ($value) {
                $column->nullable($value);
            }
        );
    }

    public function default(
        mixed $value
    ): static {
        return $this->modify(
            function ($column) use ($value) {
                $column->default($value);
            }
        );
    }

    public function comment(
        string $comment
    ): static {
        return $this->modify(
            function ($column) use ($comment) {
                $column->comment($comment);
            }
        );
    }

    public function after(
        string $column
    ): static {
        return $this->modify(
            function ($col) use ($column) {
                $col->after($column);
            }
        );
    }

    public function first(): static
    {
        return $this->modify(
            function ($column) {
                $column->first();
            }
        );
    }

    public function unsigned(): static
    {
        return $this->modify(
            function ($column) {
                $column->unsigned();
            }
        );
    }

    public function charset(
        string $charset
    ): static {
        return $this->modify(
            function ($column) use ($charset) {
                $column->charset($charset);
            }
        );
    }

    public function collation(
        string $collation
    ): static {
        return $this->modify(
            function ($column) use ($collation) {
                $column->collation($collation);
            }
        );
    }

    public function invisible(): static
    {
        return $this->modify(
            function ($column) {
                $column->invisible();
            }
        );
    }

    public function storedAs(
        string $expression
    ): static {
        return $this->modify(
            function ($column) use ($expression) {
                $column->storedAs($expression);
            }
        );
    }

    public function virtualAs(
        string $expression
    ): static {
        return $this->modify(
            function ($column) use ($expression) {
                $column->virtualAs($expression);
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Indexes
    |--------------------------------------------------------------------------
    */

    /**
     * Create a normal index.
     *
     * The index name is generated by SchemaBuilder and is guaranteed
     * to respect MySQL/MariaDB identifier limits.
     */
    public function index(
        ?string $name = null
    ): static {

        if ($this->builder) {

            $this->builder->index(
                $this->column,
                $name
            );

            return $this;
        }

        /*
         * Fallback for backwards compatibility.
         */
        $name ??= $this->safeIdentifierName(
            'idx',
            $this->column
        );

        Schema::table(
            $this->table,
            function (Blueprint $table) use ($name) {
                $table->index(
                    $this->column,
                    $name
                );
            }
        );

        return $this;
    }

    /**
     * Create a tenant-scoped unique constraint.
     *
     * Example:
     *
     *     $table->string('student_reference')->unique();
     *
     * becomes:
     *
     *     UNIQUE (school_id, student_reference)
     */
    public function unique(
        ?string $name = null
    ): static {

        if ($this->builder) {

            $this->builder->unique(
                $this->column,
                $name
            );

            return $this;
        }

        /*
         * Fallback for backwards compatibility.
         */
        $columns = [$this->column];

        if (
            Schema::hasColumn(
                $this->table,
                'school_id'
            ) &&
            !in_array(
                'school_id',
                $columns,
                true
            )
        ) {
            array_unshift(
                $columns,
                'school_id'
            );
        }

        $name ??= $this->safeIdentifierName(
            'uniq',
            $columns
        );

        Schema::table(
            $this->table,
            function (Blueprint $table) use (
                $columns,
                $name
            ) {
                $table->unique(
                    $columns,
                    $name
                );
            }
        );

        return $this;
    }

    /**
     * Create a globally unique constraint.
     *
     * Unlike unique(), this does not include school_id.
     */
    public function globalUnique(
        ?string $name = null
    ): static {

        if ($this->builder) {

            $this->builder->globalUnique(
                $this->column,
                $name
            );

            return $this;
        }

        $name ??= $this->safeIdentifierName(
            'uniq',
            $this->column
        );

        Schema::table(
            $this->table,
            function (Blueprint $table) use ($name) {
                $table->unique(
                    $this->column,
                    $name
                );
            }
        );

        return $this;
    }

    public function fullText(
        ?string $name = null
    ): static {

        if ($this->builder) {

            $this->builder->fullText(
                $this->column,
                $name
            );

            return $this;
        }

        $name ??= $this->safeIdentifierName(
            'ft',
            $this->column
        );

        Schema::table(
            $this->table,
            function (Blueprint $table) use ($name) {
                $table->fullText(
                    $this->column,
                    $name
                );
            }
        );

        return $this;
    }

    public function spatialIndex(
        ?string $name = null
    ): static {

        $name ??= $this->safeIdentifierName(
            'sp',
            $this->column
        );

        Schema::table(
            $this->table,
            function (Blueprint $table) use ($name) {
                $table->spatialIndex(
                    $this->column,
                    $name
                );
            }
        );

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Drop Indexes
    |--------------------------------------------------------------------------
    */

    public function dropIndex(): static
    {
        if ($this->builder) {

            $this->builder->dropIndex(
                $this->column
            );

            return $this;
        }

        $name = $this->safeIdentifierName(
            'idx',
            $this->column
        );

        Schema::table(
            $this->table,
            function (Blueprint $table) use ($name) {
                $table->dropIndex($name);
            }
        );

        return $this;
    }

    /**
     * Drop a tenant-scoped unique constraint.
     */
    public function dropUnique(): static
    {
        if ($this->builder) {

            $this->builder->dropUnique(
                $this->column
            );

            return $this;
        }

        $columns = [$this->column];

        if (
            Schema::hasColumn(
                $this->table,
                'school_id'
            )
        ) {
            array_unshift(
                $columns,
                'school_id'
            );
        }

        $name = $this->safeIdentifierName(
            'uniq',
            $columns
        );

        Schema::table(
            $this->table,
            function (Blueprint $table) use ($name) {
                $table->dropUnique($name);
            }
        );

        return $this;
    }

    /**
     * Drop a globally unique constraint.
     */
    public function dropGlobalUnique(): static
    {
        if ($this->builder) {

            $this->builder->dropGlobalUnique(
                $this->column
            );

            return $this;
        }

        $name = $this->safeIdentifierName(
            'uniq',
            $this->column
        );

        Schema::table(
            $this->table,
            function (Blueprint $table) use ($name) {
                $table->dropUnique($name);
            }
        );

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
        $this->foreignTable = $table;
        $this->foreignColumn = $column;

        if ($this->builder) {

            $this->builder->foreign(
                $this->column,
                $column,
                $table
            );

            return $this;
        }

        $name = $this->safeIdentifierName(
            'fk',
            $this->column,
            $table,
            $column
        );

        Schema::table(
            $this->table,
            function (Blueprint $tbl) use (
                $table,
                $column,
                $name
            ) {

                $tbl->foreign(
                    $this->column,
                    $name
                )
                    ->references($column)
                    ->on($table);
            }
        );

        return $this;
    }

    public function cascadeOnDelete(
        ?string $table = null,
        ?string $column = null
    ): static {
        $targetTable = $table ?? $this->foreignTable;
        $targetColumn = $column ?? $this->foreignColumn;

        if (!$targetTable) {
            throw new \InvalidArgumentException(
                "Cannot apply cascadeOnDelete() on '{$this->column}': foreign table is unknown. Call constrained() first or pass \$table explicitly."
            );
        }

        return $this->replaceForeignKey(
            $targetTable,
            $targetColumn,
            function ($foreign) {
                $foreign->cascadeOnDelete();
            }
        );
    }

    public function cascadeOnUpdate(
        ?string $table = null,
        ?string $column = null
    ): static {
        $targetTable = $table ?? $this->foreignTable;
        $targetColumn = $column ?? $this->foreignColumn;

        if (!$targetTable) {
            throw new \InvalidArgumentException(
                "Cannot apply cascadeOnUpdate() on '{$this->column}': foreign table is unknown. Call constrained() first or pass \$table explicitly."
            );
        }

        return $this->replaceForeignKey(
            $targetTable,
            $targetColumn,
            function ($foreign) {
                $foreign->cascadeOnUpdate();
            }
        );
    }

    public function nullOnDelete(
        ?string $table = null,
        ?string $column = null
    ): static {
        $targetTable = $table ?? $this->foreignTable;
        $targetColumn = $column ?? $this->foreignColumn;

        if (!$targetTable) {
            throw new \InvalidArgumentException(
                "Cannot apply nullOnDelete() on '{$this->column}': foreign table is unknown. Call constrained() first or pass \$table explicitly."
            );
        }

        return $this->replaceForeignKey(
            $targetTable,
            $targetColumn,
            function ($foreign) {
                $foreign->nullOnDelete();
            }
        );
    }

    public function restrictOnDelete(
        ?string $table = null,
        ?string $column = null
    ): static {
        $targetTable = $table ?? $this->foreignTable;
        $targetColumn = $column ?? $this->foreignColumn;

        if (!$targetTable) {
            throw new \InvalidArgumentException(
                "Cannot apply restrictOnDelete() on '{$this->column}': foreign table is unknown. Call constrained() first or pass \$table explicitly."
            );
        }

        return $this->replaceForeignKey(
            $targetTable,
            $targetColumn,
            function ($foreign) {
                $foreign->restrictOnDelete();
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Foreign Key Replacement
    |--------------------------------------------------------------------------
    */

    protected function replaceForeignKey(
        string $table,
        string $column,
        callable $modifier
    ): static {

        $name = $this->builder
            ? $this->builder->foreignName(
                $this->column,
                $table,
                $column
            )
            : $this->safeIdentifierName(
                'fk',
                $this->column,
                $table,
                $column
            );

        Schema::table(
            $this->table,
            function (Blueprint $tbl) use (
                $table,
                $column,
                $modifier,
                $name
            ) {

                /*
                 * Remove the existing FK using the same deterministic
                 * name before recreating it.
                 */
                $tbl->dropForeign($name);

                $foreign = $tbl->foreign(
                    $this->column,
                    $name
                )
                    ->references($column)
                    ->on($table);

                $modifier($foreign);
            }
        );

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Column Removal
    |--------------------------------------------------------------------------
    */

    public function drop(): bool
    {
        if (
            !Schema::hasColumn(
                $this->table,
                $this->column
            )
        ) {
            return false;
        }

        Schema::table(
            $this->table,
            function (Blueprint $table) {
                $table->dropColumn(
                    $this->column
                );
            }
        );

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

    /*
    |--------------------------------------------------------------------------
    | Backwards-Compatible Safe Identifier Generator
    |--------------------------------------------------------------------------
    */

    protected function safeIdentifierName(
        string $type,
        string|array $column,
        ?string $relatedTable = null,
        ?string $relatedColumn = null
    ): string {

        $columnValue = is_array($column)
            ? implode('_', $column)
            : $column;

        $tablePart = $this->sanitizeIdentifierPart(
            $this->table
        );

        $columnPart = $this->sanitizeIdentifierPart(
            $columnValue
        );

        $relatedTablePart = $relatedTable !== null
            ? $this->sanitizeIdentifierPart(
                $relatedTable
            )
            : null;

        $relatedColumnPart = $relatedColumn !== null
            ? $this->sanitizeIdentifierPart(
                $relatedColumn
            )
            : null;

        $hash = substr(
            md5(
                implode('|', [
                    $type,
                    $this->table,
                    $columnValue,
                    $relatedTable,
                    $relatedColumn,
                ])
            ),
            0,
            10
        );

        $parts = [
            $type,
            $tablePart,
            $columnPart,
        ];

        if ($relatedTablePart !== null) {
            $parts[] = $relatedTablePart;
        }

        if ($relatedColumnPart !== null) {
            $parts[] = $relatedColumnPart;
        }

        $parts[] = $hash;

        $name = implode(
            '_',
            array_filter(
                $parts,
                fn($part) => $part !== ''
            )
        );

        return strlen($name) <= 64
            ? $name
            : substr($name, 0, 64);
    }

    protected function sanitizeIdentifierPart(
        string $value
    ): string {
        $value = preg_replace(
            '/[^a-zA-Z0-9_]/',
            '_',
            $value
        ) ?? '';

        $value = preg_replace(
            '/_+/',
            '_',
            $value
        ) ?? '';

        return trim(
            $value,
            '_'
        );
    }
}
