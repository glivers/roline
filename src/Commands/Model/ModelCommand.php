<?php namespace Roline\Commands\Model;

/**
 * ModelCommand - Base class for all model commands
 *
 * Provides shared functionality for model-related CLI commands including
 * name validation, path resolution, existence checking, directory setup,
 * and table name generation. All model commands (create, delete) extend
 * this class to avoid code duplication.
 *
 * Shared Functionality:
 *   - Name normalization (removes 'Model' suffix if provided)
 *   - Path resolution to application/models/
 *   - Existence validation
 *   - Directory creation
 *   - Table name pluralization
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright 2015 - 2050 Geoffrey Okongo
 * @category Roline
 * @package Roline\Commands\Model
 * @link https://github.com/glivers/roline
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 1.0.0
 */

use Roline\Command;
use Rackage\File;

abstract class ModelCommand extends Command
{
    /**
     * Validate and normalize model name
     *
     * Ensures a model name is provided and removes the 'Model' suffix
     * if the user included it. This allows both 'User' and 'UserModel'
     * as valid input.
     *
     * @param string|null $name Model name from user input
     * @return string Normalized name without 'Model' suffix
     */
    protected function validateName($name)
    {
        if (!$name)
        {
            $this->error('Model name is required');
            exit(1);
        }

        // Remove 'Model' suffix if user provided it
        // Example: 'UserModel' becomes 'User'
        return str_replace('Model', '', $name);
    }

    /**
     * Build full path to model file
     *
     * @param string $name Normalized model name (without 'Model' suffix)
     * @return string Full relative path (e.g., 'application/models/UserModel.php')
     */
    protected function getModelPath($name)
    {
        return "application/models/{$name}Model.php";
    }

    /**
     * Check if model file already exists
     *
     * @param string $name Normalized model name
     * @return bool True if model file exists, false otherwise
     */
    protected function modelExists($name)
    {
        $path = $this->getModelPath($name);
        return File::exists($path)->exists;
    }

    /**
     * Ensure the models directory exists
     *
     * Creates application/models/ directory if it doesn't exist.
     * Called before writing new model files.
     *
     * @return void
     */
    protected function ensureModelsDir()
    {
        File::ensureDir('application/models');
    }

    /**
     * Generate plural table name from model name
     *
     * Uses simple pluralization by adding 's' to the lowercase model name.
     * This works for most English nouns but has known limitations for
     * irregular plurals (e.g., 'Person' → 'persons' not 'people').
     * Users can override the table name in their model if needed.
     *
     * @param string $name Model name (e.g., 'User', 'Post', 'Category')
     * @return string Plural table name (e.g., 'users', 'posts', 'categorys')
     */
    protected function pluralize($name)
    {
        return strtolower($name) . 's';
    }
}
