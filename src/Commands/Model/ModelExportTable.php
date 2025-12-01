<?php namespace Roline\Commands\Model;

/**
 * ModelExportTable Command
 *
 * Exports complete table data to SQL or CSV format. Queries all rows from the database
 * table associated with the model and generates formatted output files suitable for
 * backups, data migration, or analysis in external tools (Excel, database tools, etc.).
 *
 * Supported Export Formats:
 *   - SQL Format (.sql):
 *     * Complete INSERT statements for each row
 *     * Includes column names and properly escaped values
 *     * NULL handling with SQL NULL keyword
 *     * File header with table name, timestamp, row count
 *     * Ready for direct import into MySQL
 *
 *   - CSV Format (.csv):
 *     * Comma-separated values
 *     * Column headers in first row
 *     * Standard CSV escaping for quotes and commas
 *     * Compatible with Excel, Google Sheets, etc.
 *     * Smaller file size than SQL format
 *
 * File Generation:
 *   - Auto-generates filename with timestamp if not specified
 *   - Format: {tablename}_{YYYY-MM-DD_HHmmss}.sql
 *   - Saves to application/storage/exports/ directory
 *   - Creates exports directory if doesn't exist
 *   - Checks for existing files and prompts before overwrite
 *
 * Use Cases:
 *   - Database backups before destructive operations
 *   - Data migration between environments
 *   - Exporting data for analysis in Excel/spreadsheets
 *   - Sharing sample data with team members
 *   - Creating test fixtures from production data
 *
 * Typical Workflow:
 *   1. Run command with model name and optional filename
 *   2. Command validates model and table exist
 *   3. Determines export format from file extension
 *   4. Creates exports directory if needed
 *   5. Queries all table data
 *   6. Generates formatted output
 *   7. Writes to file in exports directory
 *
 * Examples:
 *   php roline model:export-table User
 *   - Exports to users_2025-01-15_143022.sql
 *
 *   php roline model:export-table Post posts_backup.sql
 *   - Exports as SQL to posts_backup.sql
 *
 *   php roline model:export-table Product products.csv
 *   - Exports as CSV to products.csv
 *
 * Usage:
 *   php roline model:export-table <Model> [filename]
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright 2015 - 2050 Geoffrey Okongo
 * @category Roline
 * @package Roline\Commands\Model
 * @link https://github.com/glivers/roline
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 1.0.0
 */

use Roline\Output;
use Roline\Schema\MySQLSchema;
use Rackage\Model;

class ModelExportTable extends ModelCommand
{
    /**
     * Get command description for listing
     *
     * @return string Brief command description
     */
    public function description()
    {
        return 'Export table data to SQL or CSV';
    }

    /**
     * Get command usage syntax
     *
     * @return string Usage syntax showing required model and optional filename
     */
    public function usage()
    {
        return '<Model|required> <file|optional>';
    }

    /**
     * Display detailed help information
     *
     * Shows arguments, export format options (SQL vs CSV), auto-generated filenames,
     * output location, and usage examples for different scenarios.
     *
     * @return void
     */
    public function help()
    {
        parent::help();

        Output::info('Arguments:');
        Output::line('  <Model|required>  Model class name (without "Model" suffix)');
        Output::line('  <file|optional>   Output filename (auto-generates if not provided)');
        Output::line();

        Output::info('Examples:');
        Output::line('  php roline model:export-table User');
        Output::line('  php roline model:export-table Post posts_backup.sql');
        Output::line('  php roline model:export-table Product products.csv');
        Output::line();

        Output::info('Output Formats:');
        Output::line('  .sql  - SQL INSERT statements');
        Output::line('  .csv  - Comma-separated values');
        Output::line('  Auto-detects format from file extension (defaults to .sql)');
        Output::line();

        Output::info('Output Location:');
        Output::line('  Files are saved to: application/storage/exports/');
        Output::line();
    }

