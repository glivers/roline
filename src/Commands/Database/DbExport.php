<?php namespace Roline\Commands\Database;

/**
 * DbExport Command
 *
 * Exports entire database to SQL file including both schema (CREATE TABLE statements)
 * and data (INSERT statements) for all tables. Generates complete database dump suitable
 * for backups, migration, version control, or restoration on different environments.
 *
 * What Gets Exported:
 *   - File Header:
 *     * Database name
 *     * Export timestamp
 *     * Total table count
 *     * Foreign key check disabling for safe import
 *
 *   - For Each Table:
 *     * DROP TABLE IF EXISTS statement (safe re-import)
 *     * CREATE TABLE statement with:
 *       - All column definitions
 *       - Data types and constraints
 *       - Primary keys
 *       - Engine and charset specifications
 *     * INSERT statements for all rows
 *     * Row count comment
 *
 * File Generation:
 *   - Auto-generates filename with timestamp if not specified
 *   - Format: {database_name}_backup_{YYYY-MM-DD_HHmmss}.sql
 *   - Saves to application/storage/exports/ directory
 *   - Creates exports directory if doesn't exist
 *   - Prompts before overwriting existing files
 *
 * SQL Format Features:
 *   - SET FOREIGN_KEY_CHECKS=0 at start (allows safe table recreation)
 *   - DROP TABLE IF EXISTS for each table (idempotent import)
 *   - Properly escaped values with addslashes()
 *   - NULL handling with SQL NULL keyword
 *   - CURRENT_TIMESTAMP preservation for default values
 *   - SET FOREIGN_KEY_CHECKS=1 at end (restore safety)
 *
 * Use Cases:
 *   - Database backups before major changes
 *   - Migrating data between environments (dev → staging → production)
 *   - Version controlling database state
 *   - Sharing database snapshots with team
 *   - Creating test fixtures from real data
 *   - Disaster recovery preparation
 *
 * Distinction from table:export:
 *   - db:export     - Exports ALL tables in database (complete dump)
 *   - table:export  - Exports single table specified by Model (table-specific)
 *
 * Important Notes:
 *   - Export includes ALL data (can be large for populated databases)
 *   - Does NOT include database creation statement
 *   - Import requires database to exist already
 *   - Exports are plain SQL files (human-readable and editable)
 *   - Can be imported with: mysql database_name < export_file.sql
 *
 * Typical Workflow:
 *   1. Developer runs: php roline db:export
 *   2. Command auto-generates filename with timestamp
 *   3. Exports all tables with structure and data
 *   4. Saves to application/storage/exports/
 *   5. Developer can version control or transfer file
 *   6. Import on other environment with standard mysql command
 *
 * Example Output:
 *   Exporting database: myapp_db
 *
 *   Tables to export: 5
 *
 *     → Exporting users...
 *     → Exporting posts...
 *     → Exporting comments...
 *     → Exporting categories...
 *     → Exporting tags...
 *
 *   Database exported successfully!
 *
 *   Location: /path/to/application/storage/exports/myapp_db_backup_2025-01-27_143022.sql
 *
 * Usage:
 *   php roline db:export                    (auto-generate filename)
 *   php roline db:export mybackup.sql       (custom filename)
 *   php roline db:export prod_snapshot.sql  (descriptive name)
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright 2015 - 2050 Geoffrey Okongo
 * @category Roline
 * @package Roline\Commands\Database
 * @link https://github.com/glivers/roline
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 1.0.0
 */

use Roline\Output;
use Roline\Utils\SchemaReader;
use Rackage\Model;
use Rackage\Registry;

class DbExport extends DatabaseCommand
{
    /**
     * Get command description for listing
     *
     * @return string Brief command description
     */
    public function description()
    {
        return 'Export entire database';
    }

    /**
     * Get command usage syntax
     *
     * @return string Usage syntax showing optional filename parameter
     */
    public function usage()
    {
        return '<file|optional>';
    }

    /**
     * Display detailed help information
     *
     * Shows arguments, export format details (CREATE TABLE + INSERT), examples with
     * auto-generated vs custom filenames, and output location.
     *
     * @return void
     */
    public function help()
    {
        parent::help();

        Output::info('Arguments:');
        Output::line('  <file|optional>  Output filename (auto-generates if not provided)');
        Output::line();

        Output::info('Examples:');
        Output::line('  php roline db:export');
        Output::line('  php roline db:export database_backup.sql');
        Output::line();

        Output::info('What it exports:');
        Output::line('  - CREATE TABLE statements for all tables');
        Output::line('  - INSERT statements for all data');
        Output::line('  - Excludes migrations table by default');
        Output::line();

        Output::info('Output Location:');
        Output::line('  Files are saved to: application/storage/exports/');
        Output::line();
    }

