<?php namespace Gliverich\Console;

use Gliverich\Console\Commands\Database;
use Symfony\Component\Console\Application;

class Loader {


	public function __construct(){

		$console = new Application();
		$console->add(new Database());
		$console->run();

	}

}

?>