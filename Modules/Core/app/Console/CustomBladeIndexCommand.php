<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Nwidart\Modules\Support\Config\GenerateConfigReader;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Traits\ModuleCommandTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Nwidart\Modules\Commands\Make\GeneratorCommand;

class CustomBladeIndexCommand extends GeneratorCommand
{
    use ModuleCommandTrait;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:make-custom-blade-index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';

    public function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        return $path . '/resources/views/backend' . '/' . $this->argument('lower_name') . '/index.blade.php';
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
            'LOWER_NAME'        => $this->argument('lower_name'),
            'MODULE_LOWER_NAME'        => $this->argument('module_lower_name'),
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
            ['lower_name', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['module_lower_name', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['module', InputArgument::REQUIRED, 'The name of module will be used.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */


    private function getStubName()
    {

        $stub = '/views/backend/folder-index.stub';

        return $stub;
    }
}
