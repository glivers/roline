<?php namespace Roline\Commands\Controller;

/**
 * ControllerComplete Command
 *
 * Convenience command designed to create complete MVC resource scaffolding in a single
 * operation by generating controller, model, and views together. Currently shows "coming
 * soon" message as scaffolding feature is not yet implemented.
 *
 * Intended Functionality (Planned):
 *   When fully implemented, this command will create:
 *   - Controller file in application/controllers/
 *   - Model file in application/models/
 *   - View directory in application/views/ with standard templates:
 *     * index.php (list view)
 *     * show.php (single item view)
 *     * create.php (create form)
 *     * edit.php (edit form)
 *
 * Typical Usage Pattern (When Implemented):
 *   php roline controller:complete Posts
 *
 *   Would create:
 *   - application/controllers/PostsController.php
 *   - application/models/PostsModel.php
 *   - application/views/posts/index.php
 *   - application/views/posts/show.php
 *   - application/views/posts/create.php
 *   - application/views/posts/edit.php
 *
 * Advantages Over Individual Commands:
 *   Instead of running:
 *   - php roline controller:create Posts
 *   - php roline model:create Posts
 *   - php roline view:create posts
 *   - php roline view:add posts show
 *   - php roline view:add posts create
 *   - php roline view:add posts edit
 *
 *   Developer runs single command:
 *   - php roline controller:complete Posts
 *
 * Current Status:
 *   - Command is registered and callable
 *   - Displays informational message about planned feature
 *   - Does NOT currently generate any files
 *   - Reserved for future scaffolding implementation
 *
 * Expected Implementation:
 *   - Leverage existing ControllerCreate, ModelCreate, ViewCreate commands
 *   - Generate RESTful controller methods (getIndex, getShow, getCreate, etc.)
 *   - Create model with basic table name configuration
 *   - Generate view templates with placeholder content
 *   - Optional database table creation integration
 *   - Possible CRUD method scaffolding in controller
 *
 * Use Cases (When Implemented):
 *   - Quick prototyping of new resources
 *   - Rapid application scaffolding
 *   - Teaching MVC patterns to new developers
 *   - Creating consistent file structure across resources
 *   - Speeding up development workflow
 *
 * Usage:
 *   php roline controller:complete Posts
 *   php roline controller:complete Users
 *   php roline controller:complete Products
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright 2015 - 2050 Geoffrey Okongo
 * @category Roline
 * @package Roline\Commands\Controller
 * @link https://github.com/glivers/roline
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 1.0.0
 */

class ControllerComplete extends ControllerCommand
{
    /**
     * Get command description for listing
     *
     * @return string Brief command description
     */
    public function description()
    {
        return 'Create controller, model, and views together';
    }

    /**
     * Get command usage syntax
     *
     * @return string Usage syntax showing required resource name
     */
    public function usage()
    {
        return '<name|required>';
    }

