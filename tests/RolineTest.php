<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Base Test Class for Roline CLI Tests
 *
 * This class provides common functionality for testing Roline commands.
 * All Roline test classes should extend this class to benefit from:
 *
 * 1. Automatic Cleanup:
 *    - Files created during tests are automatically deleted after each test
 *    - Directories created during tests are recursively removed
 *    - Prevents test pollution and ensures clean state between tests
 *
 * 2. Command Execution:
 *    - runCommand() executes Roline CLI commands as they would run in production
 *    - Commands run from Rachie root directory (mimics real usage)
 *    - Captures both output and exit codes for assertions
 *    - Handles user input simulation (for confirmation prompts)
 *
 * 3. Custom Assertions:
 *    - assertFileCreated() - Verify files were created with helpful error messages
 *    - assertDirectoryCreated() - Verify directories were created
 *
 * Usage Example:
 *
 *   class MyCommandTest extends RolineTest
 *   {
 *       public function testCreateController()
 *       {
 *           $filePath = TEST_CONTROLLERS_PATH . '/MyController.php';
 *           $this->trackFile($filePath); // Mark for cleanup
 *
 *           $result = $this->runCommand('controller:create My');
 *
 *           $this->assertFileCreated($filePath);
 *           $this->assertEquals(0, $result['exitCode']);
 *       }
 *   }
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright 2015 - 2050 Geoffrey Okongo
 * @category Roline
 * @package Tests
 * @link https://github.com/glivers/roline
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 1.0.0
 */
abstract class RolineTest extends TestCase
{
    /**
     * Files created during tests that need cleanup
     *
     * Use trackFile() to add files to this list.
     * Files are automatically deleted in tearDown() after each test.
     *
     * @var array<string> Absolute file paths
     */
    protected $createdFiles = [];

    /**
     * Directories created during tests that need cleanup
     *
     * Use trackDirectory() to add directories to this list.
     * Directories are recursively deleted in tearDown() after each test.
     *
     * @var array<string> Absolute directory paths
     */
    protected $createdDirectories = [];

    /**
     * Clean up created files and directories after each test
     *
     * This method is automatically called by PHPUnit after each test completes.
     * It ensures a clean state between tests by removing all tracked files/directories.
     *
     * Cleanup order:
     * 1. Delete all tracked files
     * 2. Delete all tracked directories (in reverse order for nested dirs)
     * 3. Reset tracking arrays
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Delete created files
        foreach ($this->createdFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // Delete created directories (in reverse order to handle nested structures)
        foreach (array_reverse($this->createdDirectories) as $dir) {
            if (is_dir($dir)) {
                $this->deleteDirectory($dir);
            }
        }

        // Reset tracking arrays for next test
        $this->createdFiles = [];
        $this->createdDirectories = [];
    }

    /**
     * Track a file for automatic cleanup after test completes
     *
     * Call this method BEFORE creating a file to ensure it gets cleaned up
     * even if the test fails or throws an exception.
     *
     * Example:
     *   $this->trackFile('/path/to/TestController.php');
     *   $this->runCommand('controller:create Test');
     *
     * @param string $filePath Absolute path to file that will be created
     * @return void
     */
    protected function trackFile($filePath)
    {
        $this->createdFiles[] = $filePath;
    }

    /**
     * Track a directory for automatic cleanup after test completes
     *
     * Call this method BEFORE creating a directory to ensure it gets cleaned up
     * recursively (including all contents) even if the test fails.
     *
     * Example:
     *   $this->trackDirectory('/path/to/views/myview');
     *   $this->runCommand('view:create myview');
     *
     * @param string $dirPath Absolute path to directory that will be created
     * @return void
     */
    protected function trackDirectory($dirPath)
    {
        $this->createdDirectories[] = $dirPath;
    }

