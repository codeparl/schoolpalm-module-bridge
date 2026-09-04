<?php

namespace SchoolPalm\ModuleBridge\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection as DoctrineConnection;
use SchoolPalm\ModuleBridge\Support\Helper;
use SchoolPalm\ModuleBridge\Manifest\ManifestFactory;

class SchemaExporter
{
    protected const BASE_MIGRATION_COLUMNS = [
        'id',
        'school_id',
        'created_at',
        'updated_at',
    ];
    /**
     * Get a Doctrine DBAL connection using Laravel DB configurations.
     */
    protected static function getDoctrineConnection(): DoctrineConnection
    {
        $connection = DB::connection();
        $config = $connection->getConfig();

        $driverMap = [
            'mysql'   => 'pdo_mysql',
            'mariadb' => 'pdo_mysql',
            'pgsql'   => 'pdo_pgsql',
            'sqlite'  => 'pdo_sqlite',
            'sqlsrv'  => 'pdo_sqlsrv',
        ];

        $driver = $driverMap[$config['driver']] ?? 'pdo_mysql';

        return DriverManager::getConnection([
            'driver'   => $driver,
            'host'     => $config['host'] ?? '127.0.0.1',
            'port'     => $config['port'] ?? null,
            'dbname'   => $config['database'],
            'user'     => $config['username'],
            'password' => $config['password'],
            'charset'  => $config['charset'] ?? 'utf8mb4',
        ]);
    }

    /**
     * Export schema from database table.
     */
    public static function export(string $table, $migrationPath, string $outputPath): array
    {
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            return [];
        }

        $root = config('sdk.modules.root');
        $doctrine = self::getDoctrineConnection();
        $schemaManager = $doctrine->createSchemaManager();
        $tableDetails = $schemaManager->introspectTable($table);
        $primaryKeys = $tableDetails->getPrimaryKey()?->getColumns() ?? [];

        $metadata = [
            'table'        => $table,
            'file'         => $migrationPath,
            'columns'      => [],
            'indexes'      => [],
            'foreign_keys' => [],
            'timestamps'   => false,
        ];

        foreach ($tableDetails->getColumns() as $column) {
            $doctrineType = $column
                ->getType()
                ->getTypeRegistry()
                ->lookupName($column->getType());

            $name = $column->getName();

            if (in_array($name, ['created_at', 'updated_at'])) {
                $metadata['timestamps'] = true;
                continue;
            }

            $metadata['columns'][] = [
                'name'            => $name,
                'type'            => self::mapToLaravelType($doctrineType),
                'length'          => $column->getLength(),
                'precision'       => $column->getPrecision(),
                'scale'           => $column->getScale(),
                'nullable'        => !$column->getNotnull(),
                'default'         => $column->getDefault(),
                'autoincrement'   => $column->getAutoincrement(),
                'primary'         => in_array($name, $primaryKeys),
                'comment'         => $column->getComment(),
            ];
        }

        /*
 * Export table-level indexes.
 */
        foreach ($tableDetails->getIndexes() as $index) {
            if ($index->isPrimary()) {
                continue;
            }

            $metadata['indexes'][] = [
                'name'    => $index->getName(),
                'columns' => $index->getColumns(),
                'unique'  => $index->isUnique(),
            ];
        }

        /*
 * Apply only single-column indexes to column metadata.
 *
 * Composite indexes, including school_id + national_id,
 * remain in the table-level indexes array.
 */
        $metadata['columns'] = self::applyIndexesToColumns(
            $metadata['columns'],
            $metadata['indexes']
        );





        foreach ($tableDetails->getForeignKeys() as $foreignKey) {
            $referencedTable =
                $foreignKey->getForeignTableName();

            $referencedTable = self::normalizeReferencedTable(
                $table,
                $referencedTable
            );

            $metadata['foreign_keys'][] = [
                'name'       => $foreignKey->getName(),
                'columns'    => $foreignKey->getLocalColumns(),
                'references' => $foreignKey->getForeignColumns(),
                'on'         => $referencedTable,
                'on_delete'  => $foreignKey->onDelete(),
                'on_update'  => $foreignKey->onUpdate(),
            ];
        }



        /*
 * Restore foreign-key relationship state onto the
 * corresponding column definitions.
 */
        $metadata['columns'] = self::applyForeignKeysToColumns(
            $metadata['columns'],
            $metadata['foreign_keys']
        );
        /*
 * Restore SchoolPalm-only relationship metadata.
 *
 * relationType is not stored in the database, so it must be
 * recovered from the previously exported schema.
 */
        $fullPath = rtrim($root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . ltrim($outputPath, DIRECTORY_SEPARATOR);

        if (File::exists($fullPath)) {
            $existingSchema = Helper::loadJson($fullPath);

            $metadata['columns'] = self::restoreRelationMetadata(
                $metadata['columns'],
                $existingSchema['columns'] ?? []
            );
        }

        $fullPath = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($outputPath, DIRECTORY_SEPARATOR);
        File::ensureDirectoryExists(dirname($fullPath));
        Helper::storeJson($fullPath, $metadata);

        return Helper::loadJson($fullPath);
    }

