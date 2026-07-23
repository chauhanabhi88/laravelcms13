<?php

namespace Modules\Core\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Modules\Core\Generators\ModuleGenerator;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ModuleMakeCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:make';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new module.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $names = $this->argument('name');
        $columns = $this->option('columns');
        $translation = $this->option('translation');
        $softDelete = $this->option('soft-delete');
        $success = true;

        if (empty($names)) {
            $this->components->error('At least one module name is required.');

            return E_ERROR;
        }

        foreach ($names as $name) {
            $code = with(new ModuleGenerator($name, $columns, $translation, $softDelete))
                ->setFilesystem($this->laravel['files'])
                ->setModule($this->laravel['modules'])
                ->setConfig($this->laravel['config'])
                ->setActivator($this->laravel[ActivatorInterface::class])
                ->setConsole($this)
                ->setComponent($this->components)
                ->setForce($this->option('force'))
                ->setType($this->getModuleType())
                ->setActive(! $this->option('disabled'))
                ->setVendor($this->option('author-vendor'))
                ->setAuthor($this->option('author-name'), $this->option('author-email'))
                ->generate();

            if ($code === E_ERROR) {
                $success = false;
            }
        }

        if (! $success) {
            return E_ERROR;
        }

        $this->dumpAutoload();

        return 0;
    }

    /**
     * Refresh the autoloader so the new module's service providers are discovered.
     *
     * Only reached once every module generated successfully. `composer` is
     * frequently absent from PATH when Artisan runs under the web server, so a
     * failure here is reported rather than swallowed - the module exists, it
     * just will not boot until this is run by hand.
     */
    private function dumpAutoload(): void
    {
        try {
            // The default 60s Process timeout is not enough for a cold composer
            // run on a project this size, and run() *throws* on timeout rather
            // than returning an unsuccessful result - so this needs both a
            // longer limit and a catch, not just a successful() check.
            $result = Process::path(base_path())
                ->timeout(600)
                ->command('composer dump-autoload')
                ->run();

            if ($result->successful()) {
                return;
            }

            $detail = trim($result->errorOutput() ?: $result->output());
        } catch (\Throwable $e) {
            $detail = $e->getMessage();
        }

        $this->components->warn(
            'Could not run "composer dump-autoload" - the module was created, but run it '
            .'manually before the new module will be autoloaded.'
        );

        if ($detail !== '') {
            $this->components->warn($detail);
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::IS_ARRAY, 'The names of modules will be created.'],
        ];
    }

    protected function getOptions()
    {
        return [
            ['columns', null, InputOption::VALUE_OPTIONAL, 'The "##"-delimited column definitions to scaffold the entity from.'],
            ['translation', null, InputOption::VALUE_NONE, 'Generate a translatable module.'],
            ['soft-delete', null, InputOption::VALUE_NONE, 'Give the generated entity soft deletes.'],
            ['plain', 'p', InputOption::VALUE_NONE, 'Generate a plain module (without some resources).'],
            ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when the module already exists.'],
            ['api', null, InputOption::VALUE_NONE, 'Generate an api module.'],
            ['web', null, InputOption::VALUE_NONE, 'Generate a web module.'],
            ['disabled', 'd', InputOption::VALUE_NONE, 'Do not enable the module at creation.'],
            ['author-name', null, InputOption::VALUE_OPTIONAL, 'Author name.'],
            ['author-email', null, InputOption::VALUE_OPTIONAL, 'Author email.'],
            ['author-vendor', null, InputOption::VALUE_OPTIONAL, 'Author vendor.'],
        ];
    }

    /**
     * Get module type .
     *
     * @return string
     */
    private function getModuleType()
    {
        $isPlain = $this->option('plain');
        $isApi = $this->option('api');

        if ($isPlain && $isApi) {
            return 'web';
        }
        if ($isPlain) {
            return 'plain';
        } elseif ($isApi) {
            return 'api';
        } else {
            return 'web';
        }
    }
}