    /**
     * Execute complete resource scaffolding
     *
     * Creates controller, model, and views in a single operation for rapid
     * MVC resource generation. Includes index, show, create, and edit views.
     *
     * @param array $arguments Command arguments (resource name at index 0)
     * @return void Exits with status 1 on failure
     */
    public function execute($arguments)
    {
        // Validate resource name
        if (empty($arguments[0])) {
            $this->error('Resource name is required!');
            $this->line();
            $this->info('Usage: php roline controller:complete <ResourceName>');
            $this->line();
            $this->info('Example: php roline controller:complete Posts');
            exit(1);
        }

        $name = $this->validateName($arguments[0]);

        $this->line();
        $this->info("Creating complete MVC scaffold for: {$name}");
        $this->line();

        // Track created files for error cleanup
        $createdFiles = [];

        try {
            // 1. Create Controller
            $this->info("1. Creating controller...");
            $controllerResult = $this->createController($name);
            if ($controllerResult['success']) {
                $createdFiles[] = $controllerResult['path'];
                $this->success("   ✓ {$controllerResult['path']}");
            } else {
                throw new \Exception($controllerResult['error']);
            }

            // 2. Create Model
            $this->info("2. Creating model...");
            $modelResult = $this->createModel($name);
            if ($modelResult['success']) {
                $createdFiles[] = $modelResult['path'];
                $this->success("   ✓ {$modelResult['path']}");
            } else {
                throw new \Exception($modelResult['error']);
            }

            // 3. Create Views
            $this->info("3. Creating views...");
            $viewsResult = $this->createViews($name);
            if ($viewsResult['success']) {
                $createdFiles = array_merge($createdFiles, $viewsResult['paths']);
                foreach ($viewsResult['paths'] as $path) {
                    $this->success("   ✓ {$path}");
                }
            } else {
                throw new \Exception($viewsResult['error']);
            }

            // Success summary
            $this->line();
            $this->success("Complete scaffold created successfully!");
            $this->line();
            $this->info("Next steps:");
            $this->info("  1. Add properties to model: php roline model:append {$name}");
            $this->info("  2. Create database table: php roline model:create-table {$name}");
            $this->info("  3. Implement controller methods");
            $this->info("  4. Customize view templates");
            $this->line();

        } catch (\Exception $e) {
            // Rollback on error - delete created files
            $this->line();
            $this->error("Failed to create scaffold: " . $e->getMessage());
            $this->line();

            if (!empty($createdFiles)) {
                $this->info("Rolling back created files...");
                foreach ($createdFiles as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                        $this->line("  Deleted: {$file}");
                    }
                }
            }

            exit(1);
        }
    }

    /**
     * Create controller file
     *
     * @param string $name Controller name (without Controller suffix)
     * @return array Result with 'success', 'path', and optional 'error'
     */
    private function createController($name)
    {
        $path = $this->getControllerPath($name);

        if ($this->controllerExists($name)) {
            return ['success' => false, 'error' => "Controller already exists: {$path}"];
        }

        // Use existing stub
        $customStubPath = getcwd() . '/application/database/stubs/controller.stub';
        $defaultStubPath = __DIR__ . '/../../../stubs/controller.stub';
        $stubPath = file_exists($customStubPath) ? $customStubPath : $defaultStubPath;

        $stub = \Rackage\File::read($stubPath);
        if (!$stub->success) {
            return ['success' => false, 'error' => 'Controller stub file not found'];
        }

        $content = str_replace('{{ControllerName}}', $name, $stub->content);
        $content = str_replace('{{ControllerName|lowercase}}', strtolower($name), $content);

        $this->ensureControllersDir();
        $result = \Rackage\File::write($path, $content);

        if (!$result->success) {
            return ['success' => false, 'error' => $result->errorMessage];
        }

        return ['success' => true, 'path' => $path];
    }

    /**
     * Create model file
     *
     * @param string $name Model name (without Model suffix)
     * @return array Result with 'success', 'path', and optional 'error'
     */
    private function createModel($name)
    {
        $modelPath = "application/models/{$name}Model.php";

        if (\Rackage\File::exists($modelPath)->exists) {
            return ['success' => false, 'error' => "Model already exists: {$modelPath}"];
        }

        // Use existing stub
        $customStubPath = getcwd() . '/application/database/stubs/model.stub';
        $defaultStubPath = __DIR__ . '/../../../stubs/model.stub';
        $stubPath = file_exists($customStubPath) ? $customStubPath : $defaultStubPath;

        $stub = \Rackage\File::read($stubPath);
        if (!$stub->success) {
            return ['success' => false, 'error' => 'Model stub file not found'];
        }

        // Use improved pluralize from ModelCommand
        $tableName = strtolower($name) . 's'; // Simple pluralization for now

        $content = str_replace('{{ModelName}}', $name, $stub->content);
        $content = str_replace('{{TableName}}', $tableName, $content);

        \Rackage\File::ensureDir('application/models');
        $result = \Rackage\File::write($modelPath, $content);

        if (!$result->success) {
            return ['success' => false, 'error' => $result->errorMessage];
        }

        return ['success' => true, 'path' => $modelPath];
    }

    /**
     * Create view files (index, show, create, edit)
     *
     * @param string $name Resource name for views
     * @return array Result with 'success', 'paths' array, and optional 'error'
     */
    private function createViews($name)
    {
        $viewsDir = 'application/views/' . strtolower($name);
        $paths = [];

        // Ensure view directory exists
        \Rackage\File::ensureDir($viewsDir);

        $views = [
            'index' => $this->getIndexViewContent($name),
            'show' => $this->getShowViewContent($name),
            'create' => $this->getCreateViewContent($name),
            'edit' => $this->getEditViewContent($name)
        ];

        foreach ($views as $viewName => $content) {
            $viewPath = "{$viewsDir}/{$viewName}.php";

            if (file_exists($viewPath)) {
                return ['success' => false, 'error' => "View already exists: {$viewPath}"];
            }

            $result = \Rackage\File::write($viewPath, $content);
            if (!$result->success) {
                return ['success' => false, 'error' => $result->errorMessage];
            }

            $paths[] = $viewPath;
        }

        return ['success' => true, 'paths' => $paths];
    }

    /**
     * Get index view template content
     *
     * @param string $name Resource name
     * @return string View content
     */
    private function getIndexViewContent($name)
    {
        $lower = strtolower($name);
        return <<<EOT
<h1>{$name} - Index</h1>

<p><a href="{{ Url::base() }}{$lower}/create">Create New</a></p>

@loopelse(\${$lower}s as \$item)
    <div>
        <h2>{{ \$item->title }}</h2>
        <a href="{{ Url::base() }}{$lower}/show/{{ \$item->id }}">View</a>
        <a href="{{ Url::base() }}{$lower}/edit/{{ \$item->id }}">Edit</a>
    </div>
@empty
    <p>No {$lower} found.</p>
@endloop

EOT;
    }

    /**
     * Get show view template content
     *
     * @param string $name Resource name
     * @return string View content
     */
    private function getShowViewContent($name)
    {
        $lower = strtolower($name);
        return <<<EOT
<h1>{$name} - Show</h1>

<p><a href="{{ Url::base() }}{$lower}">Back to List</a></p>

<div>
    <h2>{{ \${$lower}->title }}</h2>
    <!-- Add more fields here -->
</div>

<p>
    <a href="{{ Url::base() }}{$lower}/edit/{{ \${$lower}->id }}">Edit</a>
</p>

EOT;
    }

    /**
     * Get create view template content
     *
     * @param string $name Resource name
     * @return string View content
     */
    private function getCreateViewContent($name)
    {
        $lower = strtolower($name);
        return <<<EOT
<h1>{$name} - Create</h1>

<p><a href="{{ Url::base() }}{$lower}">Back to List</a></p>

<form method="POST" action="{{ Url::base() }}{$lower}/store">
    <!-- Add your form fields here -->

    <button type="submit">Create</button>
</form>

EOT;
    }

    /**
     * Get edit view template content
     *
     * @param string $name Resource name
     * @return string View content
     */
    private function getEditViewContent($name)
    {
        $lower = strtolower($name);
        return <<<EOT
<h1>{$name} - Edit</h1>

<p><a href="{{ Url::base() }}{$lower}">Back to List</a></p>

<form method="POST" action="{{ Url::base() }}{$lower}/update/{{ \${$lower}->id }}">
    <!-- Add your form fields here -->

    <button type="submit">Update</button>
</form>

EOT;
    }
}
