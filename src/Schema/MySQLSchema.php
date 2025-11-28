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
    public function updateTableFromModel($modelClass)
    {
        // Parse model class
        $schema = $this->parser->parseModelClass($modelClass);

        // Get existing table columns
        $existingColumns = $this->getExistingColumns($schema['table']);

        // Generate ALTER TABLE statements
        $statements = $this->generateAlterTableSQL($schema, $existingColumns);

        if (empty($statements)) {
            return true; // No changes needed
        }

        // Get database connection
        $this->ensureConnection();

        // Execute each ALTER statement
        foreach ($statements as $sql) {
            $result = $this->connection->execute($sql);
            if (!$result) {
                throw new \Exception("Failed to update table '{$schema['table']}': " . $this->connection->lastError());
            }
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
     * @return array Array of ALTER TABLE statements
     */
    public function generateAlterTableSQL($schema, $existingColumns)
    {
        $tableName = $schema['table'];
        $statements = [];

        $newColumns = [];
        foreach ($schema['columns'] as $column) {
            $newColumns[$column['name']] = $column;
        }

        // Handle drops
        foreach ($schema['columns'] as $column) {
            if ($column['drop']) {
                $statements[] = "ALTER TABLE `{$tableName}` DROP COLUMN `{$column['name']}`;";
            }
        }

        // Handle renames
        foreach ($schema['columns'] as $column) {
            if ($column['rename']) {
                $oldName = $column['rename'];
                $newName = $column['name'];
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
                // Column exists - check if modified
                // For now, always regenerate (smart diffing can be added later)
                $columnDef = $this->getColumnDefinition($column);
                $statements[] = "ALTER TABLE `{$tableName}` MODIFY COLUMN `{$name}` {$columnDef};";
            }
        }

        return $statements;
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
}
