<?php
namespace Tests\Integration\Commands;

use Tests\RolineTest;

/**
 * Integration Tests for model:create command
 *
 * Tests the complete flow of creating model files via CLI
 */
class ModelCreateTest extends RolineTest
{
    /**
     * Test creating a basic model
     */
    public function testCreateBasicModel()
    {
        $modelName = 'TestModel';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . '.php';

        // Track for cleanup
        $this->trackFile($modelPath);

        // Delete if exists from previous test
        if (file_exists($modelPath)) {
            unlink($modelPath);
        }

        // Run command
        $result = $this->runCommand("model:create {$modelName}");

        // Assert file was created
        $this->assertFileCreated($modelPath);

        // Assert file contains expected content
        $content = file_get_contents($modelPath);
        $this->assertStringContainsString('namespace Models;', $content);
        $this->assertStringContainsString('class TestModel extends Model', $content);
        $this->assertStringContainsString("protected static \$table = 'tests';", $content);
        $this->assertStringContainsString('protected static $timestamps = true;', $content);
        $this->assertStringContainsString('@column', $content);
        $this->assertStringContainsString('@primary', $content);
        $this->assertStringContainsString('@autonumber', $content);
    }

    /**
     * Test creating model uses auto-pluralized table name
     */
    public function testCreateModelWithAutoPluralization()
    {
        $modelName = 'Article';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . 'Model.php';

        $this->trackFile($modelPath);

        if (file_exists($modelPath)) {
            unlink($modelPath);
        }

        $result = $this->runCommand("model:create {$modelName}");

        $this->assertFileCreated($modelPath);

        $content = file_get_contents($modelPath);
        // Should auto-pluralize: Article -> articles
        $this->assertStringContainsString("protected static \$table = 'articles';", $content);
    }

    /**
     * Test that generated model is valid PHP
     */
    public function testGeneratedModelIsValidPHP()
    {
        $modelName = 'ValidPHPModel';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . '.php';

        $this->trackFile($modelPath);

        if (file_exists($modelPath)) {
            unlink($modelPath);
        }

        $result = $this->runCommand("model:create {$modelName}");

        // Check PHP syntax
        exec("php -l \"{$modelPath}\" 2>&1", $output, $exitCode);

        $this->assertEquals(0, $exitCode, "Generated model has syntax errors:\n" . implode("\n", $output));
    }

    /**
     * Test model contains proper schema documentation
     */
    public function testModelContainsSchemaDocumentation()
    {
        $modelName = 'DocumentedModel';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . '.php';

        $this->trackFile($modelPath);

        if (file_exists($modelPath)) {
            unlink($modelPath);
        }

        $result = $this->runCommand("model:create {$modelName}");

        $content = file_get_contents($modelPath);

        // Check for schema documentation comments
        $this->assertStringContainsString('DATABASE SCHEMA', $content);
        $this->assertStringContainsString('Supported Types:', $content);
        $this->assertStringContainsString('@varchar', $content);
        $this->assertStringContainsString('@int', $content);
        $this->assertStringContainsString('Modifiers:', $content);
        $this->assertStringContainsString('Examples:', $content);
    }

    /**
     * Test overwriting existing model
     */
    public function testOverwriteExistingModel()
    {
        $modelName = 'ExistingModel';
        $modelPath = TEST_MODELS_PATH . '/' . $modelName . '.php';

        $this->trackFile($modelPath);

        // Create model first time
        $this->runCommand("model:create {$modelName}");
        $this->assertFileExists($modelPath);

        // Try to create again - should reject or ask for confirmation
        $result = $this->runCommand("model:create {$modelName}", ['no']);

        // Should show "already exists" or "cancelled"
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'cancelled') || str_contains($output, 'already exists'),
            "Expected 'cancelled' or 'already exists' in output: {$result['output']}"
        );
    }
}