    /**
     * Merge columns from manifest: combine 'columns' (original) and 'updated_columns' (new/modified).
     * Also filters out columns that are in 'deleted_columns'.
     */
    public static function mergeManifestColumns(array $tableData): array
    {
        $original = $tableData['columns'] ?? [];
        $updated  = $tableData['updated_columns'] ?? [];
        $deleted  = $tableData['deleted_columns'] ?? [];

        $deletedMap = array_fill_keys(array_column($deleted, 'name'), true);
        $originalMap = array_column($original, null, 'name');

        $merged = [];
        foreach ($original as $col) {
            if (!isset($deletedMap[$col['name']])) {
                $merged[] = $col;
            }
        }

        foreach ($updated as $upd) {
            $name = $upd['name'];
            if (isset($deletedMap[$name])) {
                continue;
            }

            if (isset($originalMap[$name])) {
                foreach ($merged as $idx => $col) {
                    if ($col['name'] === $name) {
                        $merged[$idx] = array_merge($col, $upd);
                        break;
                    }
                }
            } else {
                $merged[] = $upd;
            }
        }

        return $merged;
    }

    /**
     * Generate Laravel Schema Builder PHP code from column definitions.
     * Skips system columns (id, school_id, created_at, updated_at).
     */
    public static function generateColumnDefinitions(
        array $columns,
        string $table = ''
    ): string {
        $lines = [];

        $columns = array_values(
            array_filter(
                $columns,
                function ($column) {
                    $name = $column['name'] ?? null;

                    return !in_array(
                        $name,
                        self::BASE_MIGRATION_COLUMNS,
                        true
                    );
                }
            )
        );

        foreach ($columns as $column) {
            $lines[] = self::generateFluentColumnDefinition(
                $column,
                '$table'
            ) . ';';
        }

        $foreignKeys = self::extractForeignKeysFromColumns($columns);

        $foreignKeysCode = self::generateForeignKeyDefinitions(
            $foreignKeys,
            '$table'
        );

        if ($foreignKeysCode !== '') {
            $lines[] = $foreignKeysCode;
        }

        return implode("\n", $lines);
    }


    protected static function normalizeReferencedTable(
        string $tableName,
        string $referencedTable
    ): string {
        $parts = explode('_', $tableName);

        array_pop($parts);

        $prefix = implode('_', $parts);

        if (
            $prefix !== '' &&
            str_starts_with(
                $referencedTable,
                $prefix . '_'
            )
        ) {
            return substr(
                $referencedTable,
                strlen($prefix) + 1
            );
        }

        return $referencedTable;
    }
    /**
     * Generate code for updating columns using standard Laravel Schema Builder syntax with ->change().
     */
    public static function generateUpdateColumnDefinitions(array $originalColumns, array $updatedColumns): string
    {
        if (empty($updatedColumns)) {
            return '';
        }

        $lines = [];

        foreach ($updatedColumns as $upd) {
            $changeType = $upd['change_type'] ?? self::detectChangeType($originalColumns, $upd);
            $name = $upd['name'];

            switch ($changeType) {
                case 'rename':
                    $previousName = $upd['previous_name'] ?? null;
                    if ($previousName && $previousName !== $name) {
                        $lines[] = "        \$this->schema->renameColumn('{$previousName}', '{$name}');";
                    }
                    break;

                case 'type':
                case 'modification':
                    $def = self::generateFluentColumnDefinition($upd, '$this->schema') . '->change()';
                    $lines[] = "        {$def};";
                    break;

                case 'addition':
                default:
                    $def = self::generateFluentColumnDefinition($upd, '$this->schema');
                    $lines[] = "        {$def};";
                    break;
            }
        }

        $foreignKeys = self::extractForeignKeysFromColumns($updatedColumns);

        $foreignCode = self::generateForeignKeyDefinitions(
            $foreignKeys,
            '$this->schema'
        );

        if ($foreignCode !== '') {
            $lines[] = $foreignCode;
        }

        return implode("\n", $lines);
    }

