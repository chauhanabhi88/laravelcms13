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

class CustomCreateRequestCommand extends GeneratorCommand
{
    use ModuleCommandTrait;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:make-custom-create-request';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';



    public function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        return $path . '/app/Http/Requests' . '/Create' . $this->getTableName() . 'Request.php';
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
            'TABLE_NAME' => $this->getTableName(),
            'LOWER_NAME' => $this->argument('lower_name'),
            'CREATE_RULES' => $this->argument('create_rules'),
            'REQUEST_FUNCTIONS' => $this->argument('request_functions'),
            'REQUEST_MESSAGES' =>  $this->argument('request_messages'),
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
            ['request', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['lower_name', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['create_rules', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['request_functions', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['request_messages', InputArgument::REQUIRED, 'The name of module will be used.'],
        ];
    }
    /**
     * Get the console command options.
     *
     * @return array
     */

    protected function getTableName()
    {
        $request = Str::studly($this->argument('request'));
        return $request;
    }

    private function getStubName()
    {

        $stub = '/scaffold/folder-create-request.stub';

        return $stub;
    }
}
