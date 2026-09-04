<?php

namespace SchoolPalm\ModuleBridge\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignKeyDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SchemaBuilder
{
    protected string $table;
    protected ?string $currentModulePrefix = null;
    protected ?ForeignKeyDefinition $foreignDefinition = null;

    protected ?array $currentForeignKey = null;
    /**
     * Active Laravel Blueprint during table creation.
     */
    protected ?Blueprint $blueprint = null;

    public function __construct(
        string $table,
        ?Blueprint $blueprint = null
    ) {
        $this->table = $table;
        $this->blueprint = $blueprint;

        $pieces = explode('_', $this->table);

        if (count($pieces) >= 3) {
            $this->currentModulePrefix =
                $pieces[0] . '_' .
                $pieces[1] . '_' .
                $pieces[2];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Creation Mode
    |--------------------------------------------------------------------------
    */

    protected function baseTableName(string $table): string
    {
        /*
     * Already a fully-qualified module table.
     */
        if (
            $this->currentModulePrefix !== null &&
            str_starts_with(
                $table,
                $this->currentModulePrefix . '_'
            )
        ) {
            return $table;
        }

        /*
     * Base tables are module-scoped.
     *
     * Example:
     *   current table: schoolpalm_common_staff_staff
     *   requested:     contacts
     *   result:        schoolpalm_common_staff_contacts
     */
        if ($this->currentModulePrefix !== null) {
            return $this->currentModulePrefix . '_' . $table;
        }

        return $table;
    }
    public function isCreating(): bool
    {
        return $this->blueprint !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Column Types
    |--------------------------------------------------------------------------
    */

    public function id(
        string $column = 'id'
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'id'
        );
    }

    public function ulid(
        string $column = 'id'
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'ulid'
        );
    }

    public function string(
        string $column,
        int $length = 255
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'string',
            [$length]
        );
    }