    /**
     * Generate standalone PHP code for indexes based on supplied index definitions.
     */
    public static function generateIndexDefinitions(array $indexes, string $variable = '$table'): string
    {
        if (empty($indexes)) {
            return '';
        }

        $lines = [];
        foreach ($indexes as $index) {
            $cols = $index['columns'] ?? [];
            if (empty($cols)) {
                continue;
            }

            $colsFormatted = count($cols) === 1
                ? "'{$cols[0]}'"
                : "['" . implode("', '", $cols) . "']";

            $indexName = $index['name'] ?? null;
            $type = ($index['unique'] ?? false) ? 'unique' : 'index';

            if ($indexName) {
                $lines[] = "            {$variable}->{$type}({$colsFormatted}, '{$indexName}');";
            } else {
                $lines[] = "            {$variable}->{$type}({$colsFormatted});";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Generate foreign-key definitions using SchemaBuilder syntax.
     *
     * Foreign keys are generated separately from column definitions because
     * Doctrine exposes them as table-level constraints.
     *
     * Creation:
     *
     *     $table->foreign('student_id')
     *         ->references('id')
     *         ->on('students')
     *         ->cascadeOnDelete();
     *
     * Update:
     *
     *     $this->schema->foreign('student_id', 'id', 'students')
     *         ->cascadeOnDelete();
     */



    protected static function extractForeignKeysFromColumns(array $columns): array
    {
        $foreignKeys = [];

        foreach ($columns as $column) {
            $referenceTable = $column['referenceTable'] ?? null;

            if (!$referenceTable) {
                continue;
            }

            $name = $column['name'] ?? null;

            if (!$name) {
                continue;
            }

            $foreignKeys[] = [
                'name' => $column['foreignKeyName'] ?? null,
                'columns' => [$name],
                'references' => [
                    $column['referenceColumn'] ?? 'id',
                ],
                'on' => $referenceTable,
                'on_delete' => $column['onDelete'] ?? null,
                'on_update' => $column['onUpdate'] ?? null,
                'relationType' => $column['relationType'] ?? null,
            ];
        }

        return $foreignKeys;
    }


    public static function generateForeignKeyDefinitions(
        array $foreignKeys,
        string $variable = '$table'
    ): string {
        if (empty($foreignKeys)) {
            return '';
        }

        $lines = [];

        foreach ($foreignKeys as $foreignKey) {
            $columns = $foreignKey['columns'] ?? [];
            $references = $foreignKey['references'] ?? [];
            $onTable = $foreignKey['on'] ?? null;

            if (count($columns) !== 1 || !$onTable) {
                continue;
            }

            $column = $columns[0];
            $referenceColumn = $references[0] ?? 'id';

            $line = "{$variable}->foreign("
                . "'" . addslashes($column) . "', "
                . "'" . addslashes($referenceColumn) . "', "
                . "'" . addslashes($onTable) . "'"
                . ")";

            $onDelete = strtolower(trim((string) ($foreignKey['on_delete'] ?? '')));
            $onUpdate = strtolower(trim((string) ($foreignKey['on_update'] ?? '')));

            if ($onDelete === 'cascade') {
                $line .= "\n    ->cascadeOnDelete()";
            } elseif ($onDelete === 'set null' || $onDelete === 'set_null') {
                $line .= "\n    ->nullOnDelete()";
            } elseif ($onDelete === 'restrict') {
                $line .= "\n    ->restrictOnDelete()";
            }

            if ($onUpdate === 'cascade') {
                $line .= "\n    ->cascadeOnUpdate()";
            }

            $line .= ';';

            $lines[] = $line;
        }

        return implode("\n\n", $lines);
    }
    /**
     * Apply database index metadata to column definitions.
     *
     * This preserves ->unique() and ->index() when a schema is
     * introspected from the database.
     *
     * Composite indexes are intentionally NOT pushed onto individual
     * columns because they must remain table-level indexes.
     */
    protected static function applyIndexesToColumns(
        array $columns,
        array $indexes
    ): array {
        foreach ($indexes as $index) {
            $indexColumns = $index['columns'] ?? [];

            if (empty($indexColumns)) {
                continue;
            }

            /*
         * Unique index.
         *
         * A unique index on:
         *
         *     national_id
         *
         * means national_id itself is unique.
         *
         * A unique index on:
         *
         *     school_id, national_id
         *
         * is interpreted by SchoolPalm as:
         *
         *     national_id is unique within a school.
         *
         * Therefore the designer should see:
         *
         *     unique: true
         *
         * on national_id.
         */
            if ($index['unique'] ?? false) {
                $isSchoolScoped = in_array(
                    'school_id',
                    $indexColumns,
                    true
                );

                foreach ($indexColumns as $columnName) {
                    /*
                 * school_id is the scope column, not the
                 * user-facing unique field.
                 */
                    if ($isSchoolScoped && $columnName === 'school_id') {
                        continue;
                    }

                    foreach ($columns as &$column) {
                        if ($column['name'] !== $columnName) {
                            continue;
                        }

                        $column['unique'] = true;

                        break;
                    }

                    unset($column);
                }

                continue;
            }

            /*
         * Normal non-unique indexes.
         *
         * Only single-column indexes can safely be represented
         * as indexed: true on the column.
         */
            if (count($indexColumns) === 1) {
                $columnName = $indexColumns[0];

                foreach ($columns as &$column) {
                    if ($column['name'] !== $columnName) {
                        continue;
                    }

                    $column['indexed'] = true;

                    break;
                }

                unset($column);
            }
        }

        return $columns;
    }
    /**
     * Detect change type from original columns (fallback when change_type not provided).
     */
    protected static function detectChangeType(array $originalColumns, array $updatedColumn): string
    {
        $original = null;
        foreach ($originalColumns as $col) {
            if ($col['name'] === $updatedColumn['name'] || ($updatedColumn['previous_name'] ?? null) === $col['name']) {
                $original = $col;
                break;
            }
        }

        if (!$original) {
            return 'addition';
        }

        if (($updatedColumn['previous_name'] ?? null) && $updatedColumn['previous_name'] !== $updatedColumn['name']) {
            return 'rename';
        }

        if ($original['type'] !== $updatedColumn['type']) {
            return 'type';
        }

        $keysToCompare = [
            'nullable',
            'unique',
            'indexed',
            'default',
            'length',
            'precision',
            'scale',
            'referenceTable',
            'referenceColumn',
            'relationType',
            'onDelete',
            'onUpdate',
            'comment',
        ];
        foreach ($keysToCompare as $key) {
            if (($original[$key] ?? null) != ($updatedColumn[$key] ?? null)) {
                return 'modification';
            }
        }

        return 'addition';
    }

    protected static function restoreRelationMetadata(
        array $columns,
        array $existingColumns
    ): array {
        if (empty($columns) || empty($existingColumns)) {
            return $columns;
        }

        /*
     * Index existing columns by name for efficient lookup.
     */
        $existingByName = [];

        foreach ($existingColumns as $existingColumn) {
            $name = $existingColumn['name'] ?? null;

            if ($name) {
                $existingByName[$name] = $existingColumn;
            }
        }

        foreach ($columns as $index => $column) {
            $name = $column['name'] ?? null;

            if (!$name || !isset($existingByName[$name])) {
                continue;
            }

            $existingColumn = $existingByName[$name];

            /*
         * relationType is SchoolPalm-only metadata.
         *
         * It does not exist in the physical database, so when the
         * schema is regenerated from the database we restore it from
         * the previous schema JSON.
         */
            if (
                array_key_exists('relationType', $existingColumn)
                && $existingColumn['relationType'] !== null
            ) {
                $columns[$index]['relationType'] =
                    $existingColumn['relationType'];
            }
        }

        return $columns;
    }
    /**
     * Generate a fluent column definition string using SchemaBuilder syntax.
     */
    protected static function generateFluentColumnDefinition(array $column, string $variable): string
    {
        $name = $column['name'];
        $type = $column['type'];
        $line = "{$variable}->{$type}('{$name}'";

        if ($type === 'string' && isset($column['length']) && $column['length'] !== null && $column['length'] != 255) {
            $line .= ", {$column['length']}";
        }

        if ($type === 'decimal' && isset($column['precision'], $column['scale'])) {
            $line .= ", {$column['precision']}, {$column['scale']}";
        }

        $line .= ')';

        if (
            ($column['nullable'] ?? false) ||
            strtolower(trim((string) ($column['onDelete'] ?? ''))) === 'set null'
        ) {
            $line .= '->nullable()';
        }

        if ($column['unique'] ?? false) {
            $line .= '->unique()';
        }

        if ($column['indexed'] ?? false) {
            $line .= '->index()';
        }

        if (array_key_exists('default', $column) && $column['default'] !== null && $column['default'] !== '') {
            if (in_array($type, ['string', 'text'])) {
                $line .= "->default('{$column['default']}')";
            } elseif ($type === 'boolean') {
                $line .= '->default(' . ($column['default'] ? 'true' : 'false') . ')';
            } else {
                $line .= "->default({$column['default']})";
            }
        }

        if (!empty($column['comment'])) {
            $line .= "->comment('{$column['comment']}')";
        }

        // if ($type === 'foreignId' && !empty($column['referenceTable'])) {
        //     $refCol = $column['referenceColumn'] ?? 'id';
        //     if ($refCol === 'id') {
        //         $line .= "->constrained('{$column['referenceTable']}')";
        //     } else {
        //         $line .= "->constrained('{$column['referenceTable']}', '{$refCol}')";
        //     }
        // }

        return $line;
    }

    /**
     * Generate drop column code for deleted columns.
     */
    public static function generateDropColumnDefinitions(array $deletedColumns): string
    {
        if (empty($deletedColumns)) {
            return '';
        }

        $lines = [];
        foreach ($deletedColumns as $col) {
            $name = $col['name'];
            if (in_array($name, ['id', 'school_id', 'created_at', 'updated_at'])) {
                continue;
            }
            $lines[] = "        \$this->schema->dropColumn('{$name}');";
        }

        return implode("\n", $lines);
    }

    /**
     * Export schema from an array of column definitions and table data.
     */
    public static function exportFromArray(string $table, array $columnsOrTableData, string $outputPath): array
    {
        $tableData = (isset($columnsOrTableData['columns']) || isset($columnsOrTableData['updated_columns']))
            ? $columnsOrTableData
            : ['columns' => $columnsOrTableData];

        $mergedColumns = self::mergeManifestColumns($tableData);

        $mergedColumns = self::applyIndexesToColumns(
            $mergedColumns,
            $tableData['indexes'] ?? []
        );
        $root = config('sdk.modules.root');

        $fullPath = (str_starts_with($outputPath, '/') || str_contains($outputPath, ':\\'))
            ? $outputPath
            : rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($outputPath, DIRECTORY_SEPARATOR);

        $relativeTablePath = str_replace([rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $fullPath);
        $foreignKeys = $tableData['foreign_keys'] ?? [];

        if (empty($foreignKeys)) {
            $foreignKeys = self::extractForeignKeysFromColumns($mergedColumns);
        }
        $metadata = [
            'table'        => $table,
            'file'         => $relativeTablePath,
            'columns'      => [],
            'indexes'      => $tableData['indexes'] ?? [],
            'foreign_keys' => $foreignKeys,
            'timestamps'   => $tableData['timestamps'] ?? false,
        ];

        foreach ($mergedColumns as $col) {
            $name = $col['name'];

            if (in_array($name, ['created_at', 'updated_at'])) {
                $metadata['timestamps'] = true;
                continue;
            }

            $columnMeta = [
                'name'          => $name,
                'type'          => $col['type'],
                'length'        => $col['length'] ?? null,
                'precision'     => $col['precision'] ?? null,
                'scale'         => $col['scale'] ?? null,
                'nullable'      => $col['nullable'] ?? false,
                'default'       => $col['default'] ?? null,
                'autoincrement' => $col['autoincrement'] ?? false,
                'primary'       => $col['primary'] ?? false,
                'unique'        => $col['unique'] ?? false,
                'indexed'       => $col['indexed'] ?? false,
                'comment'       => $col['comment'] ?? null,
            ];

            if (isset($col['referenceTable'])) {
                $columnMeta['referenceTable'] = $col['referenceTable'];
                $columnMeta['referenceColumn'] = $col['referenceColumn'] ?? 'id';
                $columnMeta['relationType'] = $col['relationType'] ?? null;
                $columnMeta['onDelete'] = $col['onDelete'] ?? null;
                $columnMeta['onUpdate'] = $col['onUpdate'] ?? null;
                $columnMeta['foreignKeyName'] = $col['foreignKeyName'] ?? null;
            }

            $metadata['columns'][] = $columnMeta;
        }

        File::ensureDirectoryExists(dirname($fullPath));
        Helper::storeJson($fullPath, $metadata);

        return $metadata;
    }

    /**
     * Fetch all columns (including system columns) from the actual database table.
     *
     * Single-column unique/index definitions are merged into the column metadata.
     */
    public static function getFullColumnsFromTable(string $tableName): array
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return [];
        }

        $doctrine = self::getDoctrineConnection();
        $schemaManager = $doctrine->createSchemaManager();
        $tableDetails = $schemaManager->introspectTable($tableName);

        $primaryKeys = $tableDetails->getPrimaryKey()?->getColumns() ?? [];

        $columns = [];


        foreach ($tableDetails->getColumns() as $column) {
            $name = $column->getName();

            $doctrineType = $column
                ->getType()
                ->getTypeRegistry()
                ->lookupName($column->getType());

            $columns[] = [
                'name'          => $name,
                'type'          => self::mapToLaravelType($doctrineType),
                'length'        => $column->getLength(),
                'precision'     => $column->getPrecision(),
                'scale'         => $column->getScale(),
                'nullable'      => !$column->getNotnull(),
                'default'       => $column->getDefault(),
                'autoincrement' => $column->getAutoincrement(),
                'primary'       => in_array($name, $primaryKeys),
                'unique'        => false,
                'indexed'       => false,
                'comment'       => $column->getComment(),
            ];
        }

        /*
     * -------------------------------------------------------------
     * Indexes
     * -------------------------------------------------------------
     *
     * Only single-column indexes are represented directly on
     * the column.
     *
     * Composite indexes remain table-level metadata.
     */
        foreach ($tableDetails->getIndexes() as $index) {
            if ($index->isPrimary()) {
                continue;
            }

            $indexColumns = $index->getColumns();

            if (count($indexColumns) !== 1) {
                continue;
            }

            $indexColumn = $indexColumns[0];

            foreach ($columns as &$column) {
                if ($column['name'] !== $indexColumn) {
                    continue;
                }

                if ($index->isUnique()) {
                    $column['unique'] = true;
                } else {
                    $column['indexed'] = true;
                }

                break;
            }

            unset($column);
        }

        /*
     * -------------------------------------------------------------
     * Foreign keys
     * -------------------------------------------------------------
     *
     * Restore relationship information into the column metadata.
     */
        $foreignKeys = [];

        foreach ($tableDetails->getForeignKeys() as $foreignKey) {
            $referencedTable =
                $foreignKey->getForeignTableName();

            $referencedTable = self::normalizeReferencedTable(
                $tableName,
                $referencedTable
            );

            $foreignKeys[] = [
                'name'       => $foreignKey->getName(),
                'columns'    => $foreignKey->getLocalColumns(),
                'references' => $foreignKey->getForeignColumns(),
                'on'         => $referencedTable,
                'on_delete'  => $foreignKey->onDelete(),
                'on_update'  => $foreignKey->onUpdate(),
            ];
        }

        $columns = self::applyForeignKeysToColumns(
            $columns,
            $foreignKeys
        );

        return $columns;
    }


    /**
     * Get non-primary indexes from an actual database table.
     */
    protected static function getIndexesFromTable(string $tableName): array
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return [];
        }

        $doctrine = self::getDoctrineConnection();
        $schemaManager = $doctrine->createSchemaManager();
        $tableDetails = $schemaManager->introspectTable($tableName);

        $indexes = [];

        foreach ($tableDetails->getIndexes() as $index) {
            if ($index->isPrimary()) {
                continue;
            }

            $indexes[] = [
                'name'    => $index->getName(),
                'columns' => $index->getColumns(),
                'unique'  => $index->isUnique(),
            ];
        }

        return $indexes;
    }
    /**
     * Ensure system columns (id, school_id) are present in the merged columns.
     */
    public static function ensureSystemColumns(array $mergedColumns, string $tableName, array $deletedColumns = []): array
    {
        $deletedMap = array_fill_keys(array_column($deletedColumns, 'name'), true);
        $existingNames = array_column($mergedColumns, 'name');
        $missing = [];

        foreach (['id', 'school_id'] as $sysCol) {
            if (!isset($deletedMap[$sysCol]) && !in_array($sysCol, $existingNames)) {
                $missing[] = $sysCol;
            }
        }

        if (empty($missing)) {
            return $mergedColumns;
        }

        $systemColumns = self::getFullColumnsFromTable($tableName);
        foreach ($systemColumns as $sysCol) {
            if (in_array($sysCol['name'], $missing)) {
                $mergedColumns[] = $sysCol;
            }
        }

        return $mergedColumns;
    }

