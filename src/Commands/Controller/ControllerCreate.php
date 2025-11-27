<?php namespace Roline\Commands\Controller;

/**
 * ControllerCreate Command
 *
 * Generates a new controller class from a stub template with RESTful CRUD methods.
 * The generated controller includes HTTP method-prefixed actions (get, post, put,
 * delete, patch) and view rendering examples, ready for immediate use.
 *
 * Features:
 *   - Auto-adds 'Controller' suffix if not provided
 *   - Checks for existing controllers to prevent overwriting
 *   - Supports custom stub templates
 *   - Creates controllers directory if needed
 *
 * Generated Methods:
 *   - getIndex()    - List resources
 *   - getCreate()   - Show create form
 *   - postStore()   - Store new resource
 *   - getShow($id)  - Show single resource
 *   - getEdit($id)  - Show edit form
 *   - putUpdate($id)    - Update resource
 *   - deleteDestroy($id) - Delete resource
 *
 * Usage:
 *   php roline controller:create Posts
 *   php roline controller:create PostsController
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

class ControllerCreate extends ControllerCommand
{
    public function description()
    {
        return 'Create a new controller class';
    }

    public function usage()
    {
        return '<Controller|required>';
    }

    public function help()
    {
        parent::help();

        Output::info('Arguments:');
        Output::line('  <Controller|required>  Name of the controller class');
        Output::line('                         The "Controller" suffix is added automatically');
        Output::line('                         Example: Posts becomes PostsController');
        Output::line();

        Output::info('Examples:');
        Output::line('  php roline controller:create Posts');
        Output::line('  php roline controller:create Users');
        Output::line('  php roline controller:create BlogPosts');
        Output::line();

        Output::info('Creates:');
        Output::line('  application/controllers/PostsController.php');
        Output::line();

        Output::info('Generated File:');
        Output::line('  - RESTful controller with CRUD methods (getIndex, getCreate, postStore, etc.)');
        Output::line('  - HTTP method prefixes (get, post, put, delete, patch)');
        Output::line('  - View rendering examples');
        Output::line();
    }

    public function execute($arguments)
    {
        $name = $this->validateName($arguments[0] ?? null);

        // Prevent overwriting existing controllers
        if ($this->controllerExists($name))
        {
            $path = $this->getControllerPath($name);
            $this->error("Controller already exists: {$path}");
            exit(1);
        }

        // Load stub template - custom stubs take priority over defaults
        // This allows developers to customize generated code structure
        $customStubPath = getcwd() . '/application/database/stubs/controller.stub';
        $defaultStubPath = __DIR__ . '/../../../stubs/controller.stub';

        $stubPath = file_exists($customStubPath) ? $customStubPath : $defaultStubPath;

        $stub = File::read($stubPath);

        if (!$stub->success)
        {
            $this->error('Controller stub file not found');
            exit(1);
        }

        // Replace template placeholders with actual controller name
        $content = str_replace('{{ControllerName}}', $name, $stub->content);
        $content = str_replace('{{ControllerName|lowercase}}', strtolower($name), $content);

        // Ensure target directory exists before writing
        $this->ensureControllersDir();

        // Write the generated controller file
        $path = $this->getControllerPath($name);
        $result = File::write($path, $content);

        if ($result->success)
        {
            $this->success("Controller created: {$path}");
        }
        else
        {
            $this->error("Failed to create controller: {$result->errorMessage}");
            exit(1);
        }
    }
}
