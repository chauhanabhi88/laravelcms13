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

class CustomTranslatableEntityCommand extends GeneratorCommand
{
    use ModuleCommandTrait;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:make-custom-translatable-entity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';

    public function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        return $path . '/app/Models' . '/' . $this->getEntityName() . '.php';
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
            'TABLE_COLUMNS'     => $this->argument('table_columns'),
            'TRANSLATABLE_COLUMNS' => $this->argument('translatable_columns'),
            'FOREIGN_KEY' => $this->argument('lower_name') . '_id',
            'TABLE_NAME' => $this->getEntityName(),
            'SOFT_DELETE' => $this->argument('soft_delete'),
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
            ['entity', InputArgument::REQUIRED, 'An example argument.'],
            ['lower_name', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['studly_name', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['table_columns', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['translatable_columns', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['module', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['soft_delete', InputArgument::REQUIRED, 'The name of module will be used.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */

    protected function getEntityName()
    {
        $entity = Str::studly($this->argument('entity'));
        return $entity;
    }

    private function getStubName()
    {

        $stub = '/scaffold/translatable_entity.stub';

        return $stub;
    }
}