    /**
     * Generate a short single-column index name deterministically.
     */
    protected static function shortIndexName(string $table, string $column, string $type = 'idx'): string
    {
        return strtolower("{$type}_{$table}_{$column}");
    }

    /**
     * Generate a short composite index name deterministically.
     */
    protected static function shortCompositeIndexName(string $table, array $columns, string $type = 'idx'): string
    {
        $colString = implode('_', $columns);
        return strtolower("{$type}_{$table}_{$colString}");
    }

    /**
     * Remove columns, updated_columns, and deleted_columns from the manifest.
     */
    public static function removeColumnsFromManifest(string $tableName, string $manifestPath, string $prefix): void
    {
        $existingManifest = ManifestFactory::loadManifest($manifestPath);
        if (!$existingManifest) {
            return;
        }

        $tables = $existingManifest['migrations']['tables'] ?? [];

        foreach ($tables as &$table) {
            $expectedTableName = $prefix . '_' . $table['name'];
            if ($expectedTableName === $tableName) {
                unset($table['columns'], $table['updated_columns'], $table['deleted_columns']);
                break;
            }
        }

        $existingManifest['migrations']['tables'] = $tables;
        ManifestFactory::update($existingManifest, ['migrations' => $existingManifest['migrations']], $manifestPath);
    }

