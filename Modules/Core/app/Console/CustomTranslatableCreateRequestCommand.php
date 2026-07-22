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

class CustomTranslatableCreateRequestCommand extends GeneratorCommand
{
    use ModuleCommandTrait;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:make-custom-translatable-create-request';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';



    public function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        return $path . '/app/Http/Requests' . '/CreateRequest.php';
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
            'CREATE_TRANSLATABLE_RULES' => $this->argument('create_translatable_rules'),
            'REQUEST_FUNCTIONS' => $this->argument('request_functions'),
            'REQUEST_TRANSLATABLE_MESSAGES' =>  $this->argument('request_translatable_messages'),
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
            ['module', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['create_translatable_rules', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['request_functions', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['request_translatable_messages', InputArgument::REQUIRED, 'The name of module will be used.'],
        ];
    }
    /**
     * Get the console command options.
     *
     * @return array
     */


    private function getStubName()
    {

        $stub = '/scaffold/translatable_create-request.stub';

        return $stub;
    }
}