    /**
     * Recursively delete a directory and all its contents
     *
     * This is an internal helper method used by tearDown().
     * It safely deletes directories containing files and subdirectories.
     *
     * @param string $dir Absolute path to directory to delete
     * @return void
     */
    protected function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    /**
     * Execute a Roline CLI command and capture output
     *
     * This method runs Roline commands exactly as they would run in production:
     * - Commands execute from the Rachie root directory
     * - Commands use the actual 'roline' executable
     * - Output (stdout and stderr) is captured and returned
     * - Exit codes are captured for assertions
     * - User input can be simulated for interactive prompts
     *
     * The method handles platform differences (Windows vs Unix) for piping input.
     *
     * Example usage:
     *
     *   // Basic command execution
     *   $result = $this->runCommand('controller:create MyController');
     *   $this->assertEquals(0, $result['exitCode']);
     *
     *   // Command with flags
     *   $result = $this->runCommand('controller:create Posts --resource');
     *
     *   // Command with simulated user input (for confirmation prompts)
     *   $result = $this->runCommand('controller:delete Old', ['yes']);
     *
     *   // Check command output
     *   $this->assertStringContainsString('created', $result['output']);
     *
     * @param string $command Roline command to execute (e.g., "controller:create Test")
     * @param array  $input   Optional user input to pipe (e.g., ['yes', 'no'] for confirmations)
     *
     * @return array{output: string, exitCode: int} Command results
     *               - 'output': Combined stdout and stderr as string
     *               - 'exitCode': Command exit code (0 = success, non-zero = error)
     */
    protected function runCommand($command, $input = [])
    {
        $rolinePath = RACHIE_ROOT . '/roline';
        $rachieRoot = RACHIE_ROOT;

        // Build command - must run from Rachie root directory
        $fullCommand = "cd \"{$rachieRoot}\" && php roline {$command} 2>&1";

        // On Windows, use a temp file for piped input
        if (!empty($input) && PHP_OS_FAMILY === 'Windows') {
            $tempFile = tempnam(sys_get_temp_dir(), 'phpunit_input_');
            file_put_contents($tempFile, implode("\n", $input) . "\n");
            $fullCommand = "cd \"{$rachieRoot}\" && type \"{$tempFile}\" | php roline {$command} 2>&1";
        } elseif (!empty($input)) {
            $inputString = implode("\n", $input) . "\n";
            $fullCommand = "cd \"{$rachieRoot}\" && echo \"{$inputString}\" | php roline {$command} 2>&1";
        }

        // Execute and capture output
        exec($fullCommand, $output, $exitCode);

        // Cleanup temp file
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }

        return [
            'output' => implode("\n", $output),
            'exitCode' => $exitCode
        ];
    }

    /**
     * Assert that a file was created by a command
     *
     * This is a convenience assertion wrapper around PHPUnit's assertFileExists()
     * that provides helpful default error messages specifically for Roline command tests.
     *
     * Use this after running a Roline command that should create a file to verify
     * the file was actually created at the expected location.
     *
     * Example usage:
     *
     *   $filePath = TEST_CONTROLLERS_PATH . '/PostsController.php';
     *   $this->trackFile($filePath);
     *
     *   $result = $this->runCommand('controller:create Posts');
     *
     *   // Verify file was created with helpful default message
     *   $this->assertFileCreated($filePath);
     *   // If fails, shows: "File was not created: /path/to/PostsController.php"
     *
     *   // With custom message
     *   $this->assertFileCreated($filePath, 'Posts controller should be created');
     *
     * @param string $filePath Absolute path to file that should exist
     * @param string $message  Optional custom failure message (defaults to helpful message)
     *
     * @return void
     */
    protected function assertFileCreated($filePath, $message = '')
    {
        $this->assertFileExists($filePath, $message ?: "File was not created: {$filePath}");
    }

    /**
     * Assert that a directory was created by a command
     *
     * This is a convenience assertion wrapper around PHPUnit's assertDirectoryExists()
     * that provides helpful default error messages specifically for Roline command tests.
     *
     * Use this after running a Roline command that should create a directory to verify
     * the directory was actually created at the expected location.
     *
     * Example usage:
     *
     *   $dirPath = TEST_VIEWS_PATH . '/posts';
     *   $this->trackDirectory($dirPath);
     *
     *   $result = $this->runCommand('view:create posts');
     *
     *   // Verify directory was created with helpful default message
     *   $this->assertDirectoryCreated($dirPath);
     *   // If fails, shows: "Directory was not created: /path/to/views/posts"
     *
     *   // With custom message
     *   $this->assertDirectoryCreated($dirPath, 'Posts view directory should be created');
     *
     * @param string $dirPath Absolute path to directory that should exist
     * @param string $message Optional custom failure message (defaults to helpful message)
     *
     * @return void
     */
    protected function assertDirectoryCreated($dirPath, $message = '')
    {
        $this->assertDirectoryExists($dirPath, $message ?: "Directory was not created: {$dirPath}");
    }
}