    /**
     * Update manifest after export.
     */
    protected static function updateManifestAfterExport(
        string $tableName,
        string $schemaOutputPath,
        string $manifestPath,
        string $prefix
    ): void {
        $existingManifest = ManifestFactory::loadManifest($manifestPath);

        if (!$existingManifest) {
            return;
        }

        $root = config('sdk.modules.root');

        $relativeSchemaPath = str_replace(
            [
                rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
                '\\',
            ],
            [
                '',
                '/',
            ],
            $schemaOutputPath
        );

        $tables = $existingManifest['migrations']['tables'] ?? [];

        foreach ($tables as &$table) {
            $expectedTableName =
                $prefix . '_' . ($table['name'] ?? '');

            if (
                $expectedTableName !== $tableName &&
                ($table['name'] ?? null) !== $tableName
            ) {
                continue;
            }

            /*
         * Update exported schema path.
         */
            $table['schema'] = $relativeSchemaPath;

            /*
         * Resolve dependencies from the final column definitions.
         *
         * Both arrays are considered because updated_columns may
         * contain newly added foreign keys before the final export.
         */
            $columns = array_merge(
                $table['columns'] ?? [],
                $table['updated_columns'] ?? []
            );

            $dependsOn = [];

            foreach ($columns as $column) {
                if (!is_array($column)) {
                    continue;
                }

                $referenceTable = $column['referenceTable'] ?? null;

                if (
                    is_string($referenceTable) &&
                    trim($referenceTable) !== ''
                ) {
                    $dependsOn[] = trim($referenceTable);
                }
            }

            /*
         * Keep dependencies unique and preserve their order.
         */
            $table['depends_on'] = array_values(
                array_unique($dependsOn)
            );

            /*
         * These are temporary designer/export fields.
         * The canonical manifest keeps only the final schema.
         */
            unset(
                $table['columns'],
                $table['updated_columns'],
                $table['deleted_columns']
            );

            break;
        }

        unset($table);

        $existingManifest['migrations']['tables'] = $tables;

        ManifestFactory::update(
            $existingManifest,
            [
                'migrations' => $existingManifest['migrations'],
            ],
            $manifestPath
        );
    }

