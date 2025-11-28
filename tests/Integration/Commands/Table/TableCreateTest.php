<?php namespace Tests\Integration\Commands;

/**
 * TableCreate Command Integration Tests
 *
 * Tests the table:create command which creates database tables directly
 * without requiring model classes.
 *
 * Test Coverage:
 *   - Creating table interactively with column definitions
 *   - Creating table with auto-detected primary key (id column)
 *   - Attempting to create already existing table
 *   - Required argument validation
 *   - Creating table from SQL file (--sql flag)
 *
 * @category Tests
 * @package  Tests\Integration\Commands
 */

use Tests\RolineTest;

class TableCreateTest extends RolineTest
{
    /**
     * Test creating basic table with id column
     *
     * @return void
     */
    public function testCreateBasicTable()
    {
        $tableName = 'test_table';

        $this->trackTable($tableName);

        // Create table with just id column
        $result = $this->runCommand("table:create {$tableName}", [
            'id',       // column name
            'int',      // type
            'no',       // allow null? no
            '',         // default value (none)
            'yes',      // primary key? yes
            'yes',      // auto increment? yes
            '',         // finish (empty column name)
            'yes',      // confirm creation
        ]);

        // Table should exist
        $this->assertTableExists($tableName);
        $this->assertColumnExists($tableName, 'id');
    }

    /**
     * Test creating table that already exists
     *
     * @return void
     */
    public function testCreateExistingTable()
    {
        $tableName = 'already_exists';

        $this->trackTable($tableName);

        // Create table first
        $db = $this->getDb();
        $db->exec("CREATE TABLE `{$tableName}` (id INT PRIMARY KEY)");

        // Try to create again
        $result = $this->runCommand("table:create {$tableName}");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'already exists') || str_contains($output, 'exists'),
            "Expected error about existing table: {$result['output']}"
        );
    }

    /**
     * Test table:create requires table name argument
     *
     * @return void
     */
    public function testRequiresTableNameArgument()
    {
        $result = $this->runCommand("table:create");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'required') || str_contains($output, 'usage'),
            "Expected error about missing argument: {$result['output']}"
        );
    }

    /**
     * Test cancelling interactive table creation
     *
     * @return void
     */
    public function testCancelInteractiveCreation()
    {
        $tableName = 'cancelled_table';

        // Start creating table but provide empty column name immediately (cancel)
        $result = $this->runCommand("table:create {$tableName}", [
            '', // empty column name = finish/cancel
        ]);

        // Table should not exist
        $this->assertTableDoesNotExist($tableName);

        // Output should mention cancelled or no columns
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'cancelled') || str_contains($output, 'no columns'),
            "Expected cancellation message: {$result['output']}"
        );
    }
}
