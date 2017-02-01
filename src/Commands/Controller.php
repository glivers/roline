<?php namespace Gliverich\Console\Commands;

/**
 *This class defines command options that are available for controller command
 *
 *@author Geoffrey Oliver <geoffrey.oliver2@gmail.com>
 *@copyright 2015 - 2020 Geoffrey Oliver
 *@category Gliverich
 *@package Gliverich\Console\Command\Controller
 *@link https://github.com/gliver-mvc/console
 *@license http://opensource.org/licenses/MIT MIT License
 *@version 1.0.1
 */

use Drivers\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Controller extends Command
{
    protected function configure()
    { 
        $this
            ->setName('controller')
            ->setDescription('Create controllers class and method templates, with associated views and models.')
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'What is the name of the controller class that you would like to create or modify?'
            )
            ->addArgument(
                    'methods',
                    InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                    'What methods would you like to create  for this controller class (separate multiple method names with a space)?'
                )
            ->addOption(
               'create',
               null,
               InputOption::VALUE_NONE,
               'Specify this option when you are creating a controller class for the first time.'
            )
            ->addOption(
               'append',
               null,
               InputOption::VALUE_NONE,
               'Specify this option when you would like to add methods to a controller class that already exists.'
            )
            ->addOption(
               'complete',
               null,
               InputOption::VALUE_NONE,
               'Specify this option if you want to create a controller class together with associated model, table and views all in once command.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        //define array to contain all the controllers names
        $controllers = array();

        //get the controllers directory
        $controllerDirName = Registry::getConfig()['root'] . "/application/controllers";

        //create an instance of the Finder() class
        $finder = new Finder();

        //create an instance of the FileSystem Class
        $FileSystem = new Filesystem();

        //look for all files in this directory that comply with the rules defined
        $finder->files()->in($controllerDirName)->name('*.php')->contains('extends')->notContains('use Drivers\Controllers\Implementation');

        //loop through the result set getting the class names one at a time
        foreach ($finder as $file) {

            //add controller name to the controller array
            $controllers[str_replace('/', '\\', substr($file->getRelativePathname(), 0, -14))] = array(

                'RealPath' => $file->getRealpath(),
                'ResultObject' => $file

            );

        }

        //check if a controller name was specified
        if( $name = $input->getArgument('name') ){

            //check if this is a create request
            if ($input->getOption('create')) {

                if(isset( $controllers[$name])){

                    $helper = $this->getHelper('question');
                    $question = new ConfirmationQuestion("\n\tA controller with this name already exists! Do you want to overwrite it? (y/n)", false);

                    if (!$helper->ask($input, $output, $question)) {

                        //send this to the output
                        $output->writeln("\n\tCommand aborted---+++\n\tExit().");
                    }

                    else{

                        //check if the methods to be appended were provided
                        if( $methods = $input->getArgument('methods') ){

                            //get full paht
                            $fullPath = $controllerDirName.'/'.str_replace('\\', '/', $name).'Controller.php';
                            //check if this is a subnamespace
                            $strpos = (strpos($name, '\\')) ? '\\'.substr($name, 0, strrpos($name, '\\')) : '';
                            $className = (strpos($name, '\\')) ? substr($name, strrpos($name, '\\') + 1) : $name;
                            //get model template content
                            $templateHead = file_get_contents(dirname(__FILE__).'/Templates/Controllers/ControllerHeader.php');
                            $settings = Registry::getConfig();
                            //prefile with data
                            $headerString = sprintf(
                                $templateHead, 
                                $strpos,
                                $settings['author'], 
                                $settings['copyright'], 
                                $name,
                                $settings['license'],
                                $settings['version'],
                                $className
                                );

                            //compose method template code
                            $template = file_get_contents(dirname(__FILE__).'/Templates/Controllers/ControllerMain.php');
                            //define variable to contain appended method contents
                            $methodAppendArray = array();

                            //loop through the methods composing the method content
                            foreach($methods as $methodName ){

                                //compose the method append string
                                $methodAppendArray[] = sprintf($template,ucfirst(strtolower($methodName)), ucfirst(strtolower($methodName)));

                            } 

                            //append the new file contents
                            $contentString = $headerString.join(' ', $methodAppendArray).'}';


                            //check if this is dir
                            if($FileSystem->exists($controllerDirName.'/'.$strpos)){
                                $FileSystem->dumpFile($fullPath, $contentString);
                            }
                            else{
                                $FileSystem->mkdir($controllerDirName.'/'.$strpos); 
                                $FileSystem->dumpFile($fullPath, $contentString);
                            }

                            //send this to the output
                            $output->writeln("\n\tCreating controller $name...\n\tAppending new methods to contoller class...\n\tSuccess---+++\n\tExit().");

                        }

                        else{

                            //get full paht
                            $fullPath = $controllerDirName.'/'.str_replace('\\', '/', $name).'Controller.php';
                            //check if this is a subnamespace
                            $strpos = (strpos($name, '\\')) ? '\\'.substr($name, 0, strrpos($name, '\\')) : '';
                            $className = (strpos($name, '\\')) ? substr($name, strrpos($name, '\\') + 1) : $name;
                            //get model template content
                            $templateHead = file_get_contents(dirname(__FILE__).'/Templates/Controllers/ControllerHeader.php');
                            $settings = Registry::getConfig();
                            //prefile with data
                            $headerString = sprintf(
                                $templateHead, 
                                $strpos,
                                $settings['author'], 
                                $settings['copyright'], 
                                $name,
                                $settings['license'],
                                $settings['version'],
                                $className
                                );

                            //compose method template code
                            $template = file_get_contents(dirname(__FILE__).'/Templates/Controllers/ControllerMain.php');

                            //loop through the methods composing the method content
                             $methodName = 'Index';

                            //compose the method append string
                            $methodAppendArray = sprintf($template,ucfirst(strtolower($methodName)), ucfirst(strtolower($methodName)));


                            //append the new file contents
                            $contentString = $headerString.$methodAppendArray.'}';


                            //check if this is dir
                            if($FileSystem->exists($controllerDirName.'/'.$strpos)){
                                $FileSystem->dumpFile($fullPath, $contentString);
                            }
                            else{
                                $FileSystem->mkdir($controllerDirName.'/'.$strpos); 
                                $FileSystem->dumpFile($fullPath, $contentString);
                            }

                            //send this to the output
                            $output->writeln("\n\tCreating controller $name...\n\tAppending new methods to contoller class...\n\tSuccess---+++\n\tExit().");

                        }

                    }

                }
                //this is a completely new controller, go ahead and get parameters and create controller class
                else{

                    //check if the methods to be appended were provided
                    if( $methods = $input->getArgument('methods') ){

                        //get full paht
                        $fullPath = $controllerDirName.'/'.str_replace('\\', '/', $name).'Controller.php';
                        //check if this is a subnamespace
                        $strpos = (strpos($name, '\\')) ? '\\'.substr($name, 0, strrpos($name, '\\')) : '';
                        $className = (strpos($name, '\\')) ? substr($name, strrpos($name, '\\') + 1) : $name;
                        //get model template content
                        $templateHead = file_get_contents(dirname(__FILE__).'/Templates/Controllers/ControllerHeader.php');
                        $settings = Registry::getConfig();
                        //prefile with data
                        $headerString = sprintf(
                            $templateHead, 
                            $strpos,
                            $settings['author'], 
                            $settings['copyright'], 
                            $name,
                            $settings['license'],
                            $settings['version'],
                            $className
                            );

                        //compose method template code
                        $template = file_get_contents(dirname(__FILE__).'/Templates/Controllers/ControllerMain.php');
                        //define variable to contain appended method contents
                        $methodAppendArray = array();

                        //loop through the methods composing the method content
                        foreach($methods as $methodName ){

                            //compose the method append string
                            $methodAppendArray[] = sprintf($template,ucfirst(strtolower($methodName)), ucfirst(strtolower($methodName)));

                        } 

                        //append the new file contents
                        $contentString = $headerString.join(' ', $methodAppendArray).'}';


                        //check if this is dir
                        if($FileSystem->exists($controllerDirName.'/'.$strpos)){
                            $FileSystem->dumpFile($fullPath, $contentString);
                        }
                        else{
                            $FileSystem->mkdir($controllerDirName.'/'.$strpos); 
                            $FileSystem->dumpFile($fullPath, $contentString);
                        }

                        //send this to the output
                        $output->writeln("\n\Creating controller $name...\n\tAppending new methods to contoller class...\n\tSuccess---+++\n\tExit().");

                    }

                    else{

                        //get full paht
                        $fullPath = $controllerDirName.'/'.str_replace('\\', '/', $name).'Controller.php';
                        //check if this is a subnamespace
                        $strpos = (strpos($name, '\\')) ? '\\'.substr($name, 0, strrpos($name, '\\')) : '';
                        $className = (strpos($name, '\\')) ? substr($name, strrpos($name, '\\') + 1) : $name;
                        //get model template content
                        $templateHead = file_get_contents(dirname(__FILE__).'/Templates/Controllers/ControllerHeader.php');
                        $settings = Registry::getConfig();
                        //prefile with data
                        $headerString = sprintf(
                            $templateHead, 
                            $strpos,
                            $settings['author'], 
                            $settings['copyright'], 
                            $name,
                            $settings['license'],
                            $settings['version'],
                            $className
                            );

                        //compose method template code
                        $template = file_get_contents(dirname(__FILE__).'/Templates/Controllers/ControllerMain.php');

                        //loop through the methods composing the method content
                         $methodName = 'Index';

                        //compose the method append string
                        $methodAppendArray = sprintf($template,ucfirst(strtolower($methodName)), ucfirst(strtolower($methodName)));


                        //append the new file contents
                        $contentString = $headerString.$methodAppendArray.'}';


                        //check if this is dir
                        if($FileSystem->exists($controllerDirName.'/'.$strpos)){
                            $FileSystem->dumpFile($fullPath, $contentString);
                        }
                        else{
                            $FileSystem->mkdir($controllerDirName.'/'.$strpos); 
                            $FileSystem->dumpFile($fullPath, $contentString);
                        }

                        //send this to the output
                        $output->writeln("\n\tCreating controller $name...\n\tAppending new methods to contoller class...\n\tSuccess---+++\n\tExit().");

                    }

                }

            }

            //this is an append request
            elseif ( $input->getOption('append') ) {

                //first, begin by checking if this controller exists
                if( isset( $controllers[$name]) ){

                    //check if the methods to be appended were provided
                    if( $methods = $input->getArgument('methods') ){

                        //compose method template code
                        $template = file_get_contents(dirname(__FILE__).'/Templates/Controllers/ControllerMain.php');

                        //define variable to contain appended method contents
                        $methodAppendArray = array();

                        //loop through the methods composing the method content
                        foreach($methods as $methodName ){

                            //compose the method append string
                            $methodAppendArray[] = sprintf($template,ucfirst(strtolower($methodName)), ucfirst(strtolower($methodName)));

                        } 

                        //get the result object for this controller from the controllers array
                        $ResultObject = $controllers[$name]['ResultObject'];

                        //get the contents of this file
                        $FileContents = $ResultObject->getContents();

                        //file the position of the last closing curly brace in the file contents
                        $pos = strrpos($FileContents, '}');

                        //check if closing tag was found
                        if($pos !== false){

                            //append the new file contents
                            $FileContents = substr_replace($FileContents, join(' ', $methodAppendArray), $pos, strlen('}'))."\n}";

                            //enclose this action in a try catch block to be able to handle errors
                            try {

                                //append contents to controller in style...
                                $FileSystem->dumpFile( $controllers[$name]['RealPath'], $FileContents);

                                //send this to the output
                                $output->writeln("\n\tAppending methods to $name...\n\tAppending new methods to class successful---+++\n\tExit().");

                            } 
                            catch (IOExceptionInterface $e) {

                                //send this to the output
                                $output->writeln("\n\tAn error occured while appending methods to your controller class---+++\n\tExit().");

                            }


                        }

                        //the last closing brace was not found, seems like this file has syntax errors
                        else{

                            //write to the output with error message
                            $output->writeln("\n\tSeems like your controller class $name has syntax errors---+++!\n\tFix and try again...\n\tExit().");

                        }

                    }

                    //there were not methods provided to be appended, write error message to output
                    else{

                        //send out put with missign methods message to the console
                        $output->writeln("\n\rNo methods to append to Controller $name ---+++\n\tExit().");

                    }


                }

                //this controller is not defined yet
                else{

                    //send to output stream
                    $output->writeln("\n\tController named '$name' is undefined---+++\n\tExit().");

                }

            }

            //if none of the options were specified, list all the methods in this controller class
            else{

                //first, begin by checking if this controller exists
                if( isset( $controllers[$name]) ){

                    //compose output string
                    $outputString = "\n\tController named '$name'   is already defined---+++\n\tExit().";

                    //send to output stream
                    $output->writeln($outputString);

                }

                //this controller is not defined yet
                else{

                    //compose output string
                    $outputString = "\n\tController named '$name'   is undefined---+++\n\tExit().";

                    //send to output stream
                    $output->writeln($outputString);

                }

            }

        }

        //controller name not specified, just list all the controllers and their respective methods currently defined in this application
        else{

            //define string to contain controller names
            $controllerNames = array_keys($controllers);

            //convert the key names to a string
            $controllerNamesString = join("\n", $controllerNames);

            //output string
            $outputString = "\nController Class(es) [" . count($controllers) . "] \n\t" . $controllerNamesString;
            
            //send to output stream
            $output->writeln($outputString);

        }

    }

}