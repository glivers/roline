<?php namespace Roline\Commands\Controller;

/**
 * ControllerAppend Command
 *
 * Adds a new method to an existing controller class without recreating the
 * entire file. The method is inserted before the closing brace with proper
 * formatting, GET prefix, and auto-generated view path.
 *
 * Features:
 *   - Validates controller exists before modification
 *   - Checks for method name conflicts
 *   - Auto-adds GET prefix (getMethodName)
 *   - Generates view path (controller.method)
 *   - Maintains proper code formatting
 *   - Preserves existing controller code
 *
 * Generated Method Structure:
 *   - Docblock with description
 *   - GET-prefixed method name
 *   - View rendering call
 *
 * Usage:
 *   php roline controller:append Posts published
 *   php roline controller:append Users archive
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright 2015 - 2050 Geoffrey Okongo
 * @category Roline
 * @package Roline\Commands\Controller
 * @link https://github.com/glivers/roline
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 1.0.0
 */

use Rackage\File;
use Roline\Output;

class ControllerAppend extends ControllerCommand
{
    public function description()
    {
        return 'Append a method to an existing controller';
    }

    public function usage()
    {
        return '<Controller|required> <method|required>';
    }

    public function help()
    {
        parent::help();

        Output::info('Arguments:');
        Output::line('  <Controller|required>  Name of the existing controller');
        Output::line('  <method|required>      Name of the method to add (e.g., published, archive)');
        Output::line();

        Output::info('Examples:');
        Output::line('  php roline controller:append Posts published');
        Output::line('  php roline controller:append Users archive');
        Output::line();

        Output::info('Generates:');
        Output::line('  Adds a new GET method to the controller:');
        Output::line('    public function getPublished()');
        Output::line('    {');
        Output::line('        View::render(\'posts.published\');');
        Output::line('    }');
        Output::line();

        Output::info('Note:');
        Output::line('  - Method is inserted before the closing brace of the class');
        Output::line('  - Uses GET prefix by default (getMethodName)');
        Output::line('  - View path is auto-generated (controller.method)');
        Output::line();
    }

    public function execute($arguments)
    {
        $controllerName = $this->validateName($arguments[0] ?? null);

        // Verify controller exists before attempting modification
        if (!$this->controllerExists($controllerName))
        {
            $this->error("Controller not found: {$controllerName}Controller");
            exit(1);
        }

        // Validate method name is provided
        if (!isset($arguments[1]) || empty($arguments[1]))
        {
            $this->error('Method name is required');
            $this->line('Usage: php roline controller:append <Controller> <method>');
            exit(1);
        }

        $methodName = $arguments[1];

        // Read existing controller file content
        $path = $this->getControllerPath($controllerName);
        $fileContent = File::read($path);

        if (!$fileContent->success)
        {
            $this->error("Failed to read controller: {$path}");
            exit(1);
        }

        $content = $fileContent->content;

        // Check for method name conflicts to prevent duplication
        $methodPattern = '/public\s+function\s+get' . ucfirst($methodName) . '\s*\(/';
        if (preg_match($methodPattern, $content))
        {
            $this->error("Method 'get" . ucfirst($methodName) . "' already exists in {$controllerName}Controller");
            exit(1);
        }

        // Generate view path following convention: controller.method
        $viewPath = strtolower($controllerName) . '.' . strtolower($methodName);
        $newMethod = $this->generateMethod($methodName, $viewPath);

        // Find the last closing brace (end of class)
        $lastBracePos = strrpos($content, '}');

        if ($lastBracePos === false)
        {
            $this->error("Invalid controller file structure");
            exit(1);
        }

        // Insert new method before the closing brace
        $updatedContent = substr($content, 0, $lastBracePos) . $newMethod . "\n" . substr($content, $lastBracePos);

        // Write modified content back to file
        $result = File::write($path, $updatedContent);

        if ($result->success)
        {
            $this->success("Method 'get" . ucfirst($methodName) . "' added to {$controllerName}Controller");
            $this->info("View path: {$viewPath}");
        }
        else
        {
            $this->error("Failed to update controller: {$result->errorMessage}");
            exit(1);
        }
    }

    /**
     * Generate method code with docblock and view rendering
     *
     * Creates a properly formatted controller method with GET prefix,
     * descriptive docblock, and view rendering call.
     *
     * @param string $methodName Method name without prefix (e.g., 'published')
     * @param string $viewPath View path for rendering (e.g., 'posts.published')
     * @return string Complete method code ready for insertion
     */
    private function generateMethod($methodName, $viewPath)
    {
        $methodNameCapitalized = ucfirst($methodName);

        return <<<METHOD

    /**
     * Handle {$methodName} action
     *
     * @return void
     */
    public function get{$methodNameCapitalized}()
    {
        View::render('{$viewPath}');
    }
METHOD;
    }
}
