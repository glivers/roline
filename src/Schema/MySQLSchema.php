<?php namespace Roline\Schema;

use Roline\Utils\ModelParser;
use Rackage\Database\Database;
use Rackage\Registry;

/**
 * MySQLSchema - MySQL Schema Generation and Management
 *
 * Generates MySQL-specific CREATE TABLE and ALTER TABLE statements
 * from driver-agnostic schema definitions.
 *
 * Usage:
 *   $schema = new MySQLSchema();
 *   $schema->createTableFromModel('Models\\User');
 *   $schema->updateTableFromModel('Models\\Post');
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright 2015 - 2030 Geoffrey Okongo
 * @category Roline
 * @package Roline\Schema
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 1.0.0
 */

class MySQLSchema
{
    /**
     * Model parser instance
     * @var ModelParser
     */
    private $parser;

    /**
     * Database connection
     * @var object
     */
    private $connection;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->parser = new ModelParser();
    }

    /**
     * Create table from Model class
     *
     * @param string $modelClass Fully qualified model class name (e.g., 'Models\\User')
     * @return bool True on success
     * @throws \Exception
     */
    public function createTableFromModel($modelClass)
    {
        // Parse model class
        $schema = $this->parser->parseModelClass($modelClass);

        // Validate schema has columns
        if (empty($schema['columns'])) {
            throw new \Exception(
                "Model has no valid @column definitions.\n" .
                "       Add properties with @column and type annotations.\n" .
                "       Example:\n" .
                "       /**\n" .
                "        * @column\n" .
                "        * @varchar 255\n" .
                "        */\n" .
                "       protected \$username;"
            );
        }

        // Validate foreign key constraints before creating table
        $this->validateForeignKeys($schema);

        // Generate CREATE TABLE SQL
        $sql = $this->generateCreateTableSQL($schema);

        // Get database connection
        $this->ensureConnection();

        // Drop existing table first
        $dropSQL = "DROP TABLE IF EXISTS `{$schema['table']}`;";
        $this->connection->execute($dropSQL);

        // Create table
        $result = $this->connection->execute($sql);

        if (!$result) {
            $error = $this->connection->lastError();
            throw new \Exception(
                "Failed to execute SQL.\n\n" .
                "SQL:\n{$sql}\n\n" .
                "Error: {$error}"
            );
        }

        return true;
    }

    /**
     * Update table from Model class
     *
     * @param string $modelClass Fully qualified model class name
     * @return bool True on success
     * @throws \Exception
     */
    public function updateTableFromModel($modelClass, $confirmationCallback = null)
    {
        // Parse model class
        $schema = $this->parser->parseModelClass($modelClass);

        // Get existing table columns
        $existingColumns = $this->getExistingColumns($schema['table']);

        // Generate ALTER TABLE statements with drop and rename info
        $result = $this->generateAlterTableSQL($schema, $existingColumns);
        $statements = $result['statements'];
        $dropColumns = $result['drop_columns'];
        $renameColumns = $result['rename_columns'];

        if (empty($statements)) {
            echo "\n";
            echo "✓ No changes needed - table is up to date!\n";
            return true; // No changes needed
        }

        // Preview changes before executing
        echo "\n";
        echo "Planned changes:\n";
        foreach ($statements as $i => $stmt) {
            echo "  " . ($i + 1) . ". " . substr($stmt, 0, 100);
            if (strlen($stmt) > 100) {
                echo "...";
            }
            echo "\n";
        }
        echo "\n";

        // Check for slow operations and warn user
        $rowCount = $this->getRowCount($schema['table']);
        $hasSlowOps = false;

        foreach ($statements as $stmt) {
            // Warn about adding indexes on large tables
            if (stripos($stmt, 'ADD INDEX') !== false && $rowCount > 100000) {
                $hasSlowOps = true;
                $estimatedSeconds = ceil($rowCount / 50000); // ~50K rows per second
                $estimatedMinutes = ceil($estimatedSeconds / 60);

                echo "⚠ WARNING: Adding index to large table (" . number_format($rowCount) . " rows)\n";
                echo "           This may take " . $estimatedMinutes . "-" . ($estimatedMinutes * 3) . " minutes.\n";
                echo "           DO NOT INTERRUPT - let it complete!\n\n";
                break; // Only show warning once
            }

            // Warn about modifying columns on large tables
            if (stripos($stmt, 'MODIFY COLUMN') !== false && $rowCount > 100000) {
                $hasSlowOps = true;
                echo "⚠ WARNING: Modifying column on large table (" . number_format($rowCount) . " rows)\n";
                echo "           This may take several minutes.\n\n";
                break; // Only show warning once
            }

            // Warn about dropping indexes on large tables (can require table rebuild)
            if (stripos($stmt, 'DROP INDEX') !== false && $rowCount > 100000) {
                $hasSlowOps = true;
                $estimatedSeconds = ceil($rowCount / 50000); // ~50K rows per second
                $estimatedMinutes = ceil($estimatedSeconds / 60);

                echo "⚠ WARNING: Dropping index on large table (" . number_format($rowCount) . " rows)\n";
                echo "           This may take " . $estimatedMinutes . "-" . ($estimatedMinutes * 3) . " minutes.\n";
                echo "           DO NOT INTERRUPT - let it complete!\n\n";
                break; // Only show warning once
            }
        }

        // If there are columns to drop or rename, ask for confirmation
        if ((!empty($dropColumns) || !empty($renameColumns)) && is_callable($confirmationCallback)) {
            $confirmed = $confirmationCallback($dropColumns, $renameColumns);
            if (!$confirmed) {
                // User said no - abort all changes
                echo "\n";
                echo "✗ Aborted - no changes made.\n";
                return false; // Return false to indicate abort
            }
        }

        // Get database connection
        $this->ensureConnection();

        // Execute each ALTER statement with progress display
        $total = count($statements);
        echo "Executing " . $total . " statement" . ($total > 1 ? 's' : '') . "...\n\n";

        foreach ($statements as $i => $sql) {
            $num = $i + 1;

            // Show what's being executed
            echo "[$num/$total] " . substr($sql, 0, 80);
            if (strlen($sql) > 80) {
                echo "...";
            }
            echo "\n";

            // Execute and time it
            $start = microtime(true);
            $result = $this->connection->execute($sql);
            $elapsed = microtime(true) - $start;

            if (!$result) {
                throw new \Exception("Failed to update table '{$schema['table']}': " . $this->connection->lastError());
            }

            // Show completion time
            if ($elapsed > 1) {
                echo "        ✓ Completed in " . round($elapsed, 2) . "s";
                if ($elapsed > 60) {
                    echo " (" . round($elapsed / 60, 1) . " min)";
                }
                echo "\n";
            } else {
                echo "        ✓ Completed in " . round($elapsed * 1000, 0) . "ms\n";
            }

            echo "\n";
        }

        return true;
    }

    /**
     * Generate CREATE TABLE SQL statement
     *
     * @param array $schema Schema definition from ModelParser
     * @return string CREATE TABLE SQL
     */
    public function generateCreateTableSQL($schema)
    {
        $tableName = $schema['table'];
        $columns = $schema['columns'];

        $columnDefinitions = [];
        $primaryKeys = [];
        $uniqueKeys = [];
        $indexes = [];
        $foreignKeys = [];

        foreach ($columns as $column) {
            // Skip dropped columns
            if ($column['drop']) {
                continue;
            }

            $def = "`{$column['name']}` ";

            // Type and length
            if ($column['values']) {
                // ENUM or SET type
                $values = array_map(function($v) {
                    return "'" . addslashes($v) . "'";
                }, $column['values']);
                $def .= "{$column['type']}(" . implode(', ', $values) . ")";
            } elseif ($column['length']) {
                $def .= "{$column['type']}({$column['length']})";
            } else {
                $def .= $column['type'];
            }

            // UNSIGNED
            if ($column['unsigned']) {
                $def .= " UNSIGNED";
            }

            // NULL/NOT NULL
            $def .= $column['nullable'] ? " NULL" : " NOT NULL";

            // AUTO_INCREMENT
            if ($column['autoincrement']) {
                $def .= " AUTO_INCREMENT";
            }

            // DEFAULT
            if ($column['default'] !== null) {
                if (in_array(strtoupper($column['default']), ['CURRENT_TIMESTAMP', 'NULL'])) {
                    $def .= " DEFAULT {$column['default']}";
                } else {
                    $def .= " DEFAULT '" . addslashes($column['default']) . "'";
                }
            }

            $columnDefinitions[] = $def;

            // Track keys and indexes
            if ($column['primary']) {
                $primaryKeys[] = $column['name'];
            }
            if ($column['unique']) {
                $uniqueKeys[] = $column['name'];
            }
            if ($column['index']) {
                $indexes[] = $column['name'];
            }

            // Track foreign keys
            if (!empty($column['foreign'])) {
                $foreignKeys[] = [
                    'column' => $column['name'],
                    'references' => $column['foreign'],
                    'on_delete' => $column['on_delete'] ?? 'RESTRICT',
                    'on_update' => $column['on_update'] ?? 'RESTRICT',
                ];
            }
        }

        // Add primary key
        if (!empty($primaryKeys)) {
            $columnDefinitions[] = "PRIMARY KEY (`" . implode('`, `', $primaryKeys) . "`)";
        }

        // Add unique keys
        foreach ($uniqueKeys as $key) {
            $columnDefinitions[] = "UNIQUE KEY `{$key}_unique` (`{$key}`)";
        }

        // Add indexes
        foreach ($indexes as $key) {
            $columnDefinitions[] = "KEY `{$key}_index` (`{$key}`)";
        }

        // Add composite indexes
        if (!empty($schema['composite_indexes'])) {
            foreach ($schema['composite_indexes'] as $indexName => $columns) {
                $columnList = '`' . implode('`, `', $columns) . '`';
                $columnDefinitions[] = "KEY `{$indexName}` ({$columnList})";
            }
        }

        // Add composite unique indexes
        if (!empty($schema['composite_unique_indexes'])) {
            foreach ($schema['composite_unique_indexes'] as $indexName => $columns) {
                $columnList = '`' . implode('`, `', $columns) . '`';
                $columnDefinitions[] = "UNIQUE KEY `{$indexName}` ({$columnList})";
            }
        }

        // Add foreign keys
        foreach ($foreignKeys as $fk) {
            // Parse foreign key reference: "table(column)"
            if (preg_match('/^(\w+)\((\w+)\)$/', $fk['references'], $matches)) {
                $refTable = $matches[1];
                $refColumn = $matches[2];

                $constraintName = "fk_{$tableName}_{$fk['column']}";
                $fkDef = "CONSTRAINT `{$constraintName}` FOREIGN KEY (`{$fk['column']}`) ";
                $fkDef .= "REFERENCES `{$refTable}` (`{$refColumn}`)";

                if (!empty($fk['on_delete'])) {
                    $fkDef .= " ON DELETE {$fk['on_delete']}";
                }

                if (!empty($fk['on_update'])) {
                    $fkDef .= " ON UPDATE {$fk['on_update']}";
                }

                $columnDefinitions[] = $fkDef;
            }
        }

        $sql = "CREATE TABLE `{$tableName}` (\n  ";
        $sql .= implode(",\n  ", $columnDefinitions);
        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        return $sql;
    }

    /**
     * Generate ALTER TABLE SQL statements
     *
     * @param array $schema New schema definition
     * @param array $existingColumns Existing columns from database
     * @return array Array with 'statements' and 'drop_columns' keys
     */
    public function generateAlterTableSQL($schema, $existingColumns)
    {
        $tableName = $schema['table'];
        $statements = [];
        $dropColumns = [];
        $renameColumns = [];

        $newColumns = [];
        foreach ($schema['columns'] as $column) {
            $newColumns[$column['name']] = $column;
        }

        // Handle drops - columns with @drop annotation
        foreach ($schema['columns'] as $column) {
            if ($column['drop']) {
                $dropColumns[] = [
                    'name' => $column['name'],
                    'reason' => '@drop annotation'
                ];
                $statements[] = "ALTER TABLE `{$tableName}` DROP COLUMN `{$column['name']}`;";
            }
        }

        // Handle orphaned columns - exist in DB but not in model
        foreach ($existingColumns as $columnName => $columnInfo) {
            if (!isset($newColumns[$columnName])) {
                // Check if already marked for drop via @drop annotation
                $alreadyMarked = false;
                foreach ($dropColumns as $dc) {
                    if ($dc['name'] === $columnName) {
                        $alreadyMarked = true;
                        break;
                    }
                }

                // Check if this column is being renamed (not orphaned)
                $isBeingRenamed = false;
                foreach ($schema['columns'] as $column) {
                    if (!empty($column['rename']) && $column['rename'] === $columnName) {
                        $isBeingRenamed = true;
                        break;
                    }
                }

                if (!$alreadyMarked && !$isBeingRenamed) {
                    $dropColumns[] = [
                        'name' => $columnName,
                        'reason' => 'not in model (orphaned)'
                    ];
                    $statements[] = "ALTER TABLE `{$tableName}` DROP COLUMN `{$columnName}`;";
                }
            }
        }

        // Handle renames
        foreach ($schema['columns'] as $column) {
            if ($column['rename']) {
                $oldName = $column['rename'];
                $newName = $column['name'];
                $renameColumns[] = [
                    'old_name' => $oldName,
                    'new_name' => $newName
                ];
                $columnDef = $this->getColumnDefinition($column);
                $statements[] = "ALTER TABLE `{$tableName}` CHANGE `{$oldName}` `{$newName}` {$columnDef};";
            }
        }

        // Handle new columns and modifications
        foreach ($newColumns as $name => $column) {
            if ($column['drop'] || $column['rename']) {
                continue;
            }

            if (!isset($existingColumns[$name])) {
                // New column
                $columnDef = $this->getColumnDefinition($column);
                $statements[] = "ALTER TABLE `{$tableName}` ADD COLUMN `{$name}` {$columnDef};";
            } else {
                // Column exists - check if ACTUALLY modified (smart diffing)
                if ($this->columnDefinitionChanged($existingColumns[$name], $column)) {
                    $columnDef = $this->getColumnDefinition($column);
                    $statements[] = "ALTER TABLE `{$tableName}` MODIFY COLUMN `{$name}` {$columnDef};";
                }
                // Otherwise skip - no change needed!
            }
        }

        // Handle foreign keys
        $existingForeignKeys = $this->getExistingForeignKeys($tableName);

        // Collect model foreign keys
        $modelForeignKeys = [];
        foreach ($schema['columns'] as $column) {
            if (!empty($column['foreign']) && !$column['drop']) {
                $constraintName = "fk_{$tableName}_{$column['name']}";
                $modelForeignKeys[$constraintName] = [
                    'column' => $column['name'],
                    'references' => $column['foreign'],
                    'on_delete' => $column['on_delete'] ?? 'RESTRICT',
                    'on_update' => $column['on_update'] ?? 'RESTRICT',
                ];
            }
        }

        // Drop foreign keys that exist in DB but not in model, OR have changed
        foreach ($existingForeignKeys as $constraintName => $fkInfo) {
            $shouldDrop = false;

            if (!isset($modelForeignKeys[$constraintName])) {
                // FK exists in DB but not in model - drop it
                $shouldDrop = true;
            } elseif ($fkInfo['references'] !== $modelForeignKeys[$constraintName]['references']) {
                // FK exists in both but references changed - drop and will re-add below
                $shouldDrop = true;
            }

            if ($shouldDrop) {
                $statements[] = "ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraintName}`;";
            }
        }

        // Add foreign keys that exist in model but not in DB, OR have changed
        foreach ($modelForeignKeys as $constraintName => $fk) {
            $shouldAdd = false;

            if (!isset($existingForeignKeys[$constraintName])) {
                // FK exists in model but not in DB - add it
                $shouldAdd = true;
            } elseif ($existingForeignKeys[$constraintName]['references'] !== $fk['references']) {
                // FK exists in both but references changed - re-add it (already dropped above)
                $shouldAdd = true;
            }

            if ($shouldAdd) {
                // Parse foreign key reference: "table(column)"
                if (preg_match('/^(\w+)\((\w+)\)$/', $fk['references'], $matches)) {
                    $refTable = $matches[1];
                    $refColumn = $matches[2];

                    $fkDef = "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$constraintName}` ";
                    $fkDef .= "FOREIGN KEY (`{$fk['column']}`) REFERENCES `{$refTable}` (`{$refColumn}`)";

                    if (!empty($fk['on_delete'])) {
                        $fkDef .= " ON DELETE {$fk['on_delete']}";
                    }

                    if (!empty($fk['on_update'])) {
                        $fkDef .= " ON UPDATE {$fk['on_update']}";
                    }

                    $statements[] = $fkDef . ";";
                }
            }
        }

        // Handle composite indexes
        $existingCompositeIndexes = $this->getExistingCompositeIndexes($tableName);
        $modelCompositeIndexes = $schema['composite_indexes'] ?? [];

        // Drop composite indexes that exist in DB but not in model, or have changed
        foreach ($existingCompositeIndexes as $indexName => $indexInfo) {
            $shouldDrop = false;

            if (!isset($modelCompositeIndexes[$indexName])) {
                // Index exists in DB but not in model - drop it
                $shouldDrop = true;
            } elseif ($indexInfo['columns'] !== $modelCompositeIndexes[$indexName]) {
                // Index exists but columns changed - drop and re-add
                $shouldDrop = true;
            }

            if ($shouldDrop) {
                $statements[] = "ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`;";
            }
        }

        // Add composite indexes that exist in model but not in DB, or have changed
        foreach ($modelCompositeIndexes as $indexName => $columns) {
            $shouldAdd = false;

            if (!isset($existingCompositeIndexes[$indexName])) {
                // Index exists in model but not in DB - add it
                $shouldAdd = true;
            } elseif ($existingCompositeIndexes[$indexName]['columns'] !== $columns) {
                // Index exists but columns changed - re-add it (already dropped above)
                $shouldAdd = true;
            }

            if ($shouldAdd) {
                $columnList = '`' . implode('`, `', $columns) . '`';
                $statements[] = "ALTER TABLE `{$tableName}` ADD INDEX `{$indexName}` ({$columnList});";
            }
        }

        // Handle composite unique indexes
        $existingCompositeUniqueIndexes = $this->getExistingCompositeUniqueIndexes($tableName);
        $modelCompositeUniqueIndexes = $schema['composite_unique_indexes'] ?? [];

        // Drop composite unique indexes that exist in DB but not in model, or have changed
        foreach ($existingCompositeUniqueIndexes as $indexName => $indexInfo) {
            $shouldDrop = false;

            if (!isset($modelCompositeUniqueIndexes[$indexName])) {
                // Index exists in DB but not in model - drop it
                $shouldDrop = true;
            } elseif ($indexInfo['columns'] !== $modelCompositeUniqueIndexes[$indexName]) {
                // Index exists but columns changed - drop and re-add
                $shouldDrop = true;
            }

            if ($shouldDrop) {
                $statements[] = "ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`;";
            }
        }

        // Add composite unique indexes that exist in model but not in DB, or have changed
        foreach ($modelCompositeUniqueIndexes as $indexName => $columns) {
            $shouldAdd = false;

            if (!isset($existingCompositeUniqueIndexes[$indexName])) {
                // Index exists in model but not in DB - add it
                $shouldAdd = true;
            } elseif ($existingCompositeUniqueIndexes[$indexName]['columns'] !== $columns) {
                // Index exists but columns changed - re-add it (already dropped above)
                $shouldAdd = true;
            }

            if ($shouldAdd) {
                $columnList = '`' . implode('`, `', $columns) . '`';
                $statements[] = "ALTER TABLE `{$tableName}` ADD UNIQUE INDEX `{$indexName}` ({$columnList});";
            }
        }

        // Handle simple (single-column) indexes
        $existingSimpleIndexes = $this->getExistingSimpleIndexes($tableName);
        $modelSimpleIndexes = $schema['simple_indexes'] ?? [];

        // Drop simple indexes that exist in DB but not in model, or have changed
        foreach ($existingSimpleIndexes as $indexName => $indexInfo) {
            $shouldDrop = false;

            if (!isset($modelSimpleIndexes[$indexName])) {
                // Index exists in DB but not in model - drop it
                $shouldDrop = true;
            } elseif ($indexInfo['column'] !== $modelSimpleIndexes[$indexName]['column']
                   || $indexInfo['unique'] !== $modelSimpleIndexes[$indexName]['unique']) {
                // Index exists but definition changed - drop and re-add
                $shouldDrop = true;
            }

            if ($shouldDrop) {
                $statements[] = "ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`;";
            }
        }

        // Add simple indexes that exist in model but not in DB, or have changed
        foreach ($modelSimpleIndexes as $indexName => $indexDef) {
            $shouldAdd = false;

            if (!isset($existingSimpleIndexes[$indexName])) {
                // Index exists in model but not in DB - add it
                $shouldAdd = true;
            } elseif ($existingSimpleIndexes[$indexName]['column'] !== $indexDef['column']
                   || $existingSimpleIndexes[$indexName]['unique'] !== $indexDef['unique']) {
                // Index exists but definition changed - re-add it (already dropped above)
                $shouldAdd = true;
            }

            if ($shouldAdd) {
                $column = '`' . $indexDef['column'] . '`';
                if ($indexDef['unique']) {
                    $statements[] = "ALTER TABLE `{$tableName}` ADD UNIQUE INDEX `{$indexName}` ({$column});";
                } else {
                    $statements[] = "ALTER TABLE `{$tableName}` ADD INDEX `{$indexName}` ({$column});";
                }
            }
        }

        return [
            'statements' => $statements,
            'drop_columns' => $dropColumns,
            'rename_columns' => $renameColumns
        ];
    }

    /**
     * Check if column definition has changed between DB and model
     *
     * Compares existing database column with model annotation to determine
     * if a MODIFY COLUMN statement is needed. Normalizes both definitions
     * for accurate comparison.
     *
     * @param array $dbColumn Column info from SHOW COLUMNS (database)
     * @param array $modelColumn Column definition from model annotations
     * @return bool True if column needs to be modified
     */
    private function columnDefinitionChanged($dbColumn, $modelColumn)
    {
        // Build what the column definition SHOULD be from model
        $newDef = $this->getColumnDefinition($modelColumn);

        // Build what the column definition CURRENTLY is from database
        $currentType = strtoupper($dbColumn['Type']);
        $currentNull = $dbColumn['Null'] === 'YES' ? ' NULL' : ' NOT NULL';
        $currentDefault = '';
        $currentExtra = '';

        // Handle default values
        if ($dbColumn['Default'] !== null) {
            if (in_array(strtoupper($dbColumn['Default']), ['CURRENT_TIMESTAMP', 'NULL'])) {
                $currentDefault = " DEFAULT {$dbColumn['Default']}";
            } else {
                $currentDefault = " DEFAULT '" . addslashes($dbColumn['Default']) . "'";
            }
        }

        // Handle extra (AUTO_INCREMENT, etc)
        if (!empty($dbColumn['Extra'])) {
            $currentExtra = ' ' . strtoupper($dbColumn['Extra']);
        }

        $currentDef = $currentType . $currentNull . $currentDefault . $currentExtra;

        // Normalize both for comparison (remove extra whitespace, make uppercase)
        $currentDef = preg_replace('/\s+/', ' ', strtoupper(trim($currentDef)));
        $newDef = preg_replace('/\s+/', ' ', strtoupper(trim($newDef)));

        // Remove spaces after commas in ENUM/SET (MySQL doesn't include them)
        $currentDef = preg_replace('/,\s+/', ',', $currentDef);
        $newDef = preg_replace('/,\s+/', ',', $newDef);

        // JSON type: MySQL reports just "JSON" but model generates "JSON NULL"
        // Only compare NULL-ability, skip if both are JSON with same NULL-ability
        if ($modelColumn['type'] === 'JSON') {
            $currentIsJson = strpos($currentType, 'JSON') === 0;
            if ($currentIsJson) {
                // Both are JSON - only check if NULL-ability changed
                $currentIsNullable = $dbColumn['Null'] === 'YES';
                $newIsNullable = $modelColumn['nullable'];
                return $currentIsNullable !== $newIsNullable;
            }
            // DB is not JSON but model is - needs modification
        }

        // Compare - if different, modification is needed
        return $currentDef !== $newDef;
    }

    /**
     * Get column definition string for ALTER statements
     *
     * @param array $column Column definition
     * @return string Column definition SQL
     */
    private function getColumnDefinition($column)
    {
        $def = "";

        // Type and length
        if ($column['values']) {
            $values = array_map(function($v) {
                return "'" . addslashes($v) . "'";
            }, $column['values']);
            $def .= "{$column['type']}(" . implode(', ', $values) . ")";
        } elseif ($column['length']) {
            $def .= "{$column['type']}({$column['length']})";
        } else {
            $def .= $column['type'];
        }

        // UNSIGNED
        if ($column['unsigned']) {
            $def .= " UNSIGNED";
        }

        // NULL/NOT NULL
        $def .= $column['nullable'] ? " NULL" : " NOT NULL";

        // AUTO_INCREMENT
        if ($column['autoincrement']) {
            $def .= " AUTO_INCREMENT";
        }

        // DEFAULT
        if ($column['default'] !== null) {
            if (in_array(strtoupper($column['default']), ['CURRENT_TIMESTAMP', 'NULL'])) {
                $def .= " DEFAULT {$column['default']}";
            } else {
                $def .= " DEFAULT '" . addslashes($column['default']) . "'";
            }
        }

        return $def;
    }

    /**
     * Get existing columns from database table
     *
     * @param string $tableName Table name
     * @return array Associative array of column names => column info
     */
    private function getExistingColumns($tableName)
    {
        $this->ensureConnection();

        $sql = "SHOW COLUMNS FROM `{$tableName}`";
        $result = $this->connection->execute($sql);

        if (!$result) {
            // Table doesn't exist yet
            return [];
        }

        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = $row;
        }

        return $columns;
    }

    /**
     * Get existing foreign keys from database table
     *
     * @param string $tableName Table name
     * @return array Associative array of constraint names => FK info
     */
    private function getExistingForeignKeys($tableName)
    {
        $this->ensureConnection();

        $sql = "SELECT
                    CONSTRAINT_NAME,
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tableName}'
                    AND REFERENCED_TABLE_NAME IS NOT NULL";

        $result = $this->connection->execute($sql);

        if (!$result) {
            return [];
        }

        $foreignKeys = [];
        while ($row = $result->fetch_assoc()) {
            $foreignKeys[$row['CONSTRAINT_NAME']] = [
                'column' => $row['COLUMN_NAME'],
                'references' => $row['REFERENCED_TABLE_NAME'] . '(' . $row['REFERENCED_COLUMN_NAME'] . ')'
            ];
        }

        return $foreignKeys;
    }

    /**
     * Ensure database connection is established
     *
     * @return void
     */
    private function ensureConnection()
    {
        if ($this->connection) {
            return;
        }

        // Get database connection from Registry (already initialized and connected)
        try {
            $this->connection = Registry::get('database');
        } catch (\Exception $e) {
            throw new \Exception("Failed to get database connection: " . $e->getMessage());
        }

        if (!$this->connection) {
            throw new \Exception("Database connection not available");
        }
    }

    /**
     * Get table schema as array
     *
     * @param string $tableName Table name
     * @return array Table schema information
     */
    public function getTableSchema($tableName)
    {
        $this->ensureConnection();

        $columns = $this->getExistingColumns($tableName);

        return [
            'table' => $tableName,
            'columns' => $columns,
            'exists' => !empty($columns)
        ];
    }

    /**
     * Check if table exists
     *
     * @param string $tableName Table name
     * @return bool True if table exists
     */
    public function tableExists($tableName)
    {
        $this->ensureConnection();

        $sql = "SHOW TABLES LIKE '{$tableName}'";
        $result = $this->connection->execute($sql);

        return $result && $result->num_rows > 0;
    }

    /**
     * Drop table
     *
     * @param string $tableName Table name
     * @return bool True on success
     */
    public function dropTable($tableName)
    {
        $this->ensureConnection();

        $sql = "DROP TABLE IF EXISTS `{$tableName}`;";
        return $this->connection->execute($sql);
    }

    /**
     * Get table columns (for TableSchema command)
     *
     * @param string $tableName Table name
     * @return array Array of column information
     */
    public function getTableColumns($tableName)
    {
        $this->ensureConnection();

        $sql = "SHOW COLUMNS FROM `{$tableName}`";
        $result = $this->connection->execute($sql);

        if (!$result) {
            return [];
        }

        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row;
        }

        return $columns;
    }

    /**
     * Get table indexes (for TableSchema command)
     *
     * @param string $tableName Table name
     * @return array Array of index information
     */
    public function getTableIndexes($tableName)
    {
        $this->ensureConnection();

        $sql = "SHOW INDEX FROM `{$tableName}`";
        $result = $this->connection->execute($sql);

        if (!$result) {
            return [];
        }

        $indexes = [];
        while ($row = $result->fetch_assoc()) {
            $indexes[] = $row;
        }

        return $indexes;
    }

    /**
     * Get the number of rows in a table
     *
     * @param string $tableName Name of table to count rows in
     * @return int Number of rows in table
     */
    public function getRowCount($tableName)
    {
        $this->ensureConnection();

        $sql = "SELECT COUNT(*) as count FROM `{$tableName}`";
        $result = $this->connection->execute($sql);

        if (!$result) {
            return 0;
        }

        $row = $result->fetch_assoc();
        return (int) $row['count'];
    }

    /**
     * Empty a table by deleting all rows
     *
     * Preserves table structure and indexes, but removes all data.
     * Uses DELETE instead of TRUNCATE to respect foreign keys and preserve auto-increment counter.
     *
     * @param string $tableName Name of table to empty
     * @return void
     */
    public function emptyTable($tableName)
    {
        $this->ensureConnection();

        $sql = "DELETE FROM `{$tableName}`";
        $this->connection->execute($sql);
    }

    /**
     * Display table schema in formatted output
     *
     * Shows column names, types, and constraints.
     *
     * @param string $tableName Name of table to display
     * @return void
     */
    public function displayTableSchema($tableName)
    {
        $this->ensureConnection();

        $sql = "SHOW FULL COLUMNS FROM `{$tableName}`";
        $result = $this->connection->execute($sql);

        if (!$result) {
            return;
        }

        echo "Columns:\n";
        while ($row = $result->fetch_assoc()) {
            $col = $row['Field'];
            $type = $row['Type'];
            $null = $row['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
            $key = $row['Key'] ? " ({$row['Key']})" : '';
            $extra = $row['Extra'] ? " {$row['Extra']}" : '';

            echo "  {$col}: {$type} {$null}{$key}{$extra}\n";
        }
    }

    /**
     * Get tables that reference a given table via foreign keys
     *
     * Returns an array of tables that have foreign key constraints
     * referencing the specified table.
     *
     * @param string $tableName Table name to check
     * @return array Associative array [table_name => [columns]]
     */
    public function getTablesReferencingTable($tableName)
    {
        $this->ensureConnection();

        $sql = "
            SELECT
                TABLE_NAME,
                COLUMN_NAME,
                CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ";

        // Use prepared statement
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param('s', $tableName);
        $stmt->execute();
        $result = $stmt->get_result();

        $references = [];
        while ($row = $result->fetch_assoc()) {
            $table = $row['TABLE_NAME'];
            if (!isset($references[$table])) {
                $references[$table] = [];
            }
            $references[$table][] = $row['COLUMN_NAME'];
        }

        return $references;
    }

    /**
     * Validate foreign key constraints before table creation
     *
     * Checks that all foreign key references:
     * 1. Point to existing tables
     * 2. Point to existing columns
     * 3. Have matching data types (exact match required)
     *
     * Throws descriptive exception if validation fails.
     *
     * @param array $schema Parsed model schema
     * @return void
     * @throws \Exception If foreign key validation fails
     */
    private function validateForeignKeys($schema)
    {
        // Get database connection
        $this->ensureConnection();

        //print_r($schema); exit();

        // Collect all foreign keys from schema
        $foreignKeys = [];
        foreach ($schema['columns'] as $columnName => $columnDef) {
            if (!empty($columnDef['foreign'])) {
                // Build full column type string (same as in createTableFromModel)
                $fullType = $columnDef['type'];
                if ($columnDef['length']) {
                    $fullType .= "({$columnDef['length']})";
                }
                if ($columnDef['unsigned']) {
                    $fullType .= " UNSIGNED";
                }

                $foreignKeys[] = [
                    'column' => $columnDef['name'],
                    'column_type' => $fullType,
                    'references' => $columnDef['foreign'],
                    'on_delete' => $columnDef['on_delete'] ?? 'RESTRICT',
                    'on_update' => $columnDef['on_update'] ?? 'RESTRICT',
                ];
            }
        }

        // No foreign keys? Nothing to validate
        if (empty($foreignKeys)) {
            return;
        }

        // Validate each foreign key
        foreach ($foreignKeys as $fk) {
            // Parse foreign key reference: "table(column)"
            if (!preg_match('/^(\w+)\((\w+)\)$/', $fk['references'], $matches)) {
                throw new \Exception(
                    "Invalid foreign key format for column '{$fk['column']}'.\n" .
                    "       Expected: tablename(columnname)\n" .
                    "       Got: {$fk['references']}"
                );
            }

            $refTable = $matches[1];
            $refColumn = $matches[2];

            // 1. Check if referenced table exists
            $tableCheck = $this->connection->execute("SHOW TABLES LIKE '{$refTable}'");
            if ($tableCheck->num_rows === 0) {
                throw new \Exception(
                    "Foreign Key Validation Failed!\n\n" .
                    "  Column: {$fk['column']}\n" .
                    "  References: {$refTable}({$refColumn})\n" .
                    "  Problem: Table '{$refTable}' does not exist\n\n" .
                    "  Fix: Create the '{$refTable}' table first, then retry.\n" .
                    "       Foreign keys must reference existing tables."
                );
            }

            // 2. Check if referenced column exists
            $columnCheck = $this->connection->execute(
                "SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_KEY
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = '{$refTable}'
                 AND COLUMN_NAME = '{$refColumn}'"
            );

            if ($columnCheck->num_rows === 0) {
                throw new \Exception(
                    "Foreign Key Validation Failed!\n\n" .
                    "  Column: {$fk['column']}\n" .
                    "  References: {$refTable}({$refColumn})\n" .
                    "  Problem: Column '{$refColumn}' does not exist in table '{$refTable}'\n\n" .
                    "  Fix: The referenced column must exist in the target table.\n" .
                    "       Check your @foreign annotation."
                );
            }

            $refColumnData = $columnCheck->fetch_assoc();

            // 3. Check if referenced column is indexed (required for foreign keys)
            if (empty($refColumnData['COLUMN_KEY'])) {
                throw new \Exception(
                    "Foreign Key Validation Failed!\n\n" .
                    "  Column: {$fk['column']}\n" .
                    "  References: {$refTable}({$refColumn})\n" .
                    "  Problem: Column '{$refTable}.{$refColumn}' is not indexed\n\n" .
                    "  Fix: Foreign keys can only reference indexed columns.\n" .
                    "       Add @index, @unique, or @primary to {$refTable}.{$refColumn}"
                );
            }

            // 4. Check if column types match EXACTLY
            $sourceType = strtoupper($fk['column_type']);
            $targetType = strtoupper($refColumnData['COLUMN_TYPE']);

            if ($sourceType !== $targetType) {
                // Provide helpful error with type comparison
                throw new \Exception(
                    "Foreign Key Validation Failed!\n\n" .
                    "  Column: {$fk['column']}\n" .
                    "  References: {$refTable}({$refColumn})\n" .
                    "  Problem: Data type mismatch\n\n" .
                    "  Source column type:     {$sourceType}\n" .
                    "  Referenced column type: {$targetType}\n\n" .
                    "  Fix: Foreign key columns must have EXACTLY matching types.\n" .
                    "       Change {$schema['table']}.{$fk['column']} to match {$refTable}.{$refColumn},\n" .
                    "       or update {$refTable}.{$refColumn} to match {$schema['table']}.{$fk['column']}."
                );
            }
        }

        // All foreign keys validated successfully
    }

    /**
     * Execute raw SQL query
     *
     * @param string $sql SQL query to execute
     * @return mixed Query result
     */
    public function rawQuery($sql)
    {
        $this->ensureConnection();
        return $this->connection->execute($sql);
    }

    /**
     * Get existing composite indexes from database
     *
     * Returns only non-unique indexes with multiple columns (composite).
     * Single-column indexes and unique indexes are filtered out.
     *
     * @param string $tableName Table name
     * @return array Composite indexes ['index_name' => ['columns' => ['col1', 'col2']]]
     */
    private function getExistingCompositeIndexes($tableName)
    {
        $this->ensureConnection();

        $sql = "SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$tableName}'
                AND INDEX_NAME != 'PRIMARY'
                ORDER BY INDEX_NAME, SEQ_IN_INDEX";

        $result = $this->connection->execute($sql);

        if (!$result) {
            return [];
        }

        $indexes = [];
        while ($row = $result->fetch_assoc()) {
            $indexName = $row['INDEX_NAME'];
            if (!isset($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'columns' => [],
                    'unique' => $row['NON_UNIQUE'] == 0,
                ];
            }
            $indexes[$indexName]['columns'][] = $row['COLUMN_NAME'];
        }

        // Filter: keep only composite (multi-column) AND non-unique indexes
        return array_filter($indexes, function($idx) {
            return count($idx['columns']) > 1 && !$idx['unique'];
        });
    }

    /**
     * Get existing composite unique indexes from database
     *
     * Returns only unique indexes with multiple columns (composite unique).
     * Single-column indexes and non-unique indexes are filtered out.
     *
     * @param string $tableName Table name
     * @return array Composite unique indexes ['index_name' => ['columns' => ['col1', 'col2']]]
     */
    private function getExistingCompositeUniqueIndexes($tableName)
    {
        $this->ensureConnection();

        $sql = "SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$tableName}'
                AND INDEX_NAME != 'PRIMARY'
                ORDER BY INDEX_NAME, SEQ_IN_INDEX";

        $result = $this->connection->execute($sql);

        if (!$result) {
            return [];
        }

        $indexes = [];
        while ($row = $result->fetch_assoc()) {
            $indexName = $row['INDEX_NAME'];
            if (!isset($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'columns' => [],
                    'unique' => $row['NON_UNIQUE'] == 0,
                ];
            }
            $indexes[$indexName]['columns'][] = $row['COLUMN_NAME'];
        }

        // Filter: keep only composite (multi-column) AND unique indexes
        return array_filter($indexes, function($idx) {
            return count($idx['columns']) > 1 && $idx['unique'];
        });
    }

    /**
     * Get existing simple (single-column) indexes from database
     *
     * Returns only indexes with one column (simple/single-column).
     * Composite indexes and PRIMARY key are filtered out.
     *
     * @param string $tableName Table name
     * @return array Simple indexes ['index_name' => ['column' => 'col_name', 'unique' => bool]]
     */
    private function getExistingSimpleIndexes($tableName)
    {
        $this->ensureConnection();

        $sql = "SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$tableName}'
                AND INDEX_NAME != 'PRIMARY'
                ORDER BY INDEX_NAME, SEQ_IN_INDEX";

        $result = $this->connection->execute($sql);

        if (!$result) {
            return [];
        }

        $allIndexes = [];
        while ($row = $result->fetch_assoc()) {
            $indexName = $row['INDEX_NAME'];
            if (!isset($allIndexes[$indexName])) {
                $allIndexes[$indexName] = [
                    'columns' => [],
                    'unique' => $row['NON_UNIQUE'] == 0,
                ];
            }
            $allIndexes[$indexName]['columns'][] = $row['COLUMN_NAME'];
        }

        // Filter to only single-column indexes (both unique and non-unique)
        $simpleIndexes = [];
        foreach ($allIndexes as $indexName => $indexInfo) {
            if (count($indexInfo['columns']) === 1) {
                $simpleIndexes[$indexName] = [
                    'column' => $indexInfo['columns'][0],
                    'unique' => $indexInfo['unique'],
                ];
            }
        }

        return $simpleIndexes;
    }
}