    /**
     * Regenerate the schema JSON file directly from the database table.
     */
    public static function regenerateSchemaFromTable(string $table, string $migrationPath, string $schemaOutputPath): array
    {
        return self::export($table, $migrationPath, $schemaOutputPath);
    }

    /**
     * Full workflow: generate migration code, export schema, and clean manifest.
     */
    public static function exportFromManifest(
        string $tableName,
        array $columnsOrTableData,
        string $migrationPath,
        string $schemaOutputPath,
        string $manifestPath,
        ?string $prefix = null
    ): array {
        /*
     * Check the physical database state first.
     *
     * The manifest can still contain a table after its migration has
     * been rolled back. In that case updated_columns are part of the
     * next CREATE definition, NOT ALTER/UPDATE operations.
     */
        $tableExists = DB::getSchemaBuilder()->hasTable($tableName);

        /*
     * Merge the manifest column definitions.
     *
     * This always happens, whether the table exists or not.
     * If the table was rolled back, updated_columns therefore become
     * part of the definition used to recreate the table.
     */
        $mergedColumns = (
            isset($columnsOrTableData['columns']) ||
            isset($columnsOrTableData['updated_columns'])
        )
            ? self::mergeManifestColumns($columnsOrTableData)
            : $columnsOrTableData;

        $deletedColumns = $columnsOrTableData['deleted_columns'] ?? [];

        /*
     * If there are no manifest columns and the physical table exists,
     * recover the current database definition.
     *
     * If the table does not exist, there is nothing to recover from DB.
     */
        if (empty($mergedColumns) && $tableExists) {
            $mergedColumns = self::getFullColumnsFromTable($tableName);
        }

        /*
     * Ensure system columns are present.
     */
        $mergedColumns = self::ensureSystemColumns(
            $mergedColumns,
            $tableName,
            $deletedColumns
        );

        /*
     * Determine the module/table prefix.
     */
        if ($prefix === null) {
            $parts = explode('_', $tableName);
            array_pop($parts);
            $prefix = implode('_', $parts);
        }

        /*
     * Database indexes only exist when the physical table exists.
     *
     * After rollback there is no DB index state to apply.
     */
        $indexes = $tableExists
            ? self::getIndexesFromTable($tableName)
            : [];

        if ($tableExists) {
            $mergedColumns = self::applyIndexesToColumns(
                $mergedColumns,
                $indexes
            );
        }

        /*
     * Generate CREATE definitions.
     *
     * These definitions are also used when the table has been rolled
     * back, because mergedColumns contains the latest UI changes.
     */
        $columnsCode = self::generateColumnDefinitions($mergedColumns);

        /*
     * Drop operations only apply to an existing physical table.
     */
        $dropCode = $tableExists
            ? self::generateDropColumnDefinitions($deletedColumns)
            : '';

        /*
     * UPDATE/ALTER operations only apply when the physical table exists.
     *
     * If the migration was rolled back, updated_columns must NOT be
     * returned as updateCode. They are already part of mergedColumns
     * and will therefore be used when the table is recreated.
     */
        $originalColumns = $columnsOrTableData['columns'] ?? [];
        $updatedColumns = $columnsOrTableData['updated_columns'] ?? [];

        $updateCode = $tableExists
            ? self::generateUpdateColumnDefinitions(
                $originalColumns,
                $updatedColumns
            )
            : '';

        $foreignKeys = self::extractForeignKeysFromColumns($mergedColumns);

        $foreignKeysCode = self::generateForeignKeyDefinitions(
            $foreignKeys,
            '$table'
        );

        /*
     * Export the schema definition.
     *
     * This is useful both for existing tables and for maintaining the
     * schema definition while a table is currently rolled back.
     */
        if (!empty($mergedColumns)) {
            self::exportFromArray(
                $tableName,
                $columnsOrTableData,
                $schemaOutputPath
            );
        }

        /*
     * Update the manifest metadata.
     */
        self::updateManifestAfterExport(
            $tableName,
            $schemaOutputPath,
            $manifestPath,
            $prefix
        );

        /*
     * Apply the migration update.
     *
     * RunMigration handles the actual migration workflow.
     */

        RunMigration::applyTableMigrationUpdate(
            $tableName,
            $migrationPath
        );

        /*
     * Only regenerate the schema from the database if the table
     * actually exists after the migration operation.
     */
        if (DB::getSchemaBuilder()->hasTable($tableName)) {
            self::regenerateSchemaFromTable(
                $tableName,
                $migrationPath,
                $schemaOutputPath
            );
        }

        return [
            'columns_code' => $columnsCode,
            'update_code' => $updateCode,
            'drop_code' => $dropCode,
            'schema_exported' => !empty($mergedColumns),
        ];
    }