    /**
     * Execute database export to SQL file
     *
     * Exports all tables with structure (CREATE TABLE) and data (INSERT) to SQL file.
     * Auto-generates timestamped filename if not provided, creates exports directory if
     * needed, prompts before overwriting existing files, and generates complete SQL dump
     * with foreign key handling for safe import.
     *
     * @param array $arguments Command arguments (filename at index 0, optional)
     * @return void Exits with status 0 on cancel, 1 on failure
     */
    public function execute($arguments)
    {
        try {
            // Get database name from configuration
            $dbConfig = Registry::database();
            $driver = $dbConfig['default'] ?? 'mysql';
            $databaseName = $dbConfig[$driver]['database'] ?? 'database';

            // Determine output filename (auto-generate if not provided)
            $filename = $arguments[0] ?? null;

            if ($filename === null) {
                // Generate filename with timestamp: database_backup_YYYY-MM-DD_HHmmss.sql
                $timestamp = date('Y-m-d_His');
                $filename = "{$databaseName}_backup_{$timestamp}.sql";
            }

            // Ensure exports directory exists
            $exportsDir = getcwd() . '/application/storage/exports';
            if (!is_dir($exportsDir)) {
                mkdir($exportsDir, 0755, true);
            }

            // Build full output file path
            $filepath = $exportsDir . '/' . $filename;

            // Check if file already exists and prompt for overwrite
            if (file_exists($filepath)) {
                $overwrite = $this->confirm("File '{$filename}' already exists. Overwrite?");
                if (!$overwrite) {
                    // User declined overwrite
                    $this->info("Export cancelled.");
                    exit(0);
                }
            }

            // Display export progress header
            $this->line();
            $this->info("Exporting database: {$databaseName}");
            $this->line();

            // Get all tables from database
            $schemaReader = new SchemaReader();
            $tables = $schemaReader->getTables();

            // Check if database has any tables
            if (empty($tables)) {
                $this->info('No tables found in database.');
                $this->line();
                exit(0);
            }

            // Display table count
            $this->info('Tables to export: ' . count($tables));
            $this->line();

            // Build SQL file header with metadata
            $output = "-- Database Export\n";
            $output .= "-- Database: {$databaseName}\n";
            $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $output .= "-- Tables: " . count($tables) . "\n\n";

            // Disable foreign key checks for safe table recreation
            $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            // Export each table (structure and data)
            foreach ($tables as $tableName) {
                $this->info("  → Exporting {$tableName}...");

                // Generate SQL for this table (CREATE + INSERT statements)
                $output .= $this->exportTable($tableName, $schemaReader);
                $output .= "\n";
            }

            // Re-enable foreign key checks
            $output .= "SET FOREIGN_KEY_CHECKS=1;\n";

            // Write complete SQL dump to file
            file_put_contents($filepath, $output);

            // Export successful - display summary
            $this->line();
            $this->success("Database exported successfully!");
            $this->line();
            $this->info("Location: {$filepath}");
            $this->line();

        } catch (\Exception $e) {
            // Export failed (query error, file write error, etc.)
            $this->line();
            $this->error("Export failed!");
            $this->line();
            $this->error("Error: " . $e->getMessage());
            $this->line();
            exit(1);
        }
    }

    /**
     * Export a single table (structure and data)
     *
     * Generates complete SQL for one table including DROP TABLE, CREATE TABLE with
     * full schema definition, and INSERT statements for all rows. Returns formatted
     * SQL string ready for file output.
     *
     * @param string $tableName Table name to export
     * @param SchemaReader $schemaReader Schema reader instance for querying
     * @return string Complete SQL for table (DROP + CREATE + INSERT)
     */
    private function exportTable($tableName, $schemaReader)
    {
        // Build table header comment
        $output = "--\n";
        $output .= "-- Table: {$tableName}\n";
        $output .= "--\n\n";

        // Get table schema from SchemaReader
        $schema = $schemaReader->getTableSchema($tableName);

        // DROP TABLE statement for idempotent import
        $output .= "DROP TABLE IF EXISTS `{$tableName}`;\n\n";

        // CREATE TABLE statement with full schema
        $output .= $this->generateCreateTableSQL($tableName, $schema);
        $output .= "\n\n";

        // INSERT statements for all table data
        $output .= $this->generateInsertStatementsSQL($tableName);

        return $output;
    }

