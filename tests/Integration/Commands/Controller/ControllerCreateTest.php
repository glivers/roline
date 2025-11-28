<?php
namespace Tests\Integration\Commands;

use Tests\RolineTest;

/**
 * Integration Tests for controller:create command
 *
 * Tests the complete flow of creating controller files via CLI
 */
class ControllerCreateTest extends RolineTest
{
    /**
     * Test creating a basic controller
     */
    public function testCreateBasicController()
    {
        $controllerName = 'TestController';
        $controllerPath = TEST_CONTROLLERS_PATH . '/' . $controllerName . '.php';

        // Track for cleanup
        $this->trackFile($controllerPath);

        // Delete if exists from previous test
        if (file_exists($controllerPath)) {
            unlink($controllerPath);
        }

        // Run command
        $result = $this->runCommand("controller:create {$controllerName}");

        // Assert file was created
        $this->assertFileCreated($controllerPath);

        // Assert file contains expected content
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('namespace Controllers;', $content);
        $this->assertStringContainsString('class TestController extends Controller', $content);
        $this->assertStringContainsString('public function getIndex()', $content);
    }

    /**
     * Test creating controller with resource methods
     */
    public function testCreateResourceController()
    {
        $controllerName = 'PostsController';
        $controllerPath = TEST_CONTROLLERS_PATH . '/' . $controllerName . '.php';

        $this->trackFile($controllerPath);

        if (file_exists($controllerPath)) {
            unlink($controllerPath);
        }

        $result = $this->runCommand("controller:create {$controllerName} --resource");

        $this->assertFileCreated($controllerPath);

        $content = file_get_contents($controllerPath);

        // Assert RESTful methods exist
        $this->assertStringContainsString('public function getIndex()', $content);
        $this->assertStringContainsString('public function getCreate()', $content);
        $this->assertStringContainsString('public function postStore()', $content);
        $this->assertStringContainsString('public function getShow($id)', $content);
        $this->assertStringContainsString('public function getEdit($id)', $content);
        $this->assertStringContainsString('public function putUpdate($id)', $content);
        $this->assertStringContainsString('public function deleteDestroy($id)', $content);
    }

    /**
     * Test that controller file is valid PHP
     */
    public function testGeneratedControllerIsValidPHP()
    {
        $controllerName = 'ValidPHPController';
        $controllerPath = TEST_CONTROLLERS_PATH . '/' . $controllerName . '.php';

        $this->trackFile($controllerPath);

        if (file_exists($controllerPath)) {
            unlink($controllerPath);
        }

        $result = $this->runCommand("controller:create {$controllerName}");

        // Check PHP syntax
        exec("php -l \"{$controllerPath}\" 2>&1", $output, $exitCode);

        $this->assertEquals(0, $exitCode, "Generated controller has syntax errors:\n" . implode("\n", $output));
    }

    /**
     * Test overwriting existing controller (should ask for confirmation)
     */
    public function testOverwriteExistingController()
    {
        $controllerName = 'ExistingController';
        $controllerPath = TEST_CONTROLLERS_PATH . '/' . $controllerName . '.php';

        $this->trackFile($controllerPath);

        // Create controller first time
        $this->runCommand("controller:create {$controllerName}");
        $this->assertFileExists($controllerPath);

        // Try to create again - should ask for confirmation or reject
        $result = $this->runCommand("controller:create {$controllerName}", ['no']);

        // Should be cancelled or show "already exists"
        $output = strtolower($result['output']);
        $this->assertTrue(
            str_contains($output, 'cancelled') || str_contains($output, 'already exists'),
            "Expected 'cancelled' or 'already exists' in output: {$result['output']}"
        );
    }
}
