<?php namespace Roline\Commands\Table;

/**
 * TableExport Command
 *
 * Simple standalone command to export table data to SQL or CSV.
 * Works with table names only - no model classes required.
 *
 * Usage:
 *   php roline table:export <tablename> [filename]
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright 2015 - 2050 Geoffrey Okongo
 * @category Roline
 * @package Roline\Commands\Table
 * @link https://github.com/glivers/roline
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 1.0.0
 */

use Roline\Output;
use Roline\Schema\MySQLSchema;
use Rackage\Model;

class TableExport extends TableCommand
{
    public function description()
    {
        return 'Export table data to SQL or CSV';
    }

    public function usage()
    {
        return '<tablename|required> <file|optional>';
    }

    public function help()
    {
        parent::help();

        Output::info('Arguments:');
        Output::line('  <tablename|required>  Database table name');
        Output::line('  <file|optional>       Output filename (auto-generates if not provided)');
        Output::line();

        Output::info('Examples:');
        Output::line('  php roline table:export users');
        Output::line('  php roline table:export posts posts_backup.sql');
        Output::line('  php roline table:export products products.csv');
        Output::line();

        Output::info('Output Formats:');
        Output::line('  .sql  - SQL INSERT statements');
        Output::line('  .csv  - Comma-separated values');
        Output::line('  Auto-detects format from file extension (defaults to .sql)');
        Output::line();

        Output::info('Note:');
        Output::line('  For model-based table export, use:');
        Output::line('  php roline model:export-table <Model>');
        Output::line();
    }

    public function execute($arguments)
    {
        if (empty($arguments[0])) {
            $this->error('Table name is required!');
            $this->line();
            $this->info('Usage: php roline table:export <tablename> [file]');
            exit(1);
        }

        $tableName = $arguments[0];

        $schema = new MySQLSchema();

        // Validate table exists
        if (!$schema->tableExists($tableName)) {
            $this->error("Table '{$tableName}' does not exist!");
            exit(1);
        }

        // Determine output filename
        $filename = $arguments[1] ?? null;

        if ($filename === null) {
            $timestamp = date('Y-m-d_His');
            $filename = "{$tableName}_{$timestamp}.sql";
        }

        // Detect format
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $format = strtolower($extension) === 'csv' ? 'csv' : 'sql';

        // Ensure exports directory exists
        $exportsDir = getcwd() . '/application/storage/exports';
        if (!is_dir($exportsDir)) {
            mkdir($exportsDir, 0755, true);
        }

        $filepath = $exportsDir . '/' . $filename;

        // Check if file exists
        if (file_exists($filepath)) {
            $overwrite = $this->confirm("File '{$filename}' already exists. Overwrite?");
            if (!$overwrite) {
                $this->info("Export cancelled.");
                exit(0);
            }
        }

        // Execute export
        try {
            $this->line();
            $this->info("Exporting table '{$tableName}'...");
            $this->line();

            if ($format === 'csv') {
                $this->exportToCSV($tableName, $filepath);
            } else {
                $this->exportToSQL($tableName, $filepath);
            }

            $this->line();
            $this->success("Export complete!");
            $this->line();
            $this->info("Format: " . strtoupper($format));
            $this->info("Location: {$filepath}");
            $this->line();

        } catch (\Exception $e) {
            $this->line();
            $this->error("Export failed: " . $e->getMessage());
            exit(1);
        }
    }

    private function exportToSQL($tableName, $filepath)
    {
        $sql = "SELECT * FROM `{$tableName}`";
        $result = Model::rawQuery($sql);

        if (!$result || $result->num_rows === 0) {
            file_put_contents($filepath, "-- No data in table '{$tableName}'\n");
            return;
        }

        $columns = [];
        $fields = $result->fetch_fields();
        foreach ($fields as $field) {
            $columns[] = $field->name;
        }

        $output = "-- Table export: {$tableName}\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $output .= "-- Total rows: {$result->num_rows}\n\n";

        while ($row = $result->fetch_assoc()) {
            $values = [];

            foreach ($columns as $column) {
                $value = $row[$column];

                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $escaped = addslashes($value);
                    $values[] = "'{$escaped}'";
                }
            }

            $columnList = '`' . implode('`, `', $columns) . '`';
            $valueList = implode(', ', $values);

            $output .= "INSERT INTO `{$tableName}` ({$columnList}) VALUES ({$valueList});\n";
        }

        file_put_contents($filepath, $output);
    }

    private function exportToCSV($tableName, $filepath)
    {
        $sql = "SELECT * FROM `{$tableName}`";
        $result = Model::rawQuery($sql);

        if (!$result) {
            throw new \Exception("Failed to query table");
        }

        $fp = fopen($filepath, 'w');
        if (!$fp) {
            throw new \Exception("Failed to open file for writing");
        }

        if ($result->num_rows > 0) {
            $columns = [];
            $fields = $result->fetch_fields();
            foreach ($fields as $field) {
                $columns[] = $field->name;
            }

            fputcsv($fp, $columns);

            while ($row = $result->fetch_assoc()) {
                fputcsv($fp, array_values($row));
            }
        }

        fclose($fp);
    }
}
