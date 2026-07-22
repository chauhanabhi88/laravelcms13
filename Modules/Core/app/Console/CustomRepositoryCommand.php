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

class CustomRepositoryCommand extends GeneratorCommand
{
    use ModuleCommandTrait;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:make-custom-repository';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';



    public function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        return $path . '/app/Repositories' . '/' . $this->getRepositoryName() . 'Repository.php';
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
            'MODULE_NAMESPACE' =>  $this->argument('module_namespace'),
            'TABLE_NAME' => $this->getRepositoryName(),
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
            ['module_namespace', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['module', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['repository', InputArgument::REQUIRED, 'The name of module will be used.'],
        ];
    }
    /**
     * Get the console command options.
     *
     * @return array
     */

    protected function getRepositoryName()
    {
        $repository = Str::studly($this->argument('repository'));
        return $repository;
    }

    private function getStubName()
    {

        $stub = '/scaffold/repository.stub';

        return $stub;
    }
}
