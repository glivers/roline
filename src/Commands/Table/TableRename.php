<?php namespace Roline\Commands\Table;

/**
 * TableRename Command
 *
 * Renames a database table and optionally updates the associated model file and
 * class name. Executes RENAME TABLE statement in database and offers to update
 * model's $table property and rename the model file itself for consistency.
 *
 * What Gets Renamed:
 *   - Database table name (via RENAME TABLE statement)
 *   - Model $table property (optional, user confirmation)
 *   - Model file and class name (optional, user confirmation)
 *
 * Safety Features:
 *   - Validates current table exists before renaming
 *   - Checks new name doesn't conflict with existing tables
 *   - Shows before/after preview of rename operation
 *   - Requires user confirmation before database change
 *   - Optional model updates (user can decline)
 *   - Updates class name in model file when renaming
 *
 * Important Notes:
 *   - Does NOT update foreign key references automatically
 *   - Does NOT update code references to old model name
 *   - User must manually update controller/service references
 *   - Model file rename updates class name and docblock
 *
 * Typical Workflow:
 *   1. User runs command with old and new names
 *   2. Command validates both names and shows preview
 *   3. User confirms database table rename
 *   4. RENAME TABLE executed in database
 *   5. Command offers to update model $table property
 *   6. Command offers to rename model file
 *   7. User updates code references manually
 *
 * Example:
 *   php roline table:rename Post Article
 *   - Renames 'posts' table to 'articles'
 *   - Updates PostsModel.php $table property
 *   - Renames PostsModel.php to ArticlesModel.php
 *   - Updates class name from PostsModel to ArticlesModel
 *
 * Usage:
 *   php roline table:rename Post Article
 *   php roline table:rename OldUser NewUser
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

class TableRename extends TableCommand
{
    /**
     * Get command description for listing
     *
     * @return string Brief command description
     */
    public function description()
    {
        return 'Rename table and optionally model';
    }

    /**
     * Get command usage syntax
     *
     * @return string Usage syntax showing current model and new name
     */
    public function usage()
    {
        return '<Model|required> <new_name|required>';
    }

    /**
     * Display detailed help information
     *
     * Shows arguments, examples, workflow steps, and important notes about
     * what does and doesn't get updated automatically during rename.
     *
     * @return void
     */
    public function help()
    {
        parent::help();

        Output::info('Arguments:');
        Output::line('  <Model|required>      Current model name (without "Model" suffix)');
        Output::line('  <new_name|required>   New table name');
        Output::line();

        Output::info('Examples:');
        Output::line('  php roline table:rename Post Article');
        Output::line('  php roline table:rename OldUser NewUser');
        Output::line();

        Output::info('What it does:');
        Output::line('  1. Renames the database table');
        Output::line('  2. Optionally renames the model file');
        Output::line('  3. Updates $table property in model');
        Output::line();

        Output::info('Note:');
        Output::line('  - This does NOT update foreign key references!');
        Output::line('  - Update those manually if needed');
        Output::line();
    }

    /**
     * Execute table rename with optional model updates
     *
     * Renames database table via RENAME TABLE statement, then offers to update
     * model's $table property and rename the model file itself. Validates both
     * old and new names, shows preview, requires confirmation, and executes
     * database change before offering optional model file updates.
     *
     * @param array $arguments Command arguments (current model at index 0, new name at index 1)
     * @return void Exits with status 0 on cancel, 1 on failure
     */
    public function execute($arguments)
    {
        // Validate both current model name and new table name provided
        if (empty($arguments[0]) || empty($arguments[1])) {
            $this->error('Both current model name and new name are required!');
            $this->line();
            $this->info('Usage: php roline table:rename <Model> <new_name>');
            $this->line();
            $this->info('Example: php roline table:rename Post Article');
            exit(1);
        }

        // Extract arguments
        $currentModelName = $arguments[0];
        $newTableName = $arguments[1];
        $modelClass = "Models\\{$currentModelName}Model";

        // Validate model class exists
        if (!class_exists($modelClass)) {
            $this->error("Model class not found: {$modelClass}");
            $this->line();
            $this->info('Check the model name and try again.');
            exit(1);
        }

        // Extract current table name from model's $table property
        try {
            // Use reflection to access protected static property
            $reflection = new \ReflectionClass($modelClass);
            $tableProperty = $reflection->getProperty('table');
            $tableProperty->setAccessible(true);
            $currentTableName = $tableProperty->getValue();

            // Validate table name is defined
            if (empty($currentTableName)) {
                $this->error('Model does not have a table name defined!');
                exit(1);
            }
        } catch (\Exception $e) {
            // Reflection failed
            $this->error('Error reading model: ' . $e->getMessage());
            exit(1);
        }

        // Validate current table exists in database
        $schema = new MySQLSchema();
        if (!$schema->tableExists($currentTableName)) {
            $this->error("Table '{$currentTableName}' does not exist!");
            $this->line();
            $this->info("Create it first: php roline table:create {$currentModelName}");
            exit(1);
        }

        // Check new table name doesn't conflict with existing tables
        if ($schema->tableExists($newTableName)) {
            $this->error("Table '{$newTableName}' already exists!");
            $this->line();
            $this->info('Choose a different name.');
            exit(1);
        }

        // Display rename preview (before/after)
        $this->line();
        $this->info("Renaming table:");
        $this->line("  From: {$currentTableName}");
        $this->line("  To:   {$newTableName}");
        $this->line();

        // Request user confirmation before database change
        $confirmed = $this->confirm("Are you sure you want to rename this table?");

        if (!$confirmed) {
            // User cancelled
            $this->info("Table rename cancelled.");
            exit(0);
        }

        // Execute RENAME TABLE statement in database
        try {
            $this->line();
            $this->info("Renaming table in database...");

            // Execute RENAME TABLE via MySQLSchema
            $schema->renameTable($currentTableName, $newTableName);

            // Table renamed successfully
            $this->success("Table renamed successfully!");
            $this->line();

        } catch (\Exception $e) {
            // Rename failed (SQL execution, database connection, etc.)
            $this->line();
            $this->error("Failed to rename table!");
            $this->line();
            $this->error("Error: " . $e->getMessage());
            $this->line();
            exit(1);
        }

        // Offer to update model's $table property
        $this->line();
        $updateModel = $this->confirm("Would you like to update the model's \$table property to '{$newTableName}'?");

        if ($updateModel) {
            try {
                // Update $table property via regex replacement
                $this->updateModelTableProperty($modelClass, $newTableName);
                $this->line();
                $this->success("Model \$table property updated!");
                $this->line();

            } catch (\Exception $e) {
                // Update failed - provide manual instructions
                $this->line();
                $this->error("Failed to update model file!");
                $this->line();
                $this->error("Error: " . $e->getMessage());
                $this->line();
                $this->info("Please update manually: protected static \$table = '{$newTableName}';");
                $this->line();
            }
        }

        // Offer to rename model file and update class name
        $this->line();
        $renameFile = $this->confirm("Would you also like to rename the model file from {$currentModelName}Model.php to {$newTableName}Model.php?");

        if ($renameFile) {
            try {
                // Rename file and update class name inside
                $this->renameModelFile($currentModelName, $newTableName);
                $this->line();
                $this->success("Model file renamed!");
                $this->line();
                $this->info("Old: application/models/{$currentModelName}Model.php");
                $this->info("New: application/models/{$newTableName}Model.php");
                $this->line();
                $this->info("Don't forget to update any references to the old model name in your code!");
                $this->line();

            } catch (\Exception $e) {
                // Rename failed
                $this->line();
                $this->error("Failed to rename model file!");
                $this->line();
                $this->error("Error: " . $e->getMessage());
                $this->line();
            }
        }

        // All operations complete
        $this->line();
        $this->success("Table rename complete!");
        $this->line();
    }

    /**
     * Update model's $table property
     *
     * Modifies the model file to update the protected static $table property
     * with the new table name. Uses regex to find and replace the property value
     * while preserving formatting and quote style.
     *
     * @param string $modelClass Fully-qualified model class name
     * @param string $newTableName New table name to set
     * @return void
     * @throws \Exception If model file not found or $table property not found
     */
    private function updateModelTableProperty($modelClass, $newTableName)
    {
        // Get model file path via reflection
        $reflection = new \ReflectionClass($modelClass);
        $filename = $reflection->getFileName();

        // Validate file exists
        if (!file_exists($filename)) {
            throw new \Exception("Model file not found: {$filename}");
        }

        // Read current file contents
        $content = file_get_contents($filename);

        // Build regex pattern to match $table property (preserves quote style)
        $pattern = '/(protected\s+static\s+\$table\s*=\s*[\'"])([^\'"]+)([\'"];)/';
        $replacement = '${1}' . $newTableName . '${3}';

        // Replace $table property value
        $newContent = preg_replace($pattern, $replacement, $content);

        // Validate replacement succeeded
        if ($newContent === null || $newContent === $content) {
            throw new \Exception("Could not find \$table property to update");
        }

        // Write modified content back to file
        file_put_contents($filename, $newContent);
    }

    /**
     * Rename model file and update class name
     *
     * Renames the model file from old name to new name and updates the class
     * declaration and docblock inside the file to match. Validates old file
     * exists and new file doesn't exist before proceeding. Original file is
     * deleted after new file is written successfully.
     *
     * @param string $oldName Old model name (without Model suffix)
     * @param string $newName New model name (without Model suffix)
     * @return void
     * @throws \Exception If old file not found or new file already exists
     */
    private function renameModelFile($oldName, $newName)
    {
        // Build full file paths
        $oldFile = getcwd() . "/application/models/{$oldName}Model.php";
        $newFile = getcwd() . "/application/models/{$newName}Model.php";

        // Validate old file exists
        if (!file_exists($oldFile)) {
            throw new \Exception("Model file not found: {$oldFile}");
        }

        // Check new file doesn't already exist
        if (file_exists($newFile)) {
            throw new \Exception("Target file already exists: {$newFile}");
        }

        // Read current file contents
        $content = file_get_contents($oldFile);

        // Update class name declaration
        $content = preg_replace(
            "/class {$oldName}Model/",
            "class {$newName}Model",
            $content
        );

        // Update docblock references to model name
        $content = preg_replace(
            "/{$oldName} Model/",
            "{$newName} Model",
            $content
        );

        // Write updated content to new file
        file_put_contents($newFile, $content);

        // Delete old file now that new file exists
        unlink($oldFile);
    }
}
