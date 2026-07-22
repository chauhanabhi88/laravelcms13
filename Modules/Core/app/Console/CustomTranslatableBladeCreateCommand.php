<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Nwidart\Modules\Support\Config\GenerateConfigReader;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Traits\ModuleCommandTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Nwidart\Modules\Commands\Make\GeneratorCommand;

class CustomTranslatableBladeCreateCommand extends GeneratorCommand
{
    use ModuleCommandTrait;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:make-custom-translatable-blade-create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';

    public function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        return $path . '/resources/views/backend/create.blade.php';
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
            'SCRIPTS' => $this->argument('scripts'),
            'CREATE_FIELDS' => $this->argument('create_fields'),
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
            ['module', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['scripts', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['create_fields', InputArgument::REQUIRED, 'The name of module will be used.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */


    private function getStubName()
    {

        $stub = '/views/backend/translatable_create.stub';

        return $stub;
    }
}
