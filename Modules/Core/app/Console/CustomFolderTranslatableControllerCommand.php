<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Nwidart\Modules\Support\Config\GenerateConfigReader;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Traits\ModuleCommandTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Nwidart\Modules\Commands\Make\GeneratorCommand;
use Illuminate\Support\Str;

class CustomFolderTranslatableControllerCommand extends GeneratorCommand
{
    use ModuleCommandTrait;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:make-custom-folder-translatable-controller';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';



    public function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        return $path . '/app/Http/Controllers/Backend' . '/' . $this->getControllerName() . 'Controller.php';
    }
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    protected function getTemplateContents()
    {

        return (new Stub($this->getStubName(), [
            'STUDLY_NAME'       => $this->argument('studly_name'),
            'TABLE_NAME' => $this->getControllerName(),
            'LOWER_NAME' => $this->argument('lower_name'),
            'CONTROLLER_DATA' =>  $this->argument('controller_data'),
            'CONTROLLER_VARIABLE' => $this->argument('controller_variable'),
            'CONTROLLER_STORE' =>  $this->argument('controller_store'),
            'MODULE_LOWER_NAME' => $this->argument('module_lower_name'),
            'CONTROLLER_EDIT_VARIABLE' => $this->argument('controller_edit_variable'),
            'CONTROLLER_UPDATE' => $this->argument('controller_update'),
            'CONTROLLER_INDEX' => $this->argument('controller_index'),
        ]))->render();
    }
    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [

            ['studly_name', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['lower_name', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['module', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['controller', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['controller_data', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['controller_variable', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['controller_store', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['module_lower_name', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['controller_edit_variable', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['controller_update', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['controller_index', InputArgument::REQUIRED, 'The name of module will be used.'],
        ];
    }
    /**
     * Get the console command options.
     *
     * @return array
     */

    protected function getControllerName()
    {
        $controller = Str::studly($this->argument('controller'));
        return $controller;
    }

    private function getStubName()
    {

        $stub = '/scaffold/translatable_folder-controller.stub';

        return $stub;
    }
}