    protected static function applyForeignKeysToColumns(
        array $columns,
        array $foreignKeys
    ): array {
        foreach ($foreignKeys as $foreignKey) {
            $localColumns = $foreignKey['columns'] ?? [];
            $referenceColumns = $foreignKey['references'] ?? [];


            /*
         * SchoolPalm currently represents foreignId relationships
         * as single-column relationships.
         */
            if (count($localColumns) !== 1) {
                continue;
            }

            $localColumn = $localColumns[0];
            $referenceColumn = $referenceColumns[0] ?? 'id';


            foreach ($columns as &$column) {
                if (($column['name'] ?? null) !== $localColumn) {
                    continue;
                }

                /*
             * The physical database type is usually bigint/char/etc.
             *
             * Restore the logical SchoolPalm column type so the UI
             * does not lose the fact that this is a foreignId.
             */
                $column['type'] = 'foreignId';

                $column['referenceTable'] = $foreignKey['on'] ?? null;
                $column['referenceColumn'] = $referenceColumn;
                $column['relationType'] = $foreignKey['relationType'] ?? null;
                $column['onDelete'] = $foreignKey['on_delete'] ?? null;
                $column['onUpdate'] = $foreignKey['on_update'] ?? null;

                /*
             * Keep the actual database constraint name as metadata.
             *
             * This is useful when inspecting/exporting the schema,
             * but generated migrations should NOT use this name.
             */
                $column['foreignKeyName'] = $foreignKey['name'] ?? null;

                break;
            }

            unset($column);
        }

        return $columns;
    }

