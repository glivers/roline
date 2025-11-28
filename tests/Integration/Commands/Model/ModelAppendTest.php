<?php namespace Tests\Integration\Commands;

use Tests\RolineTest;

/**
 * ModelAppend Command Integration Tests
 *
 * Tests the model:append command which adds properties to existing model files
 * using interactive prompts for property names and types.
 *
 * Test Coverage:
 *   - Adding single property to existing model
 *   - Adding multiple properties in one operation
 *   - Attempting to append to non-existent model
 *   - Validating generated @column annotations
 *   - Verifying properties are inserted before MODEL METHODS section
 *   - Testing with various property types (varchar, int, datetime, text)
 *
 * @category Tests
 * @package  Tests\Integration\Commands
 */
class ModelAppendTest extends RolineTest
{
    /**
     * Test appending a single property to existing model
     *
     * Verifies that model:append can add a new property with @column annotation
     * to an existing model file and that the property is properly formatted.
     *
     * @return void
     */
    public function testAppendSingleProperty()
    {
        $modelName = 'AppendTest';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . 'Model.php';

        $this->trackFile($modelPath);

        // Create model first
        if (file_exists($modelPath)) {
            unlink($modelPath);
        }
        $this->runCommand("model:create {$modelName}");
        $this->assertFileExists($modelPath);

        // Append property: name (varchar 255)
        $result = $this->runCommand("model:append {$modelName}", [
            'name',          // property name
            'varchar(100)',  // property type
            ''               // empty to finish
        ]);

        // Read model file
        $content = file_get_contents($modelPath);

        // Verify property was added
        $this->assertStringContainsString('protected $name;', $content);
        $this->assertStringContainsString('@column', $content);
        $this->assertStringContainsString('@varchar(100)', $content);

        // Output should confirm addition
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'added') || str_contains($output, 'success'),
            "Expected success message in output: {$result['output']}"
        );
    }

    /**
     * Test appending multiple properties to existing model
     *
     * Verifies that model:append can add multiple properties in a single
     * command execution with different types.
     *
     * @return void
     */
    public function testAppendMultipleProperties()
    {
        $modelName = 'MultiAppend';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . 'Model.php';

        $this->trackFile($modelPath);

        // Create model first
        if (file_exists($modelPath)) {
            unlink($modelPath);
        }
        $this->runCommand("model:create {$modelName}");

        // Append multiple properties
        $result = $this->runCommand("model:append {$modelName}", [
            'title',         // property 1
            'varchar(255)',
            'status',        // property 2
            'varchar(50)',
            'views',         // property 3
            'int',
            ''               // finish
        ]);

        // Read model file
        $content = file_get_contents($modelPath);

        // Verify all properties were added
        $this->assertStringContainsString('protected $title;', $content);
        $this->assertStringContainsString('@varchar(255)', $content);
        $this->assertStringContainsString('protected $status;', $content);
        $this->assertStringContainsString('@varchar(50)', $content);
        $this->assertStringContainsString('protected $views;', $content);
        $this->assertStringContainsString('@int', $content);

        // Output should confirm number added
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, '3') && str_contains($output, 'properties'),
            "Expected message about 3 properties in output: {$result['output']}"
        );
    }

    /**
     * Test appending to non-existent model
     *
     * Verifies that model:append fails gracefully when target model doesn't exist.
     *
     * @return void
     */
    public function testAppendToNonExistentModel()
    {
        $modelName = 'DoesNotExist';

        // Command should fail before asking for input
        $result = $this->runCommand("model:append {$modelName}");

        // Should show error about model not found
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'does not exist') || str_contains($output, 'not found'),
            "Expected error message about non-existent model: {$result['output']}"
        );
    }

    /**
     * Test appending properties with default type
     *
     * Verifies that model:append uses varchar(255) as default when no type provided.
     *
     * @return void
     */
    public function testAppendWithDefaultType()
    {
        $modelName = 'DefaultType';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . 'Model.php';

        $this->trackFile($modelPath);

        // Create model first
        if (file_exists($modelPath)) {
            unlink($modelPath);
        }
        $this->runCommand("model:create {$modelName}");

        // Append property with empty type (should default to varchar(255))
        $result = $this->runCommand("model:append {$modelName}", [
            'email',  // property name
            '',       // empty type (use default)
            ''        // finish
        ]);

        // Read model file
        $content = file_get_contents($modelPath);

        // Verify default type was used
        $this->assertStringContainsString('protected $email;', $content);
        $this->assertStringContainsString('@varchar(255)', $content);
    }

    /**
     * Test model:append requires model name argument
     *
     * @return void
     */
    public function testRequiresModelNameArgument()
    {
        // Command should fail before asking for input
        $result = $this->runCommand("model:append");

        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'required') || str_contains($output, 'usage'),
            "Expected error message about missing argument: {$result['output']}"
        );
    }

    /**
     * Test properties inserted before MODEL METHODS section
     *
     * Verifies that new properties are inserted in the correct location
     * (before the MODEL METHODS comment).
     *
     * @return void
     */
    public function testPropertiesInsertedBeforeModelMethods()
    {
        $modelName = 'InsertLocation';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . 'Model.php';

        $this->trackFile($modelPath);

        // Create model first
        if (file_exists($modelPath)) {
            unlink($modelPath);
        }
        $this->runCommand("model:create {$modelName}");

        // Append property
        $this->runCommand("model:append {$modelName}", [
            'description',
            'text',
            ''
        ]);

        // Read model file
        $content = file_get_contents($modelPath);

        // Find positions
        $propertyPos = strpos($content, 'protected $description;');
        $methodsPos = strpos($content, 'MODEL METHODS');

        // Verify property comes before MODEL METHODS section
        $this->assertNotFalse($propertyPos, "Property not found in model");
        $this->assertNotFalse($methodsPos, "MODEL METHODS section not found");
        $this->assertLessThan($methodsPos, $propertyPos,
            "Property should be inserted before MODEL METHODS section");
    }
}
