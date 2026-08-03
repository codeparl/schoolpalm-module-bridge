<?php

namespace SchoolPalm\ModuleBridge\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Doctrine\DBAL\DriverManager;
use SchoolPalm\ModuleBridge\Support\Helper;
use SchoolPalm\ModuleBridge\Manifest\ManifestFactory;

class SchemaExporter
{
    /**
     * Export schema from database table.
     */
    public static function export(string $table, $migrationPath, string $outputPath): array
    {
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            return [];
        }

        $root = config('sdk.modules.root');

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

        $doctrine = DriverManager::getConnection([
            'driver'   => $driver,
            'host'     => $config['host'] ?? '127.0.0.1',
            'port'     => $config['port'] ?? null,
            'dbname'   => $config['database'],
            'user'     => $config['username'],
            'password' => $config['password'],
            'charset'  => $config['charset'] ?? 'utf8mb4',
        ]);

        $schemaManager = $doctrine->createSchemaManager();
        $tableDetails = $schemaManager->introspectTable($table);
        $primaryKeys = $tableDetails->getPrimaryKey()?->getColumns() ?? [];

        $metadata = [
            'table' => $table,
            'file' => $migrationPath,
            'columns' => [],
            'indexes' => [],
            'foreign_keys' => [],
            'timestamps' => false,
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
                'name' => $name,
                'type' => self::mapToLaravelType($doctrineType),
                'length' => $column->getLength(),
                'nullable' => !$column->getNotnull(),
                'default' => $column->getDefault(),
                'autoincrement' => $column->getAutoincrement(),
                'primary' => in_array($name, $primaryKeys),
            ];
        }

        foreach ($tableDetails->getIndexes() as $index) {
            if ($index->isPrimary()) {
                continue;
            }
            $metadata['indexes'][] = [
                'name' => $index->getName(),
                'columns' => $index->getColumns(),
                'unique' => $index->isUnique(),
            ];
        }

        foreach ($tableDetails->getForeignKeys() as $foreignKey) {
            $metadata['foreign_keys'][] = [
                'name' => $foreignKey->getName(),
                'columns' => $foreignKey->getLocalColumns(),
                'references' => $foreignKey->getForeignColumns(),
                'on' => $foreignKey->getForeignTableName(),
                'on_delete' => $foreignKey->onDelete(),
                'on_update' => $foreignKey->onUpdate(),
            ];
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

        $deletedMap = [];
        foreach ($deleted as $col) {
            $deletedMap[$col['name']] = true;
        }

        $originalMap = [];
        foreach ($original as $col) {
            $originalMap[$col['name']] = $col;
        }

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
                        $merged[$idx] = $upd;
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
     * Skips 'id' and 'school_id' (system columns).
     * Uses $table (Blueprint) for up() method.
     */
    public static function generateColumnDefinitions(array $columns): string
    {
        if (empty($columns)) {
            return '';
        }

        $lines = [];
        foreach ($columns as $col) {
            $name = $col['name'];

            if (in_array($name, ['id', 'school_id','created_at', 'updated_at'])) {
                continue;
            }

            $type = $col['type'];

            $line = "\$table->{$type}('{$name}'";

            if ($type === 'string' && isset($col['length']) && $col['length'] !== null && $col['length'] != 255) {
                $line .= ", {$col['length']}";
            }

            if ($type === 'decimal' && isset($col['precision']) && isset($col['scale'])) {
                $line .= ", {$col['precision']}, {$col['scale']}";
            }

            $line .= ')';

            if ($col['nullable'] ?? false) {
                $line .= '->nullable()';
            }
            if ($col['unique'] ?? false) {
                $line .= '->unique()';
            }
            if ($col['indexed'] ?? false) {
                $line .= '->index()';
            }
            if (isset($col['default']) && $col['default'] !== null && $col['default'] !== '') {
                if (in_array($type, ['string', 'text'])) {
                    $line .= "->default('{$col['default']}')";
                } elseif (in_array($type, ['boolean'])) {
                    $line .= '->default(' . ($col['default'] ? 'true' : 'false') . ')';
                } else {
                    $line .= "->default({$col['default']})";
                }
            }
            if (isset($col['comment']) && $col['comment']) {
                $line .= "->comment('{$col['comment']}')";
            }
            if ($type === 'foreignId' && isset($col['referenceTable']) && $col['referenceTable']) {
                $refCol = $col['referenceColumn'] ?? 'id';
                if ($refCol === 'id') {
                    $line .= "->constrained('{$col['referenceTable']}')";
                } else {
                    $line .= "->constrained('{$col['referenceTable']}', '{$refCol}')";
                }
            }

            $lines[] = '            ' . $line . ';';
        }

        return implode("\n", $lines);
    }

    /**
     * Generate code for the update() method using SchemaBuilder syntax.
     * Uses change_type to determine the correct syntax:
     * - addition: fluent definition (new column)
     * - rename: $this->schema->renameColumn()
     * - type: $this->schema->changeColumn()
     * - modification: fluent + ->change()
     */
    public static function generateUpdateColumnDefinitions(array $originalColumns, array $updatedColumns): string
    {
        if (empty($updatedColumns)) {
            return '';
        }

        $lines = [];

        foreach ($updatedColumns as $upd) {
            $changeType = $upd['change_type'] ?? null;
            $name = $upd['name'];

            // If change_type is not set, fallback to detection (backward compatibility)
            if ($changeType === null) {
                $changeType = self::detectChangeType($originalColumns, $upd);
            }

            switch ($changeType) {
                case 'rename':
                    $previousName = $upd['previous_name'] ?? null;
                    if ($previousName && $previousName !== $name) {
                        $lines[] = "        \$this->schema->renameColumn('{$previousName}', '{$name}');";
                    }
                    break;

                case 'type':
                    $params = self::getColumnParams($upd);
                    $paramsString = !empty($params) ? '[' . implode(', ', $params) . ']' : '[]';
                    $lines[] = "        \$this->schema->changeColumn('{$name}', '{$upd['type']}', {$paramsString});";
                    break;

                case 'modification':
                    $def = self::generateFluentColumnDefinition($upd, '$this->schema') . '->change()';
                    $lines[] = "        " . $def . ";";
                    break;

                case 'addition':
                default:
                    $def = self::generateFluentColumnDefinition($upd, '$this->schema');
                    $lines[] = "        " . $def . ";";
                    break;
            }
        }

        return implode("\n", $lines);
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

        // Check rename
        if (($updatedColumn['previous_name'] ?? null) && $updatedColumn['previous_name'] !== $updatedColumn['name']) {
            return 'rename';
        }

        // Check type change
        if ($original['type'] !== $updatedColumn['type']) {
            return 'type';
        }

        // Check other modifications
        $keysToCompare = ['nullable', 'unique', 'indexed', 'default', 'length', 'precision', 'scale', 'referenceTable', 'referenceColumn', 'comment'];
        foreach ($keysToCompare as $key) {
            $origVal = $original[$key] ?? null;
            $newVal = $updatedColumn[$key] ?? null;
            if ($origVal != $newVal) {
                return 'modification';
            }
        }

        // No change detected
        return 'addition'; // fallback
    }

    /**
     * Get the parameters array for changeColumn based on column type.
     */
    protected static function getColumnParams(array $column): array
    {
        $type = $column['type'];
        $params = [];

        if ($type === 'string' && isset($column['length']) && $column['length'] !== null && $column['length'] != 255) {
            $params[] = $column['length'];
        }

        if ($type === 'decimal' && isset($column['precision']) && isset($column['scale'])) {
            $params[] = $column['precision'];
            $params[] = $column['scale'];
        }

        return $params;
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

        if ($type === 'decimal' && isset($column['precision']) && isset($column['scale'])) {
            $line .= ", {$column['precision']}, {$column['scale']}";
        }

        $line .= ')';

        if ($column['nullable'] ?? false) {
            $line .= '->nullable()';
        }
        if ($column['unique'] ?? false) {
            $line .= '->unique()';
        }
        if ($column['indexed'] ?? false) {
            $line .= '->index()';
        }
        if (isset($column['default']) && $column['default'] !== null && $column['default'] !== '') {
            if (in_array($type, ['string', 'text'])) {
                $line .= "->default('{$column['default']}')";
            } elseif (in_array($type, ['boolean'])) {
                $line .= '->default(' . ($column['default'] ? 'true' : 'false') . ')';
            } else {
                $line .= "->default({$column['default']})";
            }
        }
        if (isset($column['comment']) && $column['comment']) {
            $line .= "->comment('{$column['comment']}')";
        }
        if ($type === 'foreignId' && isset($column['referenceTable']) && $column['referenceTable']) {
            $refCol = $column['referenceColumn'] ?? 'id';
            if ($refCol === 'id') {
                $line .= "->constrained('{$column['referenceTable']}')";
            } else {
                $line .= "->constrained('{$column['referenceTable']}', '{$refCol}')";
            }
        }

        return $line;
    }

    /**
     * Generate drop column code for deleted columns.
     * Uses $this->schema->dropColumn().
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
     * Export schema from an array of column definitions.
     * Includes all columns (including id and school_id) in the schema JSON.
     */
   public static function exportFromArray(string $table, array $columnsOrTableData, string $outputPath): array
{
    if (isset($columnsOrTableData['columns']) || isset($columnsOrTableData['updated_columns'])) {
        $mergedColumns = self::mergeManifestColumns($columnsOrTableData);
    } else {
        $mergedColumns = $columnsOrTableData;
    }

    $root = config('sdk.modules.root');

    // Determine the full path: if $outputPath is absolute, use it directly
    if (str_starts_with($outputPath, '/') || (strpos($outputPath, ':') !== false && strpos($outputPath, ':\\') !== false)) {
        $fullPath = $outputPath;
    } else {
        $fullPath = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($outputPath, DIRECTORY_SEPARATOR);
    }

    // Generate relative path for the file key
    $relativeTablePath = str_replace(rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, '', $fullPath);
    $relativeTablePath = str_replace('\\', '/', $relativeTablePath);

    $metadata = [
        'table' => $table,
        'file' => $relativeTablePath,
        'columns' => [],
        'indexes' => [],
        'foreign_keys' => [],
        'timestamps' => false,
    ];

    foreach ($mergedColumns as $col) {
        $name = $col['name'];

        // Keep all columns, including id and school_id
        if (in_array($name, ['created_at', 'updated_at'])) {
            $metadata['timestamps'] = true;
            continue;
        }

        $metadata['columns'][] = [
            'name' => $name,
            'type' => $col['type'],
            'length' => $col['length'] ?? null,
            'nullable' => $col['nullable'] ?? false,
            'default' => $col['default'] ?? null,
            'autoincrement' => false,
            'primary' => false,
            'unique' => $col['unique'] ?? false,
            'indexed' => $col['indexed'] ?? false,
        ];
    }

    File::ensureDirectoryExists(dirname($fullPath));
    Helper::storeJson($fullPath, $metadata);

    return $metadata;
}

    /**
     * Fetch all columns (including system columns) from the actual database table.
     */
    public static function getFullColumnsFromTable(string $tableName): array
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return [];
        }

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

        $doctrine = DriverManager::getConnection([
            'driver'   => $driver,
            'host'     => $config['host'] ?? '127.0.0.1',
            'port'     => $config['port'] ?? null,
            'dbname'   => $config['database'],
            'user'     => $config['username'],
            'password' => $config['password'],
            'charset'  => $config['charset'] ?? 'utf8mb4',
        ]);

        $schemaManager = $doctrine->createSchemaManager();
        $tableDetails = $schemaManager->introspectTable($tableName);
        $primaryKeys = $tableDetails->getPrimaryKey()?->getColumns() ?? [];

        $columns = [];

        foreach ($tableDetails->getColumns() as $column) {
            $name = $column->getName();
            $doctrineType = $column->getType()->getTypeRegistry()->lookupName($column->getType());

            $columns[] = [
                'name' => $name,
                'type' => self::mapToLaravelType($doctrineType),
                'length' => $column->getLength(),
                'nullable' => !$column->getNotnull(),
                'default' => $column->getDefault(),
                'autoincrement' => $column->getAutoincrement(),
                'primary' => in_array($name, $primaryKeys),
                'unique' => false,
                'indexed' => false,
                'comment' => null,
            ];
        }

        return $columns;
    }

    /**
     * Ensure system columns (id, school_id) are present in the merged columns.
     * If missing, fetch them from the database table (if it exists).
     * Skips columns that are in the deleted list.
     */
    public static function ensureSystemColumns(array $mergedColumns, string $tableName, array $deletedColumns = []): array
    {
        $deletedMap = [];
        foreach ($deletedColumns as $col) {
            $deletedMap[$col['name']] = true;
        }

        $existingNames = array_column($mergedColumns, 'name');
        $missing = [];

        foreach (['id', 'school_id'] as $sysCol) {
            if (isset($deletedMap[$sysCol])) {
                continue;
            }
            if (!in_array($sysCol, $existingNames)) {
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
                unset($table['columns']);
                unset($table['updated_columns']);
                unset($table['deleted_columns']);
                break;
            }
        }

        $existingManifest['migrations']['tables'] = $tables;
        ManifestFactory::update($existingManifest, ['migrations' => $existingManifest['migrations']], $manifestPath);
    }

    /**
     * Update manifest after export:
     * - Set the 'schema' property to the relative path
     * - Remove 'columns', 'updated_columns', and 'deleted_columns' keys
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
        $relativeSchemaPath = str_replace(rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, '', $schemaOutputPath);
        $relativeSchemaPath = str_replace('\\', '/', $relativeSchemaPath);

        $tables = $existingManifest['migrations']['tables'] ?? [];

        foreach ($tables as &$table) {
            $expectedTableName = $prefix . '_' . $table['name'];
            if ($expectedTableName === $tableName) {
                $table['schema'] = $relativeSchemaPath;
                unset($table['columns']);
                unset($table['updated_columns']);
                unset($table['deleted_columns']);
                break;
            }
        }

        $existingManifest['migrations']['tables'] = $tables;
        ManifestFactory::update($existingManifest, ['migrations' => $existingManifest['migrations']], $manifestPath);
    }

    /**
     * Regenerate the schema JSON file directly from the database table.
     * This is useful after running migrations to ensure the schema file reflects the actual DB structure.
     *
     * @param string $table           The full prefixed table name
     * @param string $migrationPath   The migration file path (used for metadata)
     * @param string $schemaOutputPath The output path for the schema JSON
     * @return array  The exported schema data
     */
    public static function regenerateSchemaFromTable(string $table, string $migrationPath, string $schemaOutputPath): array
    {
        // Use the existing export method which fetches from the database
        return self::export($table, $migrationPath, $schemaOutputPath);
    }

    /**
     * Full workflow: generate migration code, export schema, and clean manifest.
     * After exporting, the schema is regenerated from the database to ensure accuracy.
     */
    public static function exportFromManifest(
        string $tableName,
        array $columnsOrTableData,
        string $migrationPath,
        string $schemaOutputPath,
        string $manifestPath,
        ?string $prefix = null
    ): array {
        // Determine merged columns from manifest
        if (isset($columnsOrTableData['columns']) || isset($columnsOrTableData['updated_columns'])) {
            $mergedColumns = self::mergeManifestColumns($columnsOrTableData);
        } else {
            $mergedColumns = $columnsOrTableData;
        }


   
        // Handle deleted columns
        $deletedColumns = $columnsOrTableData['deleted_columns'] ?? [];

        // If no columns defined in manifest, fetch from database
        if (empty($mergedColumns) && DB::getSchemaBuilder()->hasTable($tableName)) {
            $mergedColumns = self::getFullColumnsFromTable($tableName);
        }

      
        // Ensure system columns are present (pass deleted columns to skip them)
        $mergedColumns = self::ensureSystemColumns($mergedColumns, $tableName, $deletedColumns);
       
        // Extract prefix if not provided
        if ($prefix === null) {
            $parts = explode('_', $tableName);
            array_pop($parts);
            $prefix = implode('_', $parts);
        }

        // Generate PHP code (skips internal columns)
        $columnsCode = self::generateColumnDefinitions($mergedColumns);

        // Generate drop column code
        $dropCode = self::generateDropColumnDefinitions($deletedColumns);

        // Generate update code (for altered/renamed/added columns)
        $originalColumns = $columnsOrTableData['columns'] ?? [];
        $updatedColumns = $columnsOrTableData['updated_columns'] ?? [];
        $updateCode = self::generateUpdateColumnDefinitions($originalColumns, $updatedColumns);


        // Export schema JSON (includes all columns, except deleted ones)
        if (!empty($mergedColumns)) {
            self::exportFromArray($tableName, $mergedColumns, $schemaOutputPath);
        }

         
        // Update manifest
        self::updateManifestAfterExport($tableName, $schemaOutputPath, $manifestPath, $prefix);

        // Regenerate schema from the database to ensure it's in sync
        // This will overwrite the schema JSON with the actual DB structure
        if (DB::getSchemaBuilder()->hasTable($tableName)) {
            self::regenerateSchemaFromTable($tableName, $migrationPath, $schemaOutputPath);
        }

        return [
            'columns_code' => $columnsCode,
            'update_code' => $updateCode,
            'drop_code' => $dropCode,
            'schema_exported' => !empty($mergedColumns),
        ];
    }

    /**
     * Convert Doctrine types → Laravel schema types.
     */
    protected static function mapToLaravelType(string $type): string
    {
        return match ($type) {
            'bigint'    => 'bigInteger',
            'integer'   => 'integer',
            'smallint'  => 'smallInteger',
            'tinyint'   => 'tinyInteger',
            'string'    => 'string',
            'text'      => 'text',
            'mediumtext'=> 'mediumText',
            'longtext'  => 'longText',
            'boolean'   => 'boolean',
            'datetime'  => 'dateTime',
            'datetimetz'=> 'dateTimeTz',
            'timestamp' => 'timestamp',
            'date'      => 'date',
            'time'      => 'time',
            'float'     => 'float',
            'double'    => 'double',
            'decimal'   => 'decimal',
            'binary'    => 'binary',
            'json'      => 'json',
            'guid'      => 'uuid',
            default     => 'string',
        };
    }

    /**
     * Restore columns from schema JSON, filtering out internal ones.
     * This is used to rebuild the manifest columns from the schema file.
     */
    public static function restoreTableColumns(array $manifest): array
    {
        foreach ($manifest['migrations']['tables'] as &$m_tables) {
            // Always filter columns to remove internal ones (id, school_id)
            if (isset($m_tables['schema']) && $m_tables['schema']) {
                $path = config('sdk.modules.root') . '/' . $m_tables['schema'];
                if (File::exists($path)) {
                    $schemaData = Helper::loadJson($path);
                    $allColumns = $schemaData['columns'] ?? [];
                    // Filter out id and school_id
                    $filteredColumns = array_filter($allColumns, function ($col) {
                        return !in_array($col['name'], ['id', 'school_id']);
                    });
                    $m_tables['columns'] = array_values($filteredColumns);
                }
            } else {
                // If no schema, but columns exist, filter them as well
                if (isset($m_tables['columns'])) {
                    $filteredColumns = array_filter($m_tables['columns'], function ($col) {
                        return !in_array($col['name'], ['id', 'school_id']);
                    });
                    $m_tables['columns'] = array_values($filteredColumns);
                }
            }
        }
        return $manifest;
    }
}