    protected static function extractTableDependencies(
        string $tableName,
        array $foreignKeys
    ): array {
        $dependencies = [];

        foreach ($foreignKeys as $foreignKey) {
            $referencedTable =
                $foreignKey['on'] ?? null;

            if (!$referencedTable) {
                continue;
            }

            $dependencies[] =
                self::normalizeReferencedTable(
                    $tableName,
                    $referencedTable
                );
        }

        return array_values(
            array_unique($dependencies)
        );
    }
    /**
     * Convert Doctrine types -> Laravel schema types.
     */
    protected static function mapToLaravelType(string $type): string
    {
        return match ($type) {
            'bigint'     => 'bigInteger',
            'integer'    => 'integer',
            'smallint'   => 'smallInteger',
            'tinyint'    => 'tinyInteger',
            'string'     => 'string',
            'text'       => 'text',
            'mediumtext' => 'mediumText',
            'longtext'   => 'longText',
            'boolean'    => 'boolean',
            'datetime'   => 'dateTime',
            'datetimetz' => 'dateTimeTz',
            'timestamp'  => 'timestamp',
            'date'       => 'date',
            'time'       => 'time',
            'float'      => 'float',
            'double'     => 'double',
            'decimal'    => 'decimal',
            'binary'     => 'binary',
            'json'       => 'json',
            'guid'       => 'uuid',
            default      => 'string',
        };
    }


    /**
     * Restore deleted columns by moving them out of 'deleted_columns' back into active definitions.
     * Updates the manifest file accordingly.
     *
     * @param string $tableName Full table name with prefix
     * @param array $columnNames List of column names to restore
     * @param array $tableData Manifest table array definition
     * @param string $manifestPath Path to the manifest file
     * @param string|null $prefix Module table prefix
     * @return array Updated table data
     */
    /**
     * Restore deleted columns by moving them out of 'deleted_columns'
     * back into active definitions.
     *
     * When restoring a column, its current database index state is also
     * restored so that unique/index modifiers are preserved.
     *
     * @param string $tableName Full table name with prefix
     * @param array $columnNames List of column names to restore
     * @param array $tableData Manifest table array definition
     * @param string $manifestPath Path to the manifest file
     * @param string|null $prefix Module table prefix
     * @return array Updated table data
     */
    /**
     * Restore table columns and indexes from schema JSON into manifest array.
     */
    /**
     * Restore table columns from schema JSON into manifest array.
     */
    public static function restoreTableColumns(array $manifest): array
    {
        $root = config('sdk.modules.root');

        if (!isset($manifest['migrations']['tables']) || !is_array($manifest['migrations']['tables'])) {
            return $manifest;
        }

        foreach ($manifest['migrations']['tables'] as &$table) {
            if (empty($table['schema'])) {
                continue;
            }

            $schemaPath = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($table['schema'], DIRECTORY_SEPARATOR);

            if (!File::exists($schemaPath)) {
                continue;
            }

            $schemaData = Helper::loadJson($schemaPath);
            $rawColumns = $schemaData['columns'] ?? [];
            $filteredColumns = [];

            foreach ($rawColumns as $col) {
                // Omit internal and timestamp system columns from the restored manifest declaration
                if (in_array($col['name'], ['id', 'school_id', 'created_at', 'updated_at'])) {
                    continue;
                }

                $filteredColumns[] = $col;
            }

            $table['columns'] = $filteredColumns;
        }

        return $manifest;
    }

    /**
     * Helper to update specific table data block within the manifest file.
     */
    protected static function updateManifestTableData(
        string $tableName,
        array $tableData,
        string $manifestPath,
        ?string $prefix = null
    ): void {
        $existingManifest = ManifestFactory::loadManifest($manifestPath);

        if (!$existingManifest) {
            return;
        }

        if ($prefix === null) {
            $parts = explode('_', $tableName);
            array_pop($parts);
            $prefix = implode('_', $parts);
        }

        $tables = $existingManifest['migrations']['tables'] ?? [];

        /*
     * Columns submitted by the designer.
     *
     * updated_columns contains newly added/modified columns.
     * columns contains the existing column definitions.
     */
        $columns = array_merge(
            $tableData['columns'] ?? [],
            $tableData['updated_columns'] ?? []
        );

        /*
     * Find referenced tables from foreignId columns.
     */
        $dependencies = [];

        foreach ($columns as $column) {
            $referenceTable = $column['referenceTable'] ?? null;

            if (!$referenceTable) {
                continue;
            }

            $dependencies[] = $referenceTable;
        }

        $dependencies = array_values(
            array_unique(
                array_filter(
                    $dependencies,
                    static fn($dependency) =>
                    is_string($dependency) &&
                        $dependency !== ''
                )
            )
        );

        foreach ($tables as &$table) {

            $expectedTableName =
                $prefix . '_' . ($table['name'] ?? '');

            if (
                $expectedTableName === $tableName ||
                ($table['name'] ?? null) === $tableName
            ) {
                /*
             * Save the submitted column definitions.
             */
                $table['columns'] =
                    $tableData['columns'] ?? [];

                if (isset($tableData['deleted_columns'])) {
                    $table['deleted_columns'] =
                        $tableData['deleted_columns'];
                }

                /*
             * Save dependencies discovered from the
             * submitted foreignId columns.
             */
                $table['depends_on'] = $dependencies;

                break;
            }
        }

        unset($table);

        $existingManifest['migrations']['tables'] = $tables;

        ManifestFactory::update(
            $existingManifest,
            [
                'migrations' =>
                $existingManifest['migrations']
            ],
            $manifestPath
        );
    }
}
