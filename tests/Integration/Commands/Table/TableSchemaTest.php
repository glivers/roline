<?php namespace Tests\Integration\Commands;

/**
 * TableSchema Command Integration Tests
 *
 * Tests the table:schema command which displays database table structure
 * directly without requiring model classes.
 *
 * Test Coverage:
 *   - Displaying schema for existing table
 *   - Attempting to view schema of non-existent table
 *   - Validating schema output includes column information
 *   - Required argument validation
 *
 * @category Tests
 * @package  Tests\Integration\Commands
 */

use Tests\RolineTest;

class TableSchemaTest extends RolineTest
{
    /**
     * Test displaying schema for existing table
     *
     * @return void
     */
    public function testDisplaySchemaForExistingTable()
    {
        $tableName = 'schema_display';

        $this->trackTable($tableName);

        // Create table with multiple columns using raw SQL
        $db = $this->getDb();
        $db->exec("CREATE TABLE `{$tableName}` (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(255)
        )");
        $this->assertTableExists($tableName);

        // Display schema
        $result = $this->runCommand("table:schema {$tableName}");

        // Output should contain column information
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'id') || str_contains($output, 'column'),
            "Expected schema information in output: {$result['output']}"
        );
        $this->assertTrue(
            str_contains($output, 'name') || str_contains($output, 'email'),
            "Expected column names in output: {$result['output']}"
        );
    }

    /**
     * Test displaying schema for non-existent table
     *
     * @return void
     */
    public function testDisplaySchemaForNonExistentTable()
    {
        $tableName = 'no_such_table';

        $result = $this->runCommand("table:schema {$tableName}");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'does not exist') || str_contains($output, 'not found'),
            "Expected error message about non-existent table: {$result['output']}"
        );
    }

    /**
     * Test table:schema requires table name argument
     *
     * @return void
     */
    public function testRequiresTableNameArgument()
    {
        $result = $this->runCommand("table:schema");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'required') || str_contains($output, 'usage'),
            "Expected error message about missing argument: {$result['output']}"
        );
    }

    /**
     * Test schema output includes table name
     *
     * @return void
     */
    public function testSchemaOutputIncludesTableName()
    {
        $tableName = 'named_table';

        $this->trackTable($tableName);

        // Create table using raw SQL
        $db = $this->getDb();
        $db->exec("CREATE TABLE `{$tableName}` (id INT PRIMARY KEY)");

        // Display schema
        $result = $this->runCommand("table:schema {$tableName}");

        // Output should include table name
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, $tableName) || str_contains($output, 'table'),
            "Expected table name in output: {$result['output']}"
        );
    }
}
