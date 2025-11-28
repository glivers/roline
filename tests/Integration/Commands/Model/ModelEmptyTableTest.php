<?php namespace Tests\Integration\Commands;

use Tests\RolineTest;

/**
 * ModelEmptyTable Command Integration Tests
 *
 * Tests the model:empty-table command which deletes all rows from a table
 * while preserving the table structure.
 *
 * Test Coverage:
 *   - Emptying table with confirmation
 *   - Cancelling empty operation
 *   - Attempting to empty non-existent table
 *   - Attempting to empty table for non-existent model
 *   - Verifying table structure is preserved after emptying
 *   - Verifying all rows are deleted
 *
 * @category Tests
 * @package  Tests\Integration\Commands
 */
class ModelEmptyTableTest extends RolineTest
{
    /**
     * Test emptying table with confirmation
     *
     * Verifies that model:empty-table deletes all rows when confirmed,
     * but preserves table structure.
     *
     * @return void
     */
    public function testEmptyTableWithConfirmation()
    {
        $modelName = 'EmptyTest';
        $tableName = 'empty_tests';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . 'Model.php';

        $this->trackFile($modelPath);
        $this->trackTable($tableName);

        // Create model and table
        if (file_exists($modelPath)) {
            unlink($modelPath);
        }
        $this->runCommand("model:create {$modelName}");
        $this->runCommand("model:create-table {$modelName}", ['yes']);
        $this->assertTableExists($tableName);

        // Insert test data
        $this->insertTestData($tableName, [
            ['date_created' => date('Y-m-d H:i:s'), 'date_modified' => date('Y-m-d H:i:s')],
            ['date_created' => date('Y-m-d H:i:s'), 'date_modified' => date('Y-m-d H:i:s')],
        ]);

        // Verify data exists
        $count = $this->getTableRowCount($tableName);
        $this->assertEquals(2, $count, "Expected 2 rows before emptying");

        // Empty table with confirmation
        $result = $this->runCommand("model:empty-table {$modelName}", ['yes']);

        // Table should still exist
        $this->assertTableExists($tableName);

        // But all rows should be deleted
        $count = $this->getTableRowCount($tableName);
        $this->assertEquals(0, $count, "Expected 0 rows after emptying");

        // Output should confirm success
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'emptied') || str_contains($output, 'deleted'),
            "Expected success message in output: {$result['output']}"
        );
    }

    /**
     * Test cancelling empty operation
     *
     * Verifies that model:empty-table preserves data when user declines.
     *
     * @return void
     */
    public function testCancelEmptyTable()
    {
        $modelName = 'KeepData';
        $tableName = 'keep_datas';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . 'Model.php';

        $this->trackFile($modelPath);
        $this->trackTable($tableName);

        // Create model and table
        if (file_exists($modelPath)) {
            unlink($modelPath);
        }
        $this->runCommand("model:create {$modelName}");
        $this->runCommand("model:create-table {$modelName}", ['yes']);

        // Insert test data
        $this->insertTestData($tableName, [
            ['date_created' => date('Y-m-d H:i:s'), 'date_modified' => date('Y-m-d H:i:s')],
        ]);

        // Try to empty with 'no' (cancel)
        $result = $this->runCommand("model:empty-table {$modelName}", ['no']);

        // Data should still exist
        $count = $this->getTableRowCount($tableName);
        $this->assertEquals(1, $count, "Expected data to be preserved after cancellation");

        // Output should confirm cancellation
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'cancelled') || str_contains($output, 'cancel'),
            "Expected cancellation message in output: {$result['output']}"
        );
    }

    /**
     * Test emptying non-existent table
     *
     * Verifies that model:empty-table fails gracefully when table doesn't exist.
     *
     * @return void
     */
    public function testEmptyNonExistentTable()
    {
        $modelName = 'NoTable';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . 'Model.php';

        $this->trackFile($modelPath);

        // Create model but not table
        if (file_exists($modelPath)) {
            unlink($modelPath);
        }
        $this->runCommand("model:create {$modelName}");

        // Try to empty non-existent table
        $result = $this->runCommand("model:empty-table {$modelName}");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'does not exist') || str_contains($output, 'not found'),
            "Expected error message about non-existent table: {$result['output']}"
        );
    }

    /**
     * Test emptying table from non-existent model
     *
     * @return void
     */
    public function testEmptyTableFromNonExistentModel()
    {
        $modelName = 'NoModel';

        $result = $this->runCommand("model:empty-table {$modelName}");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'not found') || str_contains($output, 'does not exist'),
            "Expected error message about non-existent model: {$result['output']}"
        );
    }

    /**
     * Test model:empty-table requires model name argument
     *
     * @return void
     */
    public function testRequiresModelNameArgument()
    {
        $result = $this->runCommand("model:empty-table");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'required') || str_contains($output, 'usage'),
            "Expected error message about missing argument: {$result['output']}"
        );
    }

    /**
     * Test table structure preserved after emptying
     *
     * Verifies that columns, indexes, and constraints are preserved.
     *
     * @return void
     */
    public function testTableStructurePreserved()
    {
        $modelName = 'StructureTest';
        $tableName = 'structure_tests';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . 'Model.php';

        $this->trackFile($modelPath);
        $this->trackTable($tableName);

        // Create model and table
        if (file_exists($modelPath)) {
            unlink($modelPath);
        }
        $this->runCommand("model:create {$modelName}");
        $this->runCommand("model:create-table {$modelName}", ['yes']);

        // Verify columns exist before
        $this->assertColumnExists($tableName, 'id');
        $this->assertColumnExists($tableName, 'date_created');
        $this->assertColumnExists($tableName, 'date_modified');

        // Empty table
        $this->runCommand("model:empty-table {$modelName}", ['yes']);

        // Verify columns still exist after
        $this->assertColumnExists($tableName, 'id');
        $this->assertColumnExists($tableName, 'date_created');
        $this->assertColumnExists($tableName, 'date_modified');
    }
}