    /**
     * Generate CREATE TABLE SQL
     *
     * Builds complete CREATE TABLE statement from schema definition including all
     * column definitions with types, constraints, defaults, primary key, engine,
     * and charset specifications.
     *
     * @param string $tableName Table name
     * @param array $schema Table schema array from SchemaReader
     * @return string CREATE TABLE SQL statement
     */
    private function generateCreateTableSQL($tableName, $schema)
    {
        // Extract schema components
        $columns = $schema['columns'] ?? [];
        $primaryKey = $schema['primary_key'] ?? null;
        $engine = $schema['engine'] ?? 'InnoDB';
        $charset = $schema['charset'] ?? 'utf8mb4';

        // Start CREATE TABLE statement
        $sql = "CREATE TABLE `{$tableName}` (\n";

        // Build column definitions array
        $columnDefs = [];
        foreach ($columns as $columnName => $columnDef) {
            // Start with column name and type
            $def = "  `{$columnName}` {$columnDef['type']}";

            // Add NOT NULL constraint if applicable
            if (!$columnDef['nullable']) {
                $def .= " NOT NULL";
            }

            // Add DEFAULT value if set
            if ($columnDef['default'] !== null) {
                $default = $columnDef['default'];

                // Special keywords (CURRENT_TIMESTAMP, NULL) don't get quoted
                if (strtoupper($default) === 'CURRENT_TIMESTAMP' || strtoupper($default) === 'NULL') {
                    $def .= " DEFAULT {$default}";
                } else {
                    // Regular defaults get quoted
                    $def .= " DEFAULT '{$default}'";
                }
            }

            // Add extra attributes (AUTO_INCREMENT, etc.)
            if (!empty($columnDef['extra'])) {
                $def .= " {$columnDef['extra']}";
            }

            $columnDefs[] = $def;
        }

        // Join column definitions with commas
        $sql .= implode(",\n", $columnDefs);

        // Add PRIMARY KEY constraint if exists
        if ($primaryKey) {
            // Backtick-quote each primary key column
            $pkColumns = '`' . implode('`, `', $primaryKey) . '`';
            $sql .= ",\n  PRIMARY KEY ({$pkColumns})";
        }

        // Close CREATE TABLE with engine and charset
        $sql .= "\n) ENGINE={$engine} DEFAULT CHARSET={$charset};";

        return $sql;
    }

    /**
     * Generate INSERT statements for table data
     *
     * Queries all rows from table and generates INSERT statement for each row with
     * properly escaped values and NULL handling. Returns formatted SQL string with
     * row count comment.
     *
     * @param string $tableName Table name
     * @return string INSERT SQL statements for all rows
     */
    private function generateInsertStatementsSQL($tableName)
    {
        // Query all rows from table
        $sql = "SELECT * FROM `{$tableName}`";
        $result = Model::rawQuery($sql);

        // Handle empty table
        if (!$result || $result->num_rows === 0) {
            return "-- No data in table\n";
        }

        // Extract column names from result metadata
        $columns = [];
        $fields = $result->fetch_fields();
        foreach ($fields as $field) {
            $columns[] = $field->name;
        }

        // Build data header comment
        $output = "-- Data for table {$tableName}\n";
        $output .= "-- Rows: {$result->num_rows}\n\n";

        // Generate INSERT statement for each row
        while ($row = $result->fetch_assoc()) {
            $values = [];

            // Process each column value
            foreach ($columns as $column) {
                $value = $row[$column];

                // Handle NULL values with SQL NULL keyword
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    // Escape value and wrap in quotes
                    $escaped = addslashes($value);
                    $values[] = "'{$escaped}'";
                }
            }

            // Build INSERT statement with column list and values
            $columnList = '`' . implode('`, `', $columns) . '`';
            $valueList = implode(', ', $values);

            $output .= "INSERT INTO `{$tableName}` ({$columnList}) VALUES ({$valueList});\n";
        }

        return $output;
    }
}
