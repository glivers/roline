<?php namespace Roline\Commands\Model;

/**
 * ModelCreate Command
 *
 * Generates a new model class from a stub template with pre-configured table
 * name, namespace, and static method structure. The generated model extends
 * Rackage\Model and is ready for immediate database operations.
 *
 * Features:
 *   - Auto-adds 'Model' suffix if not provided
 *   - Auto-generates plural table name
 *   - Checks for existing models to prevent overwriting
 *   - Supports custom stub templates
 *   - Creates models directory if needed
 *
 * Generated Model Structure:
 *   - Proper namespace (Models\)
 *   - Static table name property
 *   - Timestamp configuration
 *   - Extends Rackage\Model
 *
 * Usage:
 *   php roline model:create Post
 *   php roline model:create PostModel
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright 2015 - 2050 Geoffrey Okongo
 * @category Roline
 * @package Roline\Commands\Model
 * @link https://github.com/glivers/roline
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 1.0.0
 */

use Rackage\File;

class ModelCreate extends ModelCommand
{
    public function description()
    {
        return 'Create a new model class';
    }

    public function usage()
    {
        return '<Model|required>';
    }

    public function execute($arguments)
    {
        $name = $this->validateName($arguments[0] ?? null);

        // Prevent overwriting existing models
        if ($this->modelExists($name))
        {
            $path = $this->getModelPath($name);
            $this->error("Model already exists: {$path}");
            exit(1);
        }

        // Generate plural table name using simple pluralization
        // Example: 'Post' becomes 'posts', 'User' becomes 'users'
        $tableName = $this->pluralize($name);

        // Load stub template - custom stubs take priority over defaults
        // This allows developers to customize generated model structure
        $customStubPath = getcwd() . '/application/database/stubs/model.stub';
        $defaultStubPath = __DIR__ . '/../../../stubs/model.stub';

        $stubPath = file_exists($customStubPath) ? $customStubPath : $defaultStubPath;

        $stub = File::read($stubPath);

        if (!$stub->success)
        {
            $this->error('Model stub file not found');
            exit(1);
        }

        // Replace template placeholders with actual values
        $content = str_replace('{{ModelName}}', $name, $stub->content);
        $content = str_replace('{{TableName}}', $tableName, $content);

        // Ensure target directory exists before writing
        $this->ensureModelsDir();

        // Write the generated model file
        $path = $this->getModelPath($name);
        $result = File::write($path, $content);

        if ($result->success)
        {
            $this->success("Model created: {$path}");
            $this->info("Table name: {$tableName}");
        }
        else
        {
            $this->error("Failed to create model: {$result->errorMessage}");
            exit(1);
        }
    }
}
