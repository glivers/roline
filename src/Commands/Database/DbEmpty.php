<?php namespace Roline\Commands\Database;

/**
 * DbEmpty Command
 *
 * Empties ALL tables in the database by deleting all rows while preserving table structures.
 * Much faster than emptying tables one by one, useful for development/testing.
 *
 * What Gets Deleted:
 *   - ALL rows in ALL tables (complete data loss)
 *   - Data permanently removed from ALL tables
 *
 * What Gets Preserved:
 *   - ALL table structures
 *   - Column definitions
 *   - Indexes and constraints
 *   - Auto-increment counters (NOT reset)
 *   - Triggers
 *
 * Safety Features:
 *   - Shows list of all tables before deletion
 *   - Requires user confirmation
 *   - Disables foreign key checks during operation
 *   - Continues through errors (empties as many tables as possible)
 *   - Displays summary of successfully emptied tables
 *
 * Use Cases:
 *   - Clearing development database for fresh crawl
 *   - Resetting test data between test runs
 *   - Quick cleanup during development
 *   - Emptying all tables before re-importing data
 *
 * Usage:
 *   php roline db:empty
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
use Rackage\Database;
use Rackage\Registry;

class DbEmpty extends DatabaseCommand
{
    /**
     * Get command description for listing
     *
     * @return string Brief command description
     */
    public function description()
    {
        return 'Empty all database tables (delete all rows)';
    }

    /**
     * Get command usage syntax
     *
     * @return string Empty string (no arguments required)
     */
    public function usage()
    {
        return '';
    }

    /**
     * Display detailed help information
     *
     * @return void
     */
    public function help()
    {
        parent::help();

        Output::info('Description:');
        Output::line('  Empties ALL tables in the database by deleting all rows.');
        Output::line('  Table structures are preserved (unlike db:drop).');
        Output::line();

        Output::info('Examples:');
        Output::line('  php roline db:empty');
        Output::line();

        Output::info('Warning:');
        Output::line('  - This deletes ALL data from ALL tables!');
        Output::line('  - This action CANNOT be undone!');
        Output::line('  - Table structures are preserved');
        Output::line();
    }

    /**
     * Execute database empty operation
     *
     * Empties ALL tables in database after confirmation.
     *
     * @param array $arguments Command arguments (none required)
     * @return void Exits with status 0 on cancel/success, 1 on failure
     */
    public function execute($arguments)
    {
        try {
            // Get database name from configuration
            $dbConfig = Registry::database();
            $driver = $dbConfig['default'] ?? 'mysql';
            $databaseName = $dbConfig[$driver]['database'] ?? 'database';

            // Display warning banner
            $this->line();
            $this->error('WARNING: This will DELETE ALL rows from ALL tables!');
            $this->line();

            // Get database connection from Registry
            $db = Registry::get('database');

            // Get all tables from database using pure SQL
            $result = $db->execute("SHOW TABLES");
            $tables = [];
            while ($row = $result->fetch_array()) {
                $tables[] = $row[0];
            }

            // Check if database has any tables
            if (empty($tables)) {
                $this->info('No tables found in database.');
                $this->line();
                exit(0);
            }

            // Display database info and table count
            $this->info("Database: {$databaseName}");
            $this->info('Tables to empty: ' . count($tables));
            $this->line();

            // Show all tables that will be emptied
            foreach ($tables as $tableName) {
                $this->line("  → {$tableName}");
            }

            // Emphasize irreversibility
            $this->line();
            $this->error('This action CANNOT be undone!');
            $this->info('Note: Table structures will be preserved');
            $this->line();

            // Confirmation
            $confirmed = $this->confirm("Are you sure you want to empty ALL tables in {$databaseName}?");

            if (!$confirmed) {
                $this->info('Operation cancelled.');
                $this->line();
                exit(0);
            }

            // Begin emptying tables
            $this->line();
            $this->info('Emptying tables...');
            $this->line();

            // Disable foreign key checks for safe deletion
            $db->execute('SET FOREIGN_KEY_CHECKS=0');

            // Track successfully emptied tables count
            $emptiedCount = 0;

            // Empty each table (continue through errors)
            foreach ($tables as $tableName) {
                try {
                    $this->info("  → Emptying {$tableName}...");

                    // Execute DELETE FROM statement
                    $db->execute("DELETE FROM `{$tableName}`");

                    // Increment emptied count
                    $emptiedCount++;
                } catch (\Exception $e) {
                    // Table empty failed - display error but continue
                    $this->error("  ✗ Failed to empty {$tableName}: " . $e->getMessage());
                }
            }

            // Re-enable foreign key checks
            $db->execute('SET FOREIGN_KEY_CHECKS=1');

            // All tables processed - display summary
            $this->line();
            $this->success("Successfully emptied {$emptiedCount} tables!");
            $this->line();
            $this->info("All data deleted from '{$databaseName}'. Table structures preserved.");
            $this->line();

        } catch (\Exception $e) {
            // Empty operation failed
            $this->line();
            $this->error('Empty operation failed!');
            $this->line();
            $this->error('Error: ' . $e->getMessage());
            $this->line();
            exit(1);
        }
    }
}