    public function char(
        string $column,
        int $length = 255
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'char',
            [$length]
        );
    }

    public function text(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'text'
        );
    }

    public function tinyText(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'tinyText'
        );
    }

    public function mediumText(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'mediumText'
        );
    }

    public function longText(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'longText'
        );
    }

    public function tinyInteger(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'tinyInteger'
        );
    }

    public function smallInteger(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'smallInteger'
        );
    }

    public function mediumInteger(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'mediumInteger'
        );
    }

    public function integer(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'integer'
        );
    }

    public function bigInteger(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'bigInteger'
        );
    }

    public function unsignedTinyInteger(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'unsignedTinyInteger'
        );
    }

    public function unsignedSmallInteger(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'unsignedSmallInteger'
        );
    }

    public function unsignedMediumInteger(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'unsignedMediumInteger'
        );
    }

    public function unsignedInteger(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'unsignedInteger'
        );
    }

    public function unsignedBigInteger(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'unsignedBigInteger'
        );
    }

    public function boolean(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'boolean'
        );
    }

    public function float(
        string $column,
        int $precision = 8,
        int $scale = 2
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'float',
            [$precision, $scale]
        );
    }

    public function double(
        string $column,
        int $precision = 8,
        int $scale = 2
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'double',
            [$precision, $scale]
        );
    }

    public function decimal(
        string $column,
        int $precision = 8,
        int $scale = 2
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'decimal',
            [$precision, $scale]
        );
    }

    public function unsignedDecimal(
        string $column,
        int $precision = 8,
        int $scale = 2
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'unsignedDecimal',
            [$precision, $scale]
        );
    }

    public function json(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'json'
        );
    }

    public function jsonb(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'jsonb'
        );
    }

    public function date(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'date'
        );
    }

    public function dateTime(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'dateTime'
        );
    }

    public function dateTimeTz(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'dateTimeTz'
        );
    }

    public function time(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'time'
        );
    }

    public function timeTz(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'timeTz'
        );
    }

    public function timestamp(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'timestamp'
        );
    }

    public function timestampTz(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'timestampTz'
        );
    }

    public function year(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'year'
        );
    }

    public function binary(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'binary'
        );
    }

    public function enum(
        string $column,
        array $allowed
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'enum',
            [$allowed]
        );
    }

    public function set(
        string $column,
        array $allowed
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'set',
            [$allowed]
        );
    }

    public function ipAddress(
        string $column = 'ip_address'
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'ipAddress'
        );
    }

    public function macAddress(
        string $column = 'mac_address'
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'macAddress'
        );
    }

    public function geometry(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'geometry'
        );
    }

    public function point(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'point'
        );
    }

    public function uuid(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'uuid'
        );
    }
    public function foreignId(
        string $column
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'ulid'
        );
    }

    public function foreignIdFor(
        string $model,
        ?string $column = null
    ): ColumnDefinition {
        $column ??= strtolower(
            class_basename($model)
        ) . '_id';

        return $this->addColumn(
            $column,
            'ulid'
        );
    }

    public function softDeletes(
        string $column = 'deleted_at'
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'softDeletes'
        );
    }

    public function softDeletesTz(
        string $column = 'deleted_at'
    ): ColumnDefinition {
        return $this->addColumn(
            $column,
            'softDeletesTz'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Column Updates
    |--------------------------------------------------------------------------
    */

    public function renameColumn(
        string $from,
        string $to
    ): self {
        if (
            !$this->isCreating() &&
            Schema::hasColumn($this->table, $from) &&
            !Schema::hasColumn($this->table, $to)
        ) {
            Schema::table(
                $this->table,
                function (Blueprint $table) use (
                    $from,
                    $to
                ) {
                    $table->renameColumn(
                        $from,
                        $to
                    );
                }
            );
        }

        return $this;
    }

    public function changeColumn(
        string $column,
        string $type,
        array $params = []
    ): self {
        if (
            $this->isCreating() ||
            !Schema::hasColumn(
                $this->table,
                $column
            )
        ) {
            return $this;
        }

        Schema::table(
            $this->table,
            function (Blueprint $table) use (
                $column,
                $type,
                $params
            ) {
                $table->$type(
                    $column,
                    ...$params
                )->change();
            }
        );

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Column Removal
    |--------------------------------------------------------------------------
    */

    public function dropColumn(string $column): self
    {
        if (!$this->isCreating()) {
            $foreignKey = $this->getForeignKeyForColumn($column);

            if ($foreignKey) {
                Schema::table(
                    $this->table,
                    function (Blueprint $table) use ($foreignKey) {
                        $table->dropForeign($foreignKey);
                    }
                );
            }
        }

        if (Schema::hasColumn($this->table, $column)) {
            Schema::table(
                $this->table,
                function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                }
            );
        }

        return $this;
    }

    protected function getForeignKeyForColumn(string $column): ?string
    {
        $database = DB::connection()->getDatabaseName();

        $result = DB::selectOne(
            "
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE CONSTRAINT_SCHEMA = ?
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
          AND REFERENCED_TABLE_NAME IS NOT NULL
        LIMIT 1
        ",
            [
                $database,
                $this->table,
                $column,
            ]
        );

        return $result?->CONSTRAINT_NAME;
    }

    public function dropColumns(
        array $columns
    ): self {
        if ($this->isCreating()) {
            return $this;
        }

        $existing = [];

        foreach ($columns as $column) {
            if (
                Schema::hasColumn(
                    $this->table,
                    $column
                )
            ) {
                $existing[] = $column;
            }
        }

        if ($existing) {
            Schema::table(
                $this->table,
                function (Blueprint $table) use (
                    $existing
                ) {
                    $table->dropColumn(
                        $existing
                    );
                }
            );
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Indexes
    |--------------------------------------------------------------------------
    */

    public function index(
        string|array $columns,
        ?string $name = null
    ): self {
        $columns = is_array($columns)
            ? array_values($columns)
            : [$columns];

        foreach ($columns as $column) {
            if (
                !$this->blueprint &&
                !Schema::hasColumn($this->table, $column)
            ) {
                return $this;
            }
        }

        $name ??= $this->safeIdentifierName(
            'ix',
            $columns
        );

        /*
     * CREATE MODE
     *
     * The table does not exist yet. Add the index directly
     * to the active Blueprint using our explicit name.
     */
        if ($this->blueprint) {
            $this->blueprint->index(
                $columns,
                $name
            );

            return $this;
        }

        /*
     * EXISTING TABLE MODE
     */
        Schema::table(
            $this->table,
            function (Blueprint $table) use (
                $columns,
                $name
            ) {
                $table->index(
                    $columns,
                    $name
                );
            }
        );

        return $this;
    }
    public function unique(
        string|array $columns,
        ?string $name = null
    ): self {

        $columns = is_array($columns)
            ? array_values($columns)
            : [$columns];

        /*
         * Every module unique constraint is tenant scoped.
         */
        if (
            !in_array(
                'school_id',
                $columns,
                true
            ) &&
            (
                $this->isCreating() ||
                Schema::hasColumn(
                    $this->table,
                    'school_id'
                )
            )
        ) {
            array_unshift(
                $columns,
                'school_id'
            );
        }

        if (!$this->isCreating()) {
            foreach ($columns as $column) {
                if (
                    !Schema::hasColumn(
                        $this->table,
                        $column
                    )
                ) {
                    return $this;
                }
            }
        }

        $name ??= $this->safeIdentifierName(
            'uniq',
            $columns
        );

        if ($this->isCreating()) {
            $this->blueprint->unique(
                $columns,
                $name
            );

            return $this;
        }

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

    public function globalIndex(
        string|array $columns,
        ?string $name = null
    ): self {
        $columns = is_array($columns)
            ? array_values($columns)
            : [$columns];

        $name ??= $this->safeIdentifierName(
            'idx',
            $columns
        );

        if ($this->isCreating()) {
            $this->blueprint->index(
                $columns,
                $name
            );

            return $this;
        }

        Schema::table(
            $this->table,
            function (Blueprint $table) use (
                $columns,
                $name
            ) {
                $table->index(
                    $columns,
                    $name
                );
            }
        );

        return $this;
    }

    public function globalUnique(
        string|array $columns,
        ?string $name = null
    ): self {
        $columns = is_array($columns)
            ? array_values($columns)
            : [$columns];

        $name ??= $this->safeIdentifierName(
            'uniq',
            $columns
        );

        if ($this->isCreating()) {
            $this->blueprint->unique(
                $columns,
                $name
            );

            return $this;
        }

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

    public function fullText(
        string|array $columns,
        ?string $name = null
    ): self {
        $columns = is_array($columns)
            ? array_values($columns)
            : [$columns];

        $name ??= $this->safeIdentifierName(
            'ft',
            $columns
        );

        if ($this->isCreating()) {
            $this->blueprint->fullText(
                $columns,
                $name
            );

            return $this;
        }

        Schema::table(
            $this->table,
            function (Blueprint $table) use (
                $columns,
                $name
            ) {
                $table->fullText(
                    $columns,
                    $name
                );
            }
        );

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
        /*
     * Resolve the logical table name to the actual
     * module-scoped database table.
     */
        $onTable = $this->baseTableName($onTable);

        $name = $this->foreignName(
            $column,
            $onTable,
            $references
        );

        $this->currentForeignKey = [
            'column' => $column,
            'references' => $references,
            'onTable' => $onTable,
            'name' => $name,
        ];

        /*
     * CREATE MODE
     */
        if ($this->isCreating()) {
            $this->foreignDefinition = $this->blueprint
                ->foreign(
                    $column,
                    $name
                )
                ->references($references)
                ->on($onTable);

            return $this;
        }

        /*
     * UPDATE MODE
     */
        if ($this->foreignExists(
            $column,
            $references,
            $onTable
        )) {
            return $this;
        }

        Schema::table(
            $this->table,
            function (Blueprint $table) use (
                $column,
                $references,
                $onTable,
                $name
            ) {
                $table
                    ->foreign(
                        $column,
                        $name
                    )
                    ->references($references)
                    ->on($onTable);
            }
        );

        return $this;
    }

    public function foreignName(
        string $column,
        string $onTable,
        string $references = 'id'
    ): string {
        return $this->safeIdentifierName(
            'fk',
            $column,
            $onTable,
            $references
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Timestamps
    |--------------------------------------------------------------------------
    */

    public function timestamps(): self
    {
        if ($this->isCreating()) {
            $this->blueprint->timestamps();

            return $this;
        }

        if (
            !Schema::hasColumn(
                $this->table,
                'created_at'
            )
        ) {
            Schema::table(
                $this->table,
                function (Blueprint $table) {
                    $table->timestamps();
                }
            );
        }

        return $this;
    }

    public function timestampsTz(): self
    {
        if ($this->isCreating()) {
            $this->blueprint->timestampsTz();

            return $this;
        }

        if (
            !Schema::hasColumn(
                $this->table,
                'created_at'
            )
        ) {
            Schema::table(
                $this->table,
                function (Blueprint $table) {
                    $table->timestampsTz();
                }
            );
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Table Operations
    |--------------------------------------------------------------------------
    */

    public function renameTable(
        string $newName
    ): self {
        if (
            !$this->isCreating() &&
            Schema::hasTable($this->table) &&
            !Schema::hasTable($newName)
        ) {
            Schema::rename(
                $this->table,
                $newName
            );

            $this->table = $newName;
        }

        return $this;
    }

    public function truncate(): self
    {
        if (!$this->isCreating()) {
            DB::table(
                $this->table
            )->truncate();
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function hasColumn(
        string $column
    ): bool {
        return Schema::hasColumn(
            $this->table,
            $column
        );
    }

    public function hasTable(): bool
    {
        return Schema::hasTable(
            $this->table
        );
    }

    public function getColumnType(
        string $column
    ): ?string {
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
    | Safe Identifier Names
    |--------------------------------------------------------------------------
    */

    protected function safeIdentifierName(
        string $type,
        string|array $columns,
        ?string $relatedTable = null,
        ?string $relatedColumn = null
    ): string {
        $columns = is_array($columns)
            ? array_values($columns)
            : [$columns];

        $columnPart = implode('_', $columns);

        /*
     * Keep the hash tied to the table and complete column set.
     *
     * This makes the name:
     *
     * uq_96824e8b_form_name
     *
     * instead of:
     *
     * uniq_schoolpalm_common_student_forms_...
     */
        $hash = substr(
            md5(
                implode('|', [
                    $type,
                    $this->table,
                    $columnPart,
                    $relatedTable,
                    $relatedColumn,
                ])
            ),
            0,
            8
        );

        /*
     * Convert internal types to the public short prefixes.
     */
        $prefix = match ($type) {
            'uniq' => 'uq',
            'idx'  => 'ix',
            'fk'   => 'fk',
            'ft'   => 'ft',
            default => $type,
        };

        /*
     * Sanitize the column portion.
     */
        $columnPart = $this->sanitizeIdentifierPart(
            $columnPart
        );

        $name = "{$prefix}_{$hash}_{$columnPart}";

        /*
     * Final safety guard for MySQL/MariaDB's 64-character
     * identifier limit.
     */
        return substr($name, 0, 64);
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

    /*
    |--------------------------------------------------------------------------
    | Internal Column Creation
    |--------------------------------------------------------------------------
    */

    protected function addColumn(
        string $column,
        string $type,
        array $params = []
    ): ColumnDefinition {

        /*
         * CREATE MODE
         *
         * The table does not exist yet. Add the column directly
         * to the active Laravel Blueprint.
         */
        if ($this->isCreating()) {

            $this->blueprint->$type(
                $column,
                ...$params
            );

            return new ColumnDefinition(
                $this->table,
                $column,
                $type,
                $params,
                $this,
                $this->blueprint
            );
        }

        /*
         * UPDATE MODE
         *
         * The table already exists.
         */
        if (!Schema::hasColumn(
            $this->table,
            $column
        )) {
            Schema::table(
                $this->table,
                function (Blueprint $table) use (
                    $column,
                    $type,
                    $params
                ) {
                    $table->$type(
                        $column,
                        ...$params
                    );
                }
            );
        }

        return new ColumnDefinition(
            $this->table,
            $column,
            $type,
            $params,
            $this
        );
    }


    public function cascadeOnDelete(): self
    {
        if ($this->foreignDefinition) {
            $this->foreignDefinition->cascadeOnDelete();

            return $this;
        }

        return $this->replaceForeignKeyAction(
            'onDelete',
            'cascade'
        );
    }

    public function cascadeOnUpdate(): self
    {
        if ($this->foreignDefinition) {
            $this->foreignDefinition->cascadeOnUpdate();

            return $this;
        }

        return $this->replaceForeignKeyAction(
            'onUpdate',
            'cascade'
        );
    }

    public function nullOnDelete(): self
    {
        if ($this->foreignDefinition) {
            $this->foreignDefinition->nullOnDelete();

            return $this;
        }

        return $this->replaceForeignKeyAction(
            'onDelete',
            'set null'
        );
    }

    public function restrictOnDelete(): self
    {
        if ($this->foreignDefinition) {
            $this->foreignDefinition->restrictOnDelete();

            return $this;
        }

        return $this->replaceForeignKeyAction(
            'onDelete',
            'restrict'
        );
    }

    protected function replaceForeignKeyAction(
        string $action,
        string $value
    ): self {
        if (!$this->currentForeignKey) {
            return $this;
        }

        $column = $this->currentForeignKey['column'];
        $references = $this->currentForeignKey['references'];
        $onTable = $this->currentForeignKey['onTable'];

        /*
     * Find the REAL database constraint.
     */
        $existing = $this->getForeignKey(
            $column
        );

        /*
     * No FK exists yet.
     *
     * Create it with the requested action.
     */
        if (!$existing) {
            $name = $this->currentForeignKey['name'];

            Schema::table(
                $this->table,
                function (Blueprint $table) use (
                    $column,
                    $references,
                    $onTable,
                    $name,
                    $action,
                    $value
                ) {
                    $foreign = $table
                        ->foreign(
                            $column,
                            $name
                        )
                        ->references($references)
                        ->on($onTable);

                    if ($action === 'onDelete') {
                        $foreign->onDelete($value);
                    } elseif ($action === 'onUpdate') {
                        $foreign->onUpdate($value);
                    }
                }
            );

            return $this;
        }

        /*
     * Preserve the other existing action.
     */
        $onDelete = $existing['on_delete'] ?? null;
        $onUpdate = $existing['on_update'] ?? null;

        if ($action === 'onDelete') {
            $onDelete = $value;
        }

        if ($action === 'onUpdate') {
            $onUpdate = $value;
        }

        /*
     * Drop the ACTUAL constraint name.
     */
        Schema::table(
            $this->table,
            function (Blueprint $table) use ($existing) {
                $table->dropForeign(
                    $existing['constraint_name']
                );
            }
        );

        /*
     * Recreate it.
     */
        $name = $this->currentForeignKey['name'];

        Schema::table(
            $this->table,
            function (Blueprint $table) use (
                $column,
                $references,
                $onTable,
                $name,
                $onDelete,
                $onUpdate
            ) {
                $foreign = $table
                    ->foreign(
                        $column,
                        $name
                    )
                    ->references($references)
                    ->on($onTable);

                if ($onDelete !== null) {
                    $foreign->onDelete($onDelete);
                }

                if ($onUpdate !== null) {
                    $foreign->onUpdate($onUpdate);
                }
            }
        );

        return $this;
    }

    protected function foreignExists(
        string $column,
        string $references,
        string $onTable
    ): bool {
        $foreignKey = $this->getForeignKey($column);

        if (!$foreignKey) {
            return false;
        }

        return (
            ($foreignKey['column_name'] ?? null) === $column &&
            ($foreignKey['referenced_column'] ?? null) === $references &&
            ($foreignKey['referenced_table'] ?? null) === $onTable
        );
    }


    protected function getForeignKey(
        string $column
    ): ?array {
        $database = DB::getDatabaseName();

        $result = DB::selectOne(
            '
        SELECT
            kcu.CONSTRAINT_NAME AS constraint_name,
            kcu.COLUMN_NAME AS column_name,
            kcu.REFERENCED_TABLE_NAME AS referenced_table,
            kcu.REFERENCED_COLUMN_NAME AS referenced_column,
            rc.DELETE_RULE AS on_delete,
            rc.UPDATE_RULE AS on_update
        FROM information_schema.KEY_COLUMN_USAGE kcu
        LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
            ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
            AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
            AND rc.TABLE_NAME = kcu.TABLE_NAME
        WHERE kcu.CONSTRAINT_SCHEMA = ?
            AND kcu.TABLE_NAME = ?
            AND kcu.COLUMN_NAME = ?
            AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        LIMIT 1
        ',
            [
                $database,
                $this->table,
                $column,
            ]
        );

        if (!$result) {
            return null;
        }

        return [
            'constraint_name' => $result->constraint_name,
            'column_name' => $result->column_name,
            'referenced_table' => $result->referenced_table,
            'referenced_column' => $result->referenced_column,
            'on_delete' => $result->on_delete,
            'on_update' => $result->on_update,
        ];
    }
}
