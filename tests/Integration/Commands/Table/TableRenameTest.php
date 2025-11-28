<?php namespace Tests\Integration\Commands;

/**
 * TableRename Command Integration Tests
 *
 * Tests the table:rename command which renames database tables directly
 * without requiring model classes.
 *
 * Test Coverage:
 *   - Renaming table with confirmation
 *   - Cancelling table rename
 *   - Attempting to rename non-existent table
 *   - Attempting to rename to existing table name
 *   - Required arguments validation (both old and new names)
 *
 * @category Tests
 * @package  Tests\Integration\Commands
 */

use Tests\RolineTest;

class TableRenameTest extends RolineTest
{
    /**
     * Test renaming table with confirmation
     *
     * @return void
     */
    public function testRenameTableWithConfirmation()
    {
        $oldName = 'old_table_name';
        $newName = 'new_table_name';

        $this->trackTable($oldName);
        $this->trackTable($newName);

        // Create table with old name using raw SQL
        $db = $this->getDb();
        $db->exec("CREATE TABLE `{$oldName}` (id INT PRIMARY KEY)");
        $this->assertTableExists($oldName);

        // Rename it
        $result = $this->runCommand("table:rename {$oldName} {$newName}", ['yes']);

        // Old name should not exist
        $this->assertTableDoesNotExist($oldName);

        // New name should exist
        $this->assertTableExists($newName);

        // Output should confirm success
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'renamed') || str_contains($output, 'success'),
            "Expected success message in output: {$result['output']}"
        );
    }

    /**
     * Test cancelling table rename
     *
     * @return void
     */
    public function testCancelTableRename()
    {
        $oldName = 'keep_old_name';
        $newName = 'not_renamed';

        $this->trackTable($oldName);

        // Create table using raw SQL
        $db = $this->getDb();
        $db->exec("CREATE TABLE `{$oldName}` (id INT PRIMARY KEY)");
        $this->assertTableExists($oldName);

        // Try to rename but cancel
        $result = $this->runCommand("table:rename {$oldName} {$newName}", ['no']);

        // Old name should still exist
        $this->assertTableExists($oldName);

        // New name should not exist
        $this->assertTableDoesNotExist($newName);

        // Output should confirm cancellation
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'cancelled') || str_contains($output, 'cancel'),
            "Expected cancellation message in output: {$result['output']}"
        );
    }

    /**
     * Test renaming non-existent table
     *
     * @return void
     */
    public function testRenameNonExistentTable()
    {
        $oldName = 'does_not_exist';
        $newName = 'new_name';

        $result = $this->runCommand("table:rename {$oldName} {$newName}");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'does not exist') || str_contains($output, 'not found'),
            "Expected error about non-existent table: {$result['output']}"
        );
    }

    /**
     * Test renaming to existing table name
     *
     * @return void
     */
    public function testRenameToExistingTableName()
    {
        $table1 = 'first_table';
        $table2 = 'second_table';

        $this->trackTable($table1);
        $this->trackTable($table2);

        // Create both tables using raw SQL
        $db = $this->getDb();
        $db->exec("CREATE TABLE `{$table1}` (id INT PRIMARY KEY)");
        $db->exec("CREATE TABLE `{$table2}` (id INT PRIMARY KEY)");

        // Try to rename first to second (which already exists)
        $result = $this->runCommand("table:rename {$table1} {$table2}");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'already exists') || str_contains($output, 'exists'),
            "Expected error about existing table: {$result['output']}"
        );
    }

    /**
     * Test table:rename requires both arguments
     *
     * @return void
     */
    public function testRequiresBothArguments()
    {
        // Missing both arguments
        $result = $this->runCommand("table:rename");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'required') || str_contains($output, 'usage'),
            "Expected error about missing arguments: {$result['output']}"
        );

        // Missing new name
        $result = $this->runCommand("table:rename old_name");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'required') || str_contains($output, 'usage'),
            "Expected error about missing new name: {$result['output']}"
        );
    }
}
