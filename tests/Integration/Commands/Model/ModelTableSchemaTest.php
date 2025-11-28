<?php namespace Tests\Integration\Commands;

use Tests\RolineTest;

/**
 * ModelTableSchema Command Integration Tests
 *
 * Tests the model:table-schema command which displays the database table
 * structure for a given model.
 *
 * Test Coverage:
 *   - Displaying schema for existing table
 *   - Attempting to view schema of non-existent table
 *   - Attempting to view schema for non-existent model
 *   - Validating schema output includes column information
 *
 * @category Tests
 * @package  Tests\Integration\Commands
 */
class ModelTableSchemaTest extends RolineTest
{
    /**
     * Test displaying schema for existing table
     *
     * Verifies that model:table-schema can display the structure of an
     * existing database table.
     *
     * @return void
     */
    public function testDisplaySchemaForExistingTable()
    {
        $modelName = 'SchemaTest';
        $tableName = 'schema_tests';
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

        // Display schema
        $result = $this->runCommand("model:table-schema {$modelName}");

        // Output should contain column information
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'id') || str_contains($output, 'column'),
            "Expected schema information in output: {$result['output']}"
        );
        $this->assertTrue(
            str_contains($output, 'date_created') || str_contains($output, 'date_modified'),
            "Expected timestamp columns in output: {$result['output']}"
        );
    }

    /**
     * Test displaying schema for non-existent table
     *
     * Verifies that model:table-schema fails gracefully when table doesn't exist.
     *
     * @return void
     */
    public function testDisplaySchemaForNonExistentTable()
    {
        $modelName = 'NoTable';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . 'Model.php';

        $this->trackFile($modelPath);

        // Create model but not table
        if (file_exists($modelPath)) {
            unlink($modelPath);
        }
        $this->runCommand("model:create {$modelName}");

        // Try to display schema
        $result = $this->runCommand("model:table-schema {$modelName}");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'does not exist') || str_contains($output, 'not found'),
            "Expected error message about non-existent table: {$result['output']}"
        );
    }

    /**
     * Test displaying schema for non-existent model
     *
     * Verifies that model:table-schema fails gracefully when model doesn't exist.
     *
     * @return void
     */
    public function testDisplaySchemaForNonExistentModel()
    {
        $modelName = 'NoModel';

        $result = $this->runCommand("model:table-schema {$modelName}");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'not found') || str_contains($output, 'does not exist'),
            "Expected error message about non-existent model: {$result['output']}"
        );
    }

    /**
     * Test model:table-schema requires model name argument
     *
     * @return void
     */
    public function testRequiresModelNameArgument()
    {
        $result = $this->runCommand("model:table-schema");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'required') || str_contains($output, 'usage'),
            "Expected error message about missing argument: {$result['output']}"
        );
    }

    /**
     * Test schema output includes table name
     *
     * Verifies that the schema output clearly shows which table is being displayed.
     *
     * @return void
     */
    public function testSchemaOutputIncludesTableName()
    {
        $modelName = 'TableName';
        $tableName = 'table_names';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . 'Model.php';

        $this->trackFile($modelPath);
        $this->trackTable($tableName);

        // Create model and table
        if (file_exists($modelPath)) {
            unlink($modelPath);
        }
        $this->runCommand("model:create {$modelName}");
        $this->runCommand("model:create-table {$modelName}", ['yes']);

        // Display schema
        $result = $this->runCommand("model:table-schema {$modelName}");

        // Output should include table name
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, $tableName) || str_contains($output, 'table'),
            "Expected table name in output: {$result['output']}"
        );
    }
}
