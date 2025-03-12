<?php namespace Roline\Console;

/**
 *This class loads the classes that handle gliverich command line tools
 *
 *@author Geoffrey Okongo <code@rachie.dev>
 *@copyright 2015 - 2030 Geoffrey Okongo
 *@category Roline
 *@package Roline\Console\Loader
 *@link https://github.com/glivers/roline
 *@license http://opensource.org/licenses/MIT MIT License
 *@version 1.0.1
 */

use Roline\Console\Commands\Controller;
use Roline\Console\Commands\Model;
use Symfony\Component\Console\Application;

class Loader {


	public function __construct(){

		$console = new Application();
		$console->add(new Controller());
		$console->add(new Model());
		$console->run();

	}

}

?>