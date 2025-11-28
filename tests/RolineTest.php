<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Base Test Class for Roline Tests
 *
 * Provides common utilities for all Roline tests:
 * - Cleanup helpers
 * - Test file/directory management
 * - Output capturing
 */
abstract class RolineTest extends TestCase
{
    /**
     * Files created during tests (for cleanup)
     * @var array
     */
    protected $createdFiles = [];

    /**
     * Directories created during tests (for cleanup)
     * @var array
     */
    protected $createdDirectories = [];

    /**
     * Clean up created files and directories after each test
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

        // Delete created directories (in reverse order)
        foreach (array_reverse($this->createdDirectories) as $dir) {
            if (is_dir($dir)) {
                $this->deleteDirectory($dir);
            }
        }

        // Reset arrays
        $this->createdFiles = [];
        $this->createdDirectories = [];
    }

    /**
     * Track a file for cleanup
     *
     * @param string $filePath
     */
    protected function trackFile($filePath)
    {
        $this->createdFiles[] = $filePath;
    }

    /**
     * Track a directory for cleanup
     *
     * @param string $dirPath
     */
    protected function trackDirectory($dirPath)
    {
        $this->createdDirectories[] = $dirPath;
    }

    /**
     * Recursively delete a directory
     *
     * @param string $dir
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
     * Execute a Roline command and capture output
     *
     * @param string $command Command to run (e.g., "controller:create Test")
     * @param array $input User input to pipe (for confirmations)
     * @return array ['output' => string, 'exitCode' => int]
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
     * Assert that a file was created
     *
     * @param string $filePath
     * @param string $message
     */
    protected function assertFileCreated($filePath, $message = '')
    {
        $this->assertFileExists($filePath, $message ?: "File was not created: {$filePath}");
    }

    /**
     * Assert that a directory was created
     *
     * @param string $dirPath
     * @param string $message
     */
    protected function assertDirectoryCreated($dirPath, $message = '')
    {
        $this->assertDirectoryExists($dirPath, $message ?: "Directory was not created: {$dirPath}");
    }
}
