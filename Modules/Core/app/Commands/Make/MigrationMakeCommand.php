<?php

namespace Modules\Core\Commands\Make;

use Illuminate\Support\Str;
use Modules\Core\Support\Migrations\SchemaParser;
use Nwidart\Modules\Commands\Make\GeneratorCommand;
use Nwidart\Modules\Support\Config\GenerateConfigReader;
use Nwidart\Modules\Support\Migrations\NameParser;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Traits\ModuleCommandTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MigrationMakeCommand extends GeneratorCommand
{
    use ModuleCommandTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:make-migration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new migration for the specified module.';

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The migration name will be created.'],
            ['module', InputArgument::OPTIONAL, 'The name of module will be created.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['fields', null, InputOption::VALUE_OPTIONAL, 'The specified fields table.', null],
            ['plain', null, InputOption::VALUE_NONE, 'Create plain migration.'],
        ];
    }

    /**
     * Get schema parser.
     *
     * @return SchemaParser
     */
    public function getSchemaParser()
    {
        return new SchemaParser($this->option('fields'));
    }

    /**
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function getTemplateContents()
    {
        $parser = new NameParser($this->argument('name'));
        $schema = $this->getSchemaParser();
        $table = $parser->getTableName();
        $entityName = $schema->getEntityName($table);

        if ($parser->isCreate()) {
            return Stub::create('/migration/create.stub', [
                'class' => $this->getClass(),
                'table' => $table,
                'fields' => $schema->render(),
                'foreign' => $schema->foreignKeyDown($table),
                'MODULE_NAME' => $this->getModuleName(),
                'ENTITY' => $entityName,
            ]);
        } elseif ($parser->isAdd()) {
            return Stub::create('/migration/add.stub', [
                'class' => $this->getClass(),
                'table' => $table,
                'fields_up' => $schema->up(),
                'fields_down' => $schema->down(),
                'foreign' => $schema->foreignKeyDown($table),
                'MODULE_NAME' => $this->getModuleName(),
                'ENTITY' => $entityName,
            ]);
        } elseif ($parser->isDelete()) {
            return Stub::create('/migration/delete.stub', [
                'class' => $this->getClass(),
                'table' => $table,
                'fields_down' => $schema->up(),
                'fields_up' => $schema->down(),
                'foreign' => $schema->getForeignKeys($table),
                'MODULE_NAME' => $this->getModuleName(),
                'ENTITY' => $entityName,
            ]);
        } elseif ($parser->isDrop()) {
            return Stub::create('/migration/drop.stub', [
                'class' => $this->getClass(),
                'table' => $table,
                'fields' => $schema->render(),
                'MODULE_NAME' => $this->getModuleName(),
                'ENTITY' => $entityName,
            ]);
        }

        return Stub::create('/migration/plain.stub', [
            'class' => $this->getClass(),
        ]);
    }

    /**
     * @return mixed
     */
    protected function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        $generatorPath = GenerateConfigReader::read('migration');

        return $path.$generatorPath->getPath().'/'.$this->getFileName().'.php';
    }

    /**
     * @return string
     */
    private function getFileName()
    {
        return date('Y_m_d_His_').$this->getSchemaName();
    }

    /**
     * @return array|string
     */
    private function getSchemaName()
    {
        return $this->argument('name');
    }

    /**
     * @return string
     */
    private function getClassName()
    {
        return Str::studly($this->argument('name'));
    }

    public function getClass()
    {
        return $this->getClassName();
    }

    /**
     * Run the command.
     */
    public function handle(): int
    {
        $this->components->info('Creating migration...');

        if (parent::handle() === E_ERROR) {
            return E_ERROR;
        }

        return 0;
    }
}
