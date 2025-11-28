<?php namespace Tests\Integration\Commands;

/**
 * TableDelete Command Integration Tests
 *
 * Tests the table:delete command which drops database tables directly
 * without requiring model classes.
 *
 * Test Coverage:
 *   - Deleting table with confirmation
 *   - Cancelling table deletion
 *   - Attempting to delete non-existent table
 *   - Required argument validation
 *   - Confirmation prompt display
 *
 * @category Tests
 * @package  Tests\Integration\Commands
 */

use Tests\RolineTest;

class TableDeleteTest extends RolineTest
{
    /**
     * Test deleting table with confirmation
     *
     * @return void
     */
    public function testDeleteTableWithConfirmation()
    {
        $tableName = 'delete_me';

        $this->trackTable($tableName);

        // Create table using raw SQL
        $db = $this->getDb();
        $db->exec("CREATE TABLE `{$tableName}` (id INT PRIMARY KEY)");
        $this->assertTableExists($tableName);

        // Delete with confirmation
        $result = $this->runCommand("table:delete {$tableName}", ['yes']);

        // Table should be gone
        $this->assertTableDoesNotExist($tableName);

        // Output should confirm deletion
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'deleted') || str_contains($output, 'dropped'),
            "Expected success message in output: {$result['output']}"
        );
    }

    /**
     * Test cancelling table deletion
     *
     * @return void
     */
    public function testCancelTableDeletion()
    {
        $tableName = 'keep_me';

        $this->trackTable($tableName);

        // Create table using raw SQL
        $db = $this->getDb();
        $db->exec("CREATE TABLE `{$tableName}` (id INT PRIMARY KEY)");
        $this->assertTableExists($tableName);

        // Try to delete but cancel
        $result = $this->runCommand("table:delete {$tableName}", ['no']);

        // Table should still exist
        $this->assertTableExists($tableName);

        // Output should confirm cancellation
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'cancelled') || str_contains($output, 'cancel'),
            "Expected cancellation message in output: {$result['output']}"
        );
    }

    /**
     * Test deleting non-existent table
     *
     * @return void
     */
    public function testDeleteNonExistentTable()
    {
        $tableName = 'does_not_exist';

        $result = $this->runCommand("table:delete {$tableName}");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'does not exist') || str_contains($output, 'not found'),
            "Expected error about non-existent table: {$result['output']}"
        );
    }

    /**
     * Test table:delete requires table name argument
     *
     * @return void
     */
    public function testRequiresTableNameArgument()
    {
        $result = $this->runCommand("table:delete");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'required') || str_contains($output, 'usage'),
            "Expected error about missing argument: {$result['output']}"
        );
    }

    /**
     * Test deletion shows warning message
     *
     * @return void
     */
    public function testShowsWarningMessage()
    {
        $tableName = 'warning_test';

        $this->trackTable($tableName);

        // Create table using raw SQL
        $db = $this->getDb();
        $db->exec("CREATE TABLE `{$tableName}` (id INT PRIMARY KEY)");

        // Try to delete (we'll cancel, just checking for warning)
        $result = $this->runCommand("table:delete {$tableName}", ['no']);

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'warning') || str_contains($output, 'data'),
            "Expected warning message in output: {$result['output']}"
        );
    }
}
