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

class CustomEloquentCommand extends GeneratorCommand
{
    use ModuleCommandTrait;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:make-custom-eloquent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';

    public function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        return $path . '/app/Repositories/Eloquent' . '/Eloquent' . $this->getEloquentName() . 'Repository.php';
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
            'STUDLY_NAME'       => $this->argument('studly_name'),
            'MODULE_NAMESPACE'     => $this->argument('module_namespace'),
            'GRID_COLUMNS'        => $this->argument('grid_columns'),
            'FILTERS_OPTIONS'       => $this->argument('filters_options'),
            'PAGINATION'     => $this->argument('pagination'),
            'TABLE_NAME' => $this->getEloquentName(),
            'CACHE_KEY' => $this->argument('cache_key')
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
            ['studly_name', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['module_namespace', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['grid_columns', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['filters_options', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['pagination', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['module', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['eloquent', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['cache_key', InputArgument::REQUIRED, 'The name of module will be used.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */


    protected function getEloquentName()
    {
        $eloquent = Str::studly($this->argument('eloquent'));

        return $eloquent;
    }

    private function getStubName()
    {

        $stub = '/scaffold/eloquent.stub';

        return $stub;
    }
}