    /**
     * Execute table export to SQL or CSV
     *
     * Exports all table data to specified or auto-generated filename. Validates model
     * and table exist, determines format from file extension, creates exports directory
     * if needed, queries all rows, and writes formatted output. Prompts before overwriting
     * existing files.
     *
     * @param array $arguments Command arguments (model at index 0, filename at index 1 optional)
     * @return void Exits with status 0 on cancel, 1 on failure
     */
    public function execute($arguments)
    {
        // Validate model name argument is provided and normalize (ucfirst, remove 'Model' suffix)
        if (empty($arguments[0])) {
            $this->error('Model name is required!');
            $this->line();
            $this->info('Usage: php roline model:export-table <Model> [file]');
            $this->line();
            $this->info('Example: php roline model:export-table User');
            exit(1);
        }

        // Normalize model name (ucfirst + remove 'Model' suffix)
        $modelName = $this->validateName($arguments[0]);
        $modelClass = "Models\\{$modelName}Model";

        // Validate model class exists
        if (!class_exists($modelClass)) {
            $this->error("Model class not found: {$modelClass}");
            $this->line();
            $this->info('Create the model first: php roline model:create ' . $modelName);
            exit(1);
        }

        // Extract table name from model's protected static $table property
        try {
            // Use reflection to access protected static property
            $reflection = new \ReflectionClass($modelClass);
            $tableProperty = $reflection->getProperty('table');
            $tableProperty->setAccessible(true);
            $tableName = $tableProperty->getValue();

            // Validate table name is defined
            if (empty($tableName)) {
                $this->error('Model does not have a table name defined!');
                exit(1);
            }
        } catch (\Exception $e) {
            // Reflection failed
            $this->error('Error reading model: ' . $e->getMessage());
            exit(1);
        }

        // Validate table exists in database
        $schema = new MySQLSchema();
        if (!$schema->tableExists($tableName)) {
            $this->error("Table '{$tableName}' does not exist!");
            $this->line();
            $this->info("Create it first: php roline model:create-table {$modelName}");
            exit(1);
        }

        // Determine output filename (auto-generate if not provided)
        $filename = $arguments[1] ?? null;

        if ($filename === null) {
            // Generate filename with timestamp: tablename_YYYY-MM-DD_HHmmss.sql
            $timestamp = date('Y-m-d_His');
            $filename = "{$tableName}_{$timestamp}.sql";
        }

        // Detect export format from file extension (.csv or .sql)
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $format = strtolower($extension) === 'csv' ? 'csv' : 'sql';

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

        // Execute export based on detected format
        try {
            $this->line();
            $this->info("Exporting table '{$tableName}'...");
            $this->line();

            // Delegate to format-specific export method
            if ($format === 'csv') {
                $this->exportToCSV($tableName, $filepath);
            } else {
                $this->exportToSQL($tableName, $filepath);
            }

            // Export successful - display summary
            $this->line();
            $this->success("Export complete!");
            $this->line();
            $this->info("Format: " . strtoupper($format));
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
     * Export table to SQL format
     *
     * Generates SQL file with INSERT statements for all table rows. Includes file
     * header with table name, timestamp, and row count. Properly escapes values and
     * handles NULL values with SQL NULL keyword.
     *
     * @param string $tableName Table name to export
     * @param string $filepath Output file path
     * @return void
     * @throws \Exception If query or file write fails
     */
    private function exportToSQL($tableName, $filepath)
    {
        // Query all rows from table
        $sql = "SELECT * FROM `{$tableName}`";
        $result = Model::rawQuery($sql);

        // Handle empty table
        if (!$result || $result->num_rows === 0) {
            file_put_contents($filepath, "-- No data in table '{$tableName}'\n");
            return;
        }

        // Extract column names from result metadata
        $columns = [];
        $fields = $result->fetch_fields();
        foreach ($fields as $field) {
            $columns[] = $field->name;
        }

        // Build file header with metadata
        $output = "-- Table export: {$tableName}\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $output .= "-- Total rows: {$result->num_rows}\n\n";

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

        // Write SQL file to disk
        file_put_contents($filepath, $output);
    }

    /**
     * Export table to CSV format
     *
     * Generates CSV file with column headers in first row and data in subsequent rows.
     * Uses PHP's native fputcsv() for proper CSV escaping of quotes and commas.
     * Compatible with Excel, Google Sheets, and other spreadsheet tools.
     *
     * @param string $tableName Table name to export
     * @param string $filepath Output file path
     * @return void
     * @throws \Exception If query or file write fails
     */
    private function exportToCSV($tableName, $filepath)
    {
        // Query all rows from table
        $sql = "SELECT * FROM `{$tableName}`";
        $result = Model::rawQuery($sql);

        // Validate query succeeded
        if (!$result) {
            throw new \Exception("Failed to query table");
        }

        // Open file for writing
        $fp = fopen($filepath, 'w');
        if (!$fp) {
            throw new \Exception("Failed to open file for writing");
        }

        // Write CSV content if table has data
        if ($result->num_rows > 0) {
            // Extract column names from result metadata
            $columns = [];
            $fields = $result->fetch_fields();
            foreach ($fields as $field) {
                $columns[] = $field->name;
            }

            // Write column headers as first row
            fputcsv($fp, $columns);

            // Write each data row (fputcsv handles escaping)
            while ($row = $result->fetch_assoc()) {
                fputcsv($fp, array_values($row));
            }
        }

        // Close file handle
        fclose($fp);
    }
}
