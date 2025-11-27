<?php namespace Roline\Commands\View;

/**
 * ViewCreate Command
 *
 * Creates a new view directory with index template file in application/views/.
 * Supports custom stub templates from application/database/stubs/view.stub or
 * generates a default HTML5 template with basic structure.
 *
 * What Gets Created:
 *   - View directory: application/views/{name}/
 *   - Index template: application/views/{name}/index.php
 *
 * Template Options:
 *   1. Custom Stub - If application/database/stubs/view.stub exists, uses it
 *      with placeholder replacement ({{ViewName}}, {{ViewName|ucfirst}})
 *   2. Generated Template - Default HTML5 boilerplate with page title and
 *      basic content structure
 *
 * Safety Features:
 *   - Validates view name (required, valid characters)
 *   - Checks for existing directories to prevent overwriting
 *   - Ensures parent application/views/ directory exists
 *   - Reports success with usage instructions
 *
 * Typical Workflow:
 *   1. Create view directory structure
 *   2. Generate index.php template
 *   3. Developer edits template with Rachie view syntax (@if, @foreach, etc.)
 *   4. Render via View::render('viewname.index') in controllers
 *
 * Usage:
 *   php roline view:create users
 *   php roline view:create blog
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright 2015 - 2050 Geoffrey Okongo
 * @category Roline
 * @package Roline\Commands\View
 * @link https://github.com/glivers/roline
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 1.0.0
 */

use Rackage\File;
use Roline\Output;

class ViewCreate extends ViewCommand
{
    /**
     * Get command description for listing
     *
     * @return string Brief command description
     */
    public function description()
    {
        return 'Create a new view directory';
    }

    /**
     * Get command usage syntax
     *
     * @return string Usage syntax showing required view name argument
     */
    public function usage()
    {
        return '<view|required>';
    }

    /**
     * Display detailed help information
     *
     * Shows required arguments, examples of view creation, what files/directories
     * get created, and details about the generated template structure.
     *
     * @return void
     */
    public function help()
    {
        parent::help();

        Output::info('Arguments:');
        Output::line('  <view|required>  Name of the view directory (e.g., posts, users)');
        Output::line();

        Output::info('Examples:');
        Output::line('  php roline view:create posts');
        Output::line('  php roline view:create users');
        Output::line();

        Output::info('Creates:');
        Output::line('  application/views/posts/');
        Output::line('  application/views/posts/index.php');
        Output::line();

        Output::info('Generated File:');
        Output::line('  - Basic HTML template with Rachie syntax');
        Output::line('  - Ready to use with View::render(\'posts.index\')');
        Output::line();
    }

    /**
     * Execute view directory creation
     *
     * Creates a new view directory under application/views/ with an index.php
     * template file. Supports custom stub templates or generates default HTML5
     * boilerplate. Validates view name, checks for conflicts, and reports success
     * with usage instructions.
     *
     * @param array $arguments Command arguments (view name required at index 0)
     * @return void Exits with status 1 on failure
     */
    public function execute($arguments)
    {
        // Validate and extract view name from arguments
        $name = $this->validateName($arguments[0] ?? null);

        // Check if view directory already exists to prevent overwriting
        if ($this->viewDirExists($name))
        {
            $path = $this->getViewDir($name);
            $this->error("View directory already exists: {$path}");
            exit(1);
        }

        // Build full view directory path
        $viewDir = $this->getViewDir($name);

        // Ensure parent views directory exists (creates application/views/ if needed)
        $this->ensureViewsDir();

        // Create the view directory structure
        $result = File::ensureDir($viewDir);

        if (!$result->success)
        {
            $this->error("Failed to create view directory: {$result->errorMessage}");
            exit(1);
        }

        // Build path to index.php file inside view directory
        $indexPath = $this->getViewPath($viewDir, 'index');

        // Check for custom stub template first, then fall back to generated template
        $customStubPath = getcwd() . '/application/database/stubs/view.stub';

        if (file_exists($customStubPath)) {
            // Read custom stub file
            $stub = File::read($customStubPath);

            if ($stub->success) {
                // Replace placeholders in custom stub with actual view name
                $indexContent = str_replace('{{ViewName}}', $name, $stub->content);
                $indexContent = str_replace('{{ViewName|ucfirst}}', ucfirst($name), $indexContent);
            } else {
                // Fall back to generated template if stub read fails
                $indexContent = $this->generateIndexTemplate($name);
            }
        } else {
            // No custom stub exists - use generated HTML5 template
            $indexContent = $this->generateIndexTemplate($name);
        }

        // Write index.php file to disk
        $writeResult = File::write($indexPath, $indexContent);

        if ($writeResult->success)
        {
            // Display success messages with file paths and usage instructions
            $this->success("View directory created: {$viewDir}");
            $this->success("Index file created: {$indexPath}");
            $this->info("Use: View::render('{$name}.index')");
        }
        else
        {
            $this->error("Failed to create index file: {$writeResult->errorMessage}");
            exit(1);
        }
    }

    /**
     * Generate index template content
     *
     * Creates a default HTML5 boilerplate template when no custom stub is available.
     * Includes basic meta tags, responsive viewport, and placeholder content with
     * the view name incorporated into the title and heading.
     *
     * @param string $name View name (used in title and heading)
     * @return string Complete HTML5 template content
     */
    private function generateIndexTemplate($name)
    {
        // Capitalize view name for display in title and heading
        $nameCapitalized = ucfirst($name);

        // Generate basic HTML5 template with meta tags and placeholder content
        return <<<TEMPLATE
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$nameCapitalized} - Index</title>
</head>
<body>
    <h1>{$nameCapitalized} Index</h1>

    <p>This is the index view for {$name}.</p>

    <!-- Add your content here -->
</body>
</html>
TEMPLATE;
    }
}
