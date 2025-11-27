<?php namespace Roline\Commands\Table;

/**
 * TableUpdate Command
 *
 * Safely updates existing database tables by comparing model @column annotations
 * with current database structure and generating appropriate ALTER TABLE statements.
 * This is the SAFE alternative to table:create for modifying existing tables without
 * data loss.
 *
 * What Gets Updated:
 *   - New columns added to database from model
 *   - Columns marked with @drop removed from database
 *   - Columns marked with @rename renamed in database
 *   - Column types/constraints modified to match annotations
 *
 * Special Annotations for Safe Migrations:
 *   - @drop - Marks column for deletion (prevents accidental data loss)
 *   - @rename old_name - Renames column from old_name to property name
 *
 * Safety Features:
 *   - Non-destructive - Only adds/modifies, never drops without @drop annotation
 *   - Validates table exists before attempting update
 *   - Compares model schema with database schema before changes
 *   - Preserves existing data during column modifications
 *   - Generates minimal ALTER TABLE statements
 *
 * Common Use Cases:
 *   - Adding new columns to existing tables
 *   - Modifying column types or constraints
 *   - Safely renaming columns with @rename annotation
 *   - Removing obsolete columns with @drop annotation
 *
 * Example Model Annotations:
 * ```php
 * // Add new column
 * /** @column @varchar(100) *\/
 * protected $new_field;
 *
 * // Rename column
 * /** @column @rename old_email @varchar(255) *\/
 * protected $email;
 *
 * // Drop column
 * /** @column @drop *\/
 * protected $obsolete_field;
 * ```
 *
 * Workflow:
 *   1. Modify model @column annotations
 *   2. Run table:update command
 *   3. Schema compares model vs database
 *   4. ALTER TABLE statements executed
 *   5. Table structure updated, data preserved
 *
 * Usage:
 *   php roline table:update User
 *   php roline table:update Post
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

class TableUpdate extends TableCommand
{
    /**
     * Get command description for listing
     *
     * @return string Brief command description
     */
    public function description()
    {
        return 'Update table from Model @column changes';
    }

    /**
     * Get command usage syntax
     *
     * @return string Usage syntax showing required model name
     */
    public function usage()
    {
        return '<Model|required>';
    }

    /**
     * Display detailed help information
     *
     * Shows how to use the command, what operations it performs, and examples
     * of special annotations (@drop, @rename) for safe schema migrations.
     *
     * @return void
     */
    public function help()
    {
        parent::help();

        Output::info('Arguments:');
        Output::line('  <Model|required>  Model class name (without "Model" suffix)');
        Output::line();

        Output::info('What this does:');
        Output::line('  - Compares Model @column annotations with database');
        Output::line('  - Generates ALTER TABLE statements for changes');
        Output::line('  - Handles: new columns, dropped columns (@drop), renamed columns (@rename)');
        Output::line();

        Output::info('Examples:');
        Output::line('  php roline table:update User');
        Output::line('  php roline table:update Post');
        Output::line();

        Output::info('Safe Migrations:');
        Output::line('  Use @drop to mark columns for deletion:');
        Output::line('    /**');
        Output::line('     * @column');
        Output::line('     * @drop');
        Output::line('     */');
        Output::line('    protected $old_column;');
        Output::line();
        Output::line('  Use @rename to rename columns:');
        Output::line('    /**');
        Output::line('     * @column');
        Output::line('     * @rename old_name');
        Output::line('     * @varchar 255');
        Output::line('     */');
        Output::line('    protected $new_name;');
        Output::line();
    }

    /**
     * Execute table update from model annotations
     *
     * Compares model @column annotations with current database structure and
     * generates ALTER TABLE statements to synchronize. Validates model and table
     * exist, then delegates to MySQLSchema for schema comparison and DDL execution.
     * This is non-destructive - data is preserved during updates.
     *
     * @param array $arguments Command arguments (model name at index 0)
     * @return void Exits with status 1 on failure
     */
    public function execute($arguments)
    {
        // Validate model name argument is provided
        if (empty($arguments[0])) {
            $this->error('Model name is required!');
            $this->line();
            $this->info('Usage: php roline table:update <Model>');
            $this->line();
            $this->info('Example: php roline table:update User');
            exit(1);
        }

        // Build fully-qualified model class name
        $modelName = $arguments[0];
        $modelClass = "Models\\{$modelName}Model";

        // Check if model class exists via autoloader
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

        // Execute table update via MySQLSchema
        try {
            // Create schema instance for database operations
            $schema = new MySQLSchema();

            // Validate table exists in database before attempting update
            if (!$schema->tableExists($tableName)) {
                $this->error("Table '{$tableName}' does not exist!");
                $this->line();
                $this->info("Create it first: php roline table:create {$modelName}");
                exit(1);
            }

            // Display update summary
            $this->line();
            $this->info("Updating table '{$tableName}' from Model: {$modelClass}");
            $this->line();

            // Compare model schema with database schema and execute ALTER TABLE statements
            $schema->updateTableFromModel($modelClass);

            // Update successful
            $this->line();
            $this->success("Table '{$tableName}' updated successfully!");
            $this->line();

        } catch (\Exception $e) {
            // Update failed (schema comparison, SQL execution, database connection, etc.)
            $this->line();
            $this->error("Failed to update table!");
            $this->line();
            $this->error("Error: " . $e->getMessage());
            $this->line();
            exit(1);
        }
    }
}
