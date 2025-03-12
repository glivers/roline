<?php namespace Roline\Console\Commands;

use Rackage\Drivers\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Model extends Command
{
    protected function configure()
    { 
        $this
            ->setName('model')
            ->setDescription('Generate model class templates and create and update table structures')
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Which model you want to create or update?'
            )
            ->addOption(
               'create',
               null,
               InputOption::VALUE_NONE,
               'Use this option to generate a model and table class template.'
            )
            ->addOption(
               'table:create',
               null,
               InputOption::VALUE_NONE,
               'Use this option to create the table related to this model into the database.'
            )
            ->addOption(
               'table:update',
               null,
               InputOption::VALUE_NONE,
               'Use this option to update the table structure associated with this model in the database.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        //define array to contain all the model names
        $models = array();

        //get the model directory
        $modelDirName = Registry::getConfig()['root'] . "/application/models";
        $tableDirName = Registry::getConfig()['root'] . "/application/database/Models";

        //create an instance of the Finder() class
        $finder = new Finder();

        //create an instance of the FileSystem Class
        $FileSystem = new Filesystem();

        //look for all files in this directory that comply with the rules defined
        $finder->files()->in($modelDirName)->name('*.php')->contains('extends')->notContains('use Drivers\Models\BaseModelClass');

        //loop through the result set getting the class names one at a time
        foreach ($finder as $file) {

            //add model name to the models array
            $models[str_replace('/', '\\', substr($file->getRelativePathname(), 0, -4))] = array(

                'RealPath' => $file->getRealpath(),
                'ResultObject' => $file

            );

        }


        //check if a model name was specified
        if( $name = $input->getArgument('name') ){

            //get file namespace
            $modelFileNamepath = str_replace('\\', '/', $name);

            //check if this is a create request
            if ($input->getOption('create')) {

                //check if this class already exists
                if( isset( $models[$name]) ){

                    $helper = $this->getHelper('question');
                    $question = new ConfirmationQuestion("\n\tA model with this name already exists! Do you want to overwrite it? (y/n)", false);

                    if (!$helper->ask($input, $output, $question)) {

                        //send this to the output
                        $output->writeln("\n\tCommand aborted---+++\n\tExit().");
                    }

                    else{

                        //get full paht
                        $fullPath = $modelDirName.'/'.$modelFileNamepath.'.php';
                        $tablePath = $tableDirName.'/'.$modelFileNamepath.'Table.php';
                        //check if this is a subnamespace
                        $strpos = (strpos($name, '\\')) ? '\\'.substr($name, 0, strrpos($name, '\\')) : '';
                        $className = (strpos($name, '\\')) ? substr($name, strrpos($name, '\\') + 1) : $name;
                        //get model template content
                        $template = file_get_contents(dirname(__FILE__).'/Templates/Models/Model.php');
                        $settings = Registry::getConfig();
                        //prefile with data
                        $modelString = sprintf(
                            $template, 
                            $strpos,
                            $settings['author'], 
                            $settings['copyright'], 
                            $name,
                            $settings['license'],
                            $settings['version'],
                            $className
                            );

                        //check if this is dir
                        if($FileSystem->exists($modelDirName.'/'.$strpos)){
                            $FileSystem->dumpFile($fullPath, $modelString);
                        }
                        else{
                            $FileSystem->mkdir($modelDirName.'/'.$strpos); 
                            $FileSystem->dumpFile($fullPath, $modelString);
                        }

                        //get table template
                        $tableString =  file_get_contents(dirname(__FILE__).'/Templates/Models/ModelTable.php');
                        $tableString = sprintf($tableString, $className);

                        //check if this is dir
                        if($FileSystem->exists($tableDirName.'/'.$strpos)){
                            $FileSystem->dumpFile($tablePath, $tableString);
                        }
                        else{
                            $FileSystem->mkdir($tableDirName.'/'.$strpos); 
                            $FileSystem->dumpFile($tablePath, $tableString);
                        }

                        //send this to the output
                        $output->writeln("\n\tCreate model $name---+++\n\tModel and table class creation success...!\n\tExit().");

                    }

                }

                //this is a completely new model, go ahead and create model/table class pairs
                else{
                    //get full paht
                    $fullPath = $modelDirName.'/'.$modelFileNamepath.'.php';
                    $tablePath = $tableDirName.'/'.$modelFileNamepath.'Table.php';
                    //check if this is a subnamespace
                    $strpos = (strpos($name, '\\')) ? '\\'.substr($name, 0, strrpos($name, '\\')) : '';
                    $className = (strpos($name, '\\')) ? substr($name, strrpos($name, '\\') + 1) : $name;
                    //get model template content
                    $template = file_get_contents(dirname(__FILE__).'/Templates/Models/Model.php');
                    $settings = Registry::getConfig();
                    //prefile with data
                    $modelString = sprintf(
                        $template, 
                        $strpos,
                        $settings['author'], 
                        $settings['copyright'], 
                        $name,
                        $settings['license'],
                        $settings['version'],
                        $className
                        );

                    //check if this is dir
                    if($FileSystem->exists($modelDirName.'/'.$strpos)){
                        $FileSystem->dumpFile($fullPath, $modelString);
                    }
                    else{
                        $FileSystem->mkdir($modelDirName.'/'.$strpos); 
                        $FileSystem->dumpFile($fullPath, $modelString);
                    }

                    //get table template
                    $tableString =  file_get_contents(dirname(__FILE__).'/Templates/Models/ModelTable.php');
                    $tableString = sprintf($tableString, $className);

                    //check if this is dir
                    if($FileSystem->exists($tableDirName.'/'.$strpos)){
                        $FileSystem->dumpFile($tablePath, $tableString);
                    }
                    else{
                        $FileSystem->mkdir($tableDirName.'/'.$strpos); 
                        $FileSystem->dumpFile($tablePath, $tableString);
                    }

                    //send this to the output
                    $output->writeln("\n\tCreate model $name---+++\n\tModel and table class creation success...!\n\tExit().");

                }

            }

            //this is an create model table request
            elseif ( $input->getOption('table:create') ) {

                //first, begin by checking if this model exists
                if( isset( $models[$name]) ){

                    if(class_exists('Models\\'.$name)){
                        
                        $modelClass = 'Models\\'.$name;
                        $modelClass::createTable();

                    }
                    else{
                        //send this to the output
                        $output->writeln("\n\tThe model class $name is undefined---+++\n\tExit().");

                    }

                }

                //this model is not defined yet
                else{

                    //send this to the output
                    $output->writeln("\n\tThe model class $name is undefined---+++\n\tExit().");

                }

            }

            //this is an update model table request
            elseif ( $input->getOption('table:update') ) {

                //first, begin by checking if this model exists
                if( isset( $models[$name]) ){

                    if(class_exists('Models\\'.$name)){
                        
                        $modelClass = 'Models\\'.$name;
                        $modelClass::updateTable();

                    }
                    else{
                        //send this to the output
                        $output->writeln("\n\tThe model class $name is undefined---+++\n\tExit().");

                    }

                }

                //this model is not defined yet
                else{

                    //send this to the output
                    $output->writeln("\n\tThe model class $name is undefined---+++\n\tExit().");

                }

            }

            //if none of the options were specified, list all the methods in this model class
            else{

                //first, begin by checking if this model exists
                if( isset( $models[$name]) ){

                    //send this to the output
                    $output->writeln("\n\tThe model class $name is already defined---+++\n\tExit().");


                }

                //this model is not defined yet
                else{

                    //send this to the output
                    $output->writeln("\n\tThe model class $name is undefined---+++\n\tExit().");

                }

            }

        }

        //model name not specified, just list all the models defined in this application
        else{

            //define string to contain controller names
            $modelNames = array_keys($models);

            //convert the key names to a string
            $modelNamesString = join("\n\t", $modelNames);

            //output string
            $outputString = "\nModel Class(es) [" . count($models) . "] \n\t" . $modelNamesString;
            
            //send to output stream
            $output->writeln($outputString);

        }

    }

}