<?php

namespace Modules\Core\Generators;

use Illuminate\Config\Repository as Config;
use Illuminate\Console\Command as Console;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\FileRepository;
use Nwidart\Modules\Support\Config\GenerateConfigReader;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Generators\Generator;
use Nwidart\Modules\Traits\PathNamespace;

class ModuleGenerator extends Generator
{
    use PathNamespace;

    /**
     * The module name will created.
     *
     * @var string
     */
    protected $name;
    protected $column;
    protected $translation;
    protected $softDelete;

    /**
     * The laravel config instance.
     *
     * @var Config
     */
    protected $config;

    /**
     * The laravel filesystem instance.
     *
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * The laravel console instance.
     *
     * @var Console
     */
    protected $console;

    /**
     * The laravel component Factory instance.
     *
     * @var \Illuminate\Console\View\Components\Factory
     */
    protected $component;

    /**
     * The activator instance
     *
     * @var ActivatorInterface
     */
    protected $activator;

    /**
     * The module instance.
     *
     * @var \Nwidart\Modules\Module
     */
    protected $module;

    /**
     * Force status.
     *
     * @var bool
     */
    protected $force = false;

    /**
     * set default module type.
     *
     * @var string
     */
    protected $type = 'web';

    /**
     * Enables the module.
     *
     * @var bool
     */
    protected $isActive = false;

    /**
     * Module author
     *
     * @var array
     */
    protected array $author = [
        'name', 'email',
    ];

    /**
     * Vendor name
     *
     * @var string
     */
    protected ?string $vendor = null;

    /**
     * The constructor.
     * @param $name
     * @param FileRepository $module
     * @param Config     $config
     * @param Filesystem $filesystem
     * @param Console    $console
     */
    public function __construct(
        $name,
        $columns,
        $translation,
        $softDelete,
        FileRepository $module = null,
        Config $config = null,
        Filesystem $filesystem = null,
        Console $console = null,
        ActivatorInterface $activator = null
    ) {
        $this->name = $name;
        $this->column = $columns;
        $this->translation = $translation;
        $this->softDelete = $softDelete;
        $this->config = $config;
        $this->filesystem = $filesystem;
        $this->console = $console;
        $this->module = $module;
        $this->activator = $activator;
    }

    /**
     * Set type.
     *
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set active flag.
     *
     * @param bool $active
     *
     * @return $this
     */
    public function setActive(bool $active)
    {
        $this->isActive = $active;

        return $this;
    }

    /**
     * Get the name of module will created. By default in studly case.
     *
     * @return string
     */
    public function getName()
    {
        return Str::studly($this->name);
    }

    public function getColumns()
    {
        if (!$this->column) {
            return null;
        }
        $array = [];
        $data = [];
        $string = $this->column;
        $array = explode("##", $string);
        foreach ($array as $key) {
            $value = [];
            $value = explode(";", $key);
            $data[str_replace(' ', '_', strtolower($value[0]))]['column'] = $value[0];
            if ($value[1] != 'Null') {
                $data[str_replace(' ', '_', strtolower($value[0]))]['filter'] = $value[1];
            }
            if ($value[2] != 'Null') {
                $data[str_replace(' ', '_', strtolower($value[0]))]['grid'] = $value[2];
            }
            if ($value[3] != 'Null') {
                $data[str_replace(' ', '_', strtolower($value[0]))]['type'] = $value[3];
            }
            if ($value[4] != 'Null') {
                $data[str_replace(' ', '_', strtolower($value[0]))]['required'] = $value[4];
            }
            if ($value[5] != 'Null') {
                $data[str_replace(' ', '_', strtolower($value[0]))]['image'] = $value[5];
            }
            if ($value[6] != 'Null') {
                $data[str_replace(' ', '_', strtolower($value[0]))]['unique'] = $value[6];
            }
            if ($value[7] != 'Null') {
                $data[str_replace(' ', '_', strtolower($value[0]))]['translation'] = $value[7];
            }
        }
        return $data;
    }

    /**
     * Get the laravel config instance.
     *
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Set the laravel config instance.
     *
     * @param Config $config
     *
     * @return $this
     */
    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Set the modules activator
     *
     * @param ActivatorInterface $activator
     *
     * @return $this
     */
    public function setActivator(ActivatorInterface $activator)
    {
        $this->activator = $activator;

        return $this;
    }

    /**
     * Get the laravel filesystem instance.
     *
     * @return Filesystem
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * Set the laravel filesystem instance.
     *
     * @param Filesystem $filesystem
     *
     * @return $this
     */
    public function setFilesystem($filesystem)
    {
        $this->filesystem = $filesystem;

        return $this;
    }

    /**
     * Get the laravel console instance.
     *
     * @return Console
     */
    public function getConsole()
    {
        return $this->console;
    }

    /**
     * Set the laravel console instance.
     *
     * @param Console $console
     *
     * @return $this
     */
    public function setConsole($console)
    {
        $this->console = $console;

        return $this;
    }

    /**
     * @return \Illuminate\Console\View\Components\Factory
     */
    public function getComponent(): \Illuminate\Console\View\Components\Factory
    {
        return $this->component;
    }

    /**
     * @param \Illuminate\Console\View\Components\Factory $component
     */
    public function setComponent(\Illuminate\Console\View\Components\Factory $component): self
    {
        $this->component = $component;

        return $this;
    }

    /**
     * Get the module instance.
     *
     * @return \Nwidart\Modules\Module
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * Set the module instance.
     *
     * @param mixed $module
     *
     * @return $this
     */
    public function setModule($module)
    {
        $this->module = $module;

        return $this;
    }

    /**
     * Setting the author from the command
     *
     * @param string|null $name
     * @param string|null $email
     * @return $this
     */
    public function setAuthor(string $name = null, string $email = null)
    {
        $this->author['name'] = $name;
        $this->author['email'] = $email;

        return $this;
    }

    /**
     * Installing vendor from the command
     *
     * @param string|null $vendor
     * @return $this
     */
    public function setVendor(string $vendor = null)
    {
        $this->vendor = $vendor;

        return $this;
    }

    /**
     * Get the list of folders will created.
     *
     * @return array
     */
    public function getFolders()
    {
        return $this->module->config('paths.generator');
    }

    /**
     * Get the list of files will created.
     *
     * @return array
     */
    public function getFiles()
    {
        return $this->module->config('stubs.files');
    }

    /**
     * Set force status.
     *
     * @param bool|int $force
     *
     * @return $this
     */
    public function setForce($force)
    {
        $this->force = $force;

        return $this;
    }

    /**
     * Generate the module.
     */
    public function generate(): int
    {
        $name = $this->getName();

        if ($this->module->has($name)) {
            if ($this->force) {
                $this->module->delete($name);
            } else {
                $this->component->error("Module [{$name}] already exists!");

                return E_ERROR;
            }
        }
        $this->component->info("Creating module: [$name]");

        $this->generateFolders();

        $this->generateModuleJsonFile();

        if ($this->type !== 'plain') {
            $this->generateFiles();
            $this->generateResources();
        }

        if ($this->type === 'plain') {
            $this->cleanModuleJsonFile();
        }

        // $this->activator->setActiveByName($name, $this->isActive);

        $this->console->newLine(1);

        $this->component->info("Module [{$name}] created successfully.");

        return 0;
    }

    /**
     * Generate the folders.
     */
    public function generateFolders()
    {
        foreach ($this->getFolders() as $key => $folder) {
            $folder = GenerateConfigReader::read($key);

            if ($folder->generate() === false) {
                continue;
            }

            $path = $this->module->getModulePath($this->getName())  . $folder->getPath();
            
            $this->filesystem->ensureDirectoryExists($path, 0777, true);
            if (config('modules.stubs.gitkeep')) {
                $this->generateGitKeep($path);
            }
        }
    }

    /**
     * Generate git keep to the specified path.
     * 0777 0755
     *
     * @param string $path
     */
    public function generateGitKeep($path)
    {
        $this->filesystem->put($path . '/.gitkeep', '');
    }

    /**
     * Generate the files.
     */
    public function generateFiles()
    {
        $data = $this->getFiles();
        if ($this->translation) {
            unset($data['scaffold/controller']);
            unset($data['scaffold/create-request']);
            unset($data['scaffold/update-request']);
            unset($data['views/backend/create']);
            unset($data['views/backend/edit']);
        }
        
        foreach ($data as $stub => $file) {
            $path = $this->module->getModulePath($this->getName()) . $file;
            
            if (!$this->filesystem->isDirectory($dir = dirname($path))) {
                $this->filesystem->makeDirectory($dir, 0777, true);
            }

            $this->filesystem->put($path, $this->getStubContents($stub));

            $this->console->info("Created : {$path}");
        }
    }

    /**
     * Generate some resources.
     */
    public function generateResources()
    {
        $this->console->call('module:make-seed', [
            'name' => $this->getName(),
            'module' => $this->getName(),
            '--master' => true,
        ]);

        $this->console->call('module:make-provider', [
            'name' => $this->getName() . 'ServiceProvider',
            'module' => $this->getName(),
            '--master' => true,
        ]);

        $this->console->call('module:route-provider', [
            'module' => $this->getName(),
        ]);

        // $this->console->call('module:make-controller', [
        //     'controller' => $this->getName() . 'Controller',
        //     'module' => $this->getName(),
        // ]);
        if ($this->translation) {
            $this->console->call('module:make-custom-translatable-entity', [
                'entity' => $this->getName(),
                'lower_name' => $this->getLowerNameReplacement(),
                'studly_name' => $this->getStudlyNameReplacement(),
                'table_columns' => $this->getTableColumnsReplacement()['main_module'],
                'translatable_columns' => $this->getTableColumnsReplacement()['translation_module'],
                'module' => $this->getName(),
                'soft_delete' => $this->getSoftDelete()
            ]);
            $this->console->call('module:make-custom-translatable-controller', [
                'studly_name' => $this->getStudlyNameReplacement(),
                'module' => $this->getName(),
                'controller_data' => $this->getControllerDataReplacement(),
                'controller_variable' => $this->getControllerVariableReplacement(),
                'controller_store' => $this->getControllerStoreReplacement(),
                'controller_edit_variable' => $this->getControllerEditVariableReplacement(),
                'controller_update' => $this->getControllerUpdateReplacement(),
                'controller_index' => $this->getControllerIndexReplacement(),
            ]);

            $this->console->call('module:make-custom-translatable-create-request', [
                'studly_name' => $this->getStudlyNameReplacement(),
                'request_functions' => $this->getRequestFunctionsReplacement(),
                'create_translatable_rules' => $this->getCreateTranslatableRulesReplacement(),
                'request_translatable_messages' => $this->getRequestTranslatableMessagesReplacement(),
                'module' => $this->getName(),
            ]);

            $this->console->call('module:make-custom-translatable-update-request', [
                'studly_name' => $this->getStudlyNameReplacement(),
                'request_functions' => $this->getRequestFunctionsReplacement(),
                'update_translatable_rules' => $this->getUpdateTranslatableRulesReplacement(),
                'request_translatable_messages' => $this->getRequestTranslatableMessagesReplacement(),
                'module' => $this->getName(),
            ]);

            $this->console->call('module:make-custom-translatable-blade-create', [
                'scripts' => $this->getScriptsReplacement(),
                'lower_name' => $this->getLowerNameReplacement(),
                'create_fields' => $this->getCreateTranslatableFieldsReplacement()['main_module'],
                'module' => $this->getName(),
            ]);

            $this->console->call('module:make-custom-translatable-blade-create-translatable', [
                'create_translatable_fields' => $this->getCreateTranslatableFieldsReplacement()['translation_module'],
                'module' => $this->getName(),
            ]);

            $this->console->call('module:make-custom-translatable-blade-edit', [
                'scripts' => $this->getScriptsReplacement(),
                'lower_name' => $this->getLowerNameReplacement(),
                'edit_fields' => $this->getEditTranslatableFieldsReplacement()['main_module'],
                'module' => $this->getName(),
            ]);

            $this->console->call('module:make-custom-translatable-blade-edit-translatable', [
                'edit_translatable_fields' => $this->getEditTranslatableFieldsReplacement()['translation_module'],
                'module' => $this->getName(),
            ]);
        } else {
            $this->console->call('module:make-custom-entity', [
                'entity' => $this->getName(),
                'lower_name' => $this->getLowerNameReplacement(),
                'studly_name' => $this->getStudlyNameReplacement(),
                'table_columns' => $this->getTableColumnsReplacement()['main_module'],
                'module' => $this->getName(),
                'soft_delete' => $this->getSoftDelete()
            ]);
        }

        $this->console->call('module:make-custom-eloquent', [
            'lower_name' => $this->getLowerNameReplacement(),
            'studly_name' => $this->getStudlyNameReplacement(),
            'module_namespace' => $this->getModuleNamespaceReplacement(),
            'grid_columns' => $this->getGridColumnsReplacement(),
            'filters_options' => $this->getFiltersOptionsReplacement(),
            'pagination' => $this->getPaginationReplacement(),
            'eloquent' => $this->getName(),
            'cache_key' => $this->getLowerNameReplacement() . '.cache.',
            'module' => $this->getName(),
        ]);

        $this->console->call('module:make-custom-repository', [
            'studly_name' => $this->getStudlyNameReplacement(),
            'module_namespace' => $this->getModuleNamespaceReplacement(),
            'module' => $this->getName(),
            'repository' => $this->getName(),
        ]);


        $this->console->call('module:make-custom-cache', [
            'lower_name' => $this->getLowerNameReplacement(),
            'studly_name' => $this->getStudlyNameReplacement(),
            'cache' => $this->getName(),
            'module' => $this->getName(),
            'entity' => $this->getLowerNameReplacement() . '.cache.',
        ]);

        $this->console->call('module:make-custom-lang', [
            'lower_name' => $this->getLowerNameReplacement(),
            'studly_name' => $this->getStudlyNameReplacement(),
            'titles' => $this->getTitlesReplacement(),
            'module' => $this->getName(),
            'message' => $this->getUniqueMessagesReplacement(),
        ]);
    }

    /**
     * Get the contents of the specified stub file by given stub name.
     *
     * @param $stub
     *
     * @return string
     */
    protected function getStubContents($stub)
    {
        $stubInstance = new Stub(
            '/' . $stub . '.stub',
            $this->getReplacement($stub)
        );
        
        $content = $stubInstance->render();
        // replace additional placeholders
        $additionalReplacements = [
            '$STUDLY_NAME$' => $this->getStudlyNameReplacement(),
            '$LOWER_NAME$' => $this->getLowerNameReplacement(),
            '$MODULE_NAMESPACE$' => $this->getModuleNamespaceReplacement(),
            '$VENDOR$' => $this->getVendorReplacement(),
            '$AUTHOR_NAME$' => $this->getAuthorNameReplacement(),
            '$AUTHOR_EMAIL$' => $this->getAuthorEmailReplacement(),
            '$CONTROLLER_INDEX$' => $this->getControllerIndexReplacement(),
            '$CONTROLLER_DATA$' => $this->getControllerDataReplacement(),
            '$CONTROLLER_VARIABLE$' => $this->getControllerVariableReplacement(),
            '$CONTROLLER_STORE$' => $this->getControllerStoreReplacement(),
            '$CONTROLLER_EDIT_VARIABLE$' => $this->getControllerEditVariableReplacement(),
            '$CONTROLLER_UPDATE$' => $this->getControllerUpdateReplacement()
        ];
        
        foreach ($additionalReplacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }
        
        return $content;
    }

    /**
     * get the list for the replacements.
     */
    public function getReplacements()
    {
        return $this->module->config('stubs.replacements');
    }

    /**
     * Get array replacement for the specified stub.
     *
     * @param $stub
     *
     * @return array
     */
    protected function getReplacement($stub)
    {
        $replacements = $this->module->config('stubs.replacements');

        if (!isset($replacements[$stub])) {
            return [];
        }
        $keys = $replacements[$stub];

        $replaces = [];

        foreach ($keys as $key) {
            if (method_exists($this, $method = 'get' . ucfirst(Str::studly(strtolower($key))) . 'Replacement')) {
                $replaces[$key] = $this->$method();
            } else {
                $replaces[$key] = null;
            }
        }
        return $replaces;
    }

    /**
     * Generate the module.json file
     */
    private function generateModuleJsonFile()
    {
        $path = $this->module->getModulePath($this->getName()) . 'module.json';
        //dd($path);
        if (!$this->filesystem->isDirectory($dir = dirname($path))) {
            $this->filesystem->makeDirectory($dir, 0777, true);
        }

        $this->filesystem->put($path, $this->getStubContents('json'));

        $this->console->info("Created : {$path}");
    }

    /**
     * Remove the default service provider that was added in the module.json file
     * This is needed when a --plain module was created
     */
    private function cleanModuleJsonFile()
    {
        $path = $this->module->getModulePath($this->getName()) . 'module.json';

        $content = $this->filesystem->get($path);
        $namespace = $this->getModuleNamespaceReplacement();
        $studlyName = $this->getStudlyNameReplacement();

        $provider = '"' . $namespace . '\\\\' . $studlyName . '\\\\Providers\\\\' . $studlyName . 'ServiceProvider"';

        $content = str_replace($provider, '', $content);

        $this->filesystem->put($path, $content);
    }

    /**
     * Get the module name in lower case.
     *
     * @return string
     */
    protected function getLowerNameReplacement()
    {
        return strtolower($this->getName());
    }

    /**
     * Get the module name in studly case.
     *
     * @return string
     */
    protected function getStudlyNameReplacement()
    {
        return $this->getName();
    }

    /**
     * Get replacement for $VENDOR$.
     *
     * @return string
     */
    protected function getVendorReplacement()
    {
        return $this->module->config('composer.vendor');
    }

    /**
     * Get replacement for $MODULE_NAMESPACE$.
     *
     * @return string
     */
    protected function getModuleNamespaceReplacement()
    {
        return str_replace('\\', '\\\\', $this->module->config('namespace'));
    }

    /**
     * Get replacement for $AUTHOR_NAME$.
     *
     * @return string
     */
    protected function getAuthorNameReplacement()
    {
        return $this->module->config('composer.author.name');
    }

    /**
     * Get replacement for $AUTHOR_EMAIL$.
     *
     * @return string
     */
    protected function getAuthorEmailReplacement()
    {
        return $this->module->config('composer.author.email');
    }


    protected function getGridColumnsReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('grid', $value)) {
                $str .= "\t\t\t[\n";
                $str .= "\t\t\t\t" . '"title" => trans("' . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.titles.' . $column . '"),' . "\n";
                $str .= "\t\t\t\t" . '"column" => "' . $column . '"';
                if (!array_key_exists('filter', $value)) {
                    $str .= ",\n\t\t\t\t" . '"no_sort" => true' . "\n\t\t\t],";
                } else {
                    $str .= "\n\t\t\t],\n";
                }
            }
        }
        return $str;
    }

    protected function getTitlesReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            $str .= "\t\t" . '"' . $column . '" => "' . $value['column'] . '",' . "\n";
        }
        return $str;
    }

    protected function getUniqueMessagesReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (!empty($value['unique'])) {
                $str .= "\t\t" . '"' . $column . '_unique" => "' . $value['column'] . ' should be unique.",' . "\n";
            }
        }
        return $str;
    }

    protected function getTableColumnsReplacement()
    {
        $str = '';
        $trans_str = '';
        if (!$this->getColumns()) {
            return [
                'main_module' => $str,
                'translation_module' => $trans_str
            ];
        }
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if ($this->translation) {
                if (array_key_exists('translation', $value)) {
                    $trans_str .= "'" . $column . "',";
                    continue;
                }
            }
            $str .= "'" . $column . "',";
        }
        $str = rtrim($str, ',');
        $trans_str = rtrim($trans_str, ',');
        return [
            'main_module' => $str,
            'translation_module' => $trans_str
        ];
    }

    protected function getFiltersOptionsReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('grid', $value)) {
                if (array_key_exists('filter', $value)) {
                    $moduleName = $this->getLowerNameReplacement();
                    $str .= "\t\t\t[\n";
                    $str .= "\t\t\t" . '"type" => "' . $value['filter'] . '",' . "\n";
                    $str .= "\t\t\t" . '"row"  => "1",' . "\n";
                    if ($value['filter']  == 'text' || $value['filter'] == 'select') {
                        $str .= "\t\t\t" . '"name" => "' . $column . '",' . "\n";
                        $str .= "\t\t\t" . '"value" => $request->get("' . $column . '", getSessionFilter(config("'.$moduleName.'.cache.name") , "' . $column . '")),' . "\n";
                        if ($value['filter']  == 'text') {
                            $str .= "\t\t\t" . '"options" => ["placeholder" => trans("' . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.titles.' . $column . '"), "class" => "form-control"]' . "\n";
                        } else {
                            $str .= "\t\t\t" . '"select_options" => [],' . "\n";
                            $str .= "\t\t\t" . '"options" => ["label" => trans("' . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.titles.' . $column . '"), "class" => "form-control"]' . "\n";
                        }
                    } elseif ($value['filter'] == 'number_range' || $value['filter'] == 'date_range' || $value['filter'] == 'time_range') {
                        $str .= "\t\t\t" . '"name" => ["' . $column . '_from","' . $column . '_to"],' . "\n";
                        $str .= "\t\t\t" . '"value" => [' . "\n";
                        $str .= "\t\t\t\t" . '"' . $column . '_from" => $request->get("' . $column . '_from", getSessionFilter(config("'.$moduleName.'.cache.name") , "' . $column . '_from")),' . "\n";
                        $str .= "\t\t\t\t" . '"' . $column . '_to" => $request->get("' . $column . '_to", getSessionFilter(config("'.$moduleName.'.cache.name") , "' . $column . '_to")),' . "\n";
                        $str .= "\t\t\t" . '],' . "\n";
                        $str .= "\t\t\t" . '"options" => [' . "\n";
                        $str .= "\t\t\t\t" . '"' . $column . '_from" => ["label" => trans("' . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.titles.' . $column . '"),"placeholder" => trans("core::core.labels.from"), "class" => "form-control"],' . "\n";
                        $str .= "\t\t\t\t" . '"' . $column . '_to"   => ["placeholder" => trans("core::core.labels.to"), "class" => "form-control"]' . "\n";
                        $str .= "\t\t\t" . ']' . "\n";
                    }
                    $str .= "\t\t\t" . '],' . "\n";
                }
            }
        }
        return $str;
    }

    protected function getPaginationReplacement()
    {
        $transListings = '';
        if ($this->translation) {
            $transListings = '->listsTranslations([' . $this->getTableColumnsReplacement()['translation_module'] . '])';
        }
        $temp = "\t\t\t" . 'return $collection' . $transListings.';';
        if (!$this->getColumns()) {
            return $temp;
        }
        $str = '';
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('grid', $value)) {
                if (array_key_exists('filter', $value)) {
                    $lowerName = $this->getLowerNameReplacement();
                    if ($value['filter']  == 'text') {
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'Cond = $request->get("' . $column . '", getSessionFilter(config("'.$lowerName.'.cache.name") , "' . $column . '"));' . "\n";
                        $str .= "\t\t\t" . 'if($where' . Str::studly($column) . 'Cond !== null) {' . "\n";
                        if (array_key_exists('translation', $value)) {
                            $str .= "\t\t\t\t" . '$collection->whereHas("translations", function ($query) use ($where' . Str::studly($column) . 'Cond) {' . "\n";
                            $str .= "\t\t\t\t\t" . '$query->where("' . $column . '", "LIKE", "%{$where' . Str::studly($column) . 'Cond}%");' . "\n";
                            $str .= "\t\t\t})->with('translations');\n";
                        } else {
                            $str .= "\t\t\t\t" . '$collection->where("' . $column . '", "LIKE", "%{$where' . Str::studly($column) . 'Cond}%");' . "\n";
                        }
                        $str .= "\t\t\t" . '}' . "\n";
                    } elseif ($value['filter'] == 'select') {
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'Cond = $request->get("' . $column . '", getSessionFilter(config("'.$lowerName.'.cache.name") , "' . $column . '"));' . "\n";
                        $str .= "\t\t\t" . 'if($where' . Str::studly($column) . 'Cond !== null) {' . "\n";
                        $str .= "\t\t\t\t" . '$collection->where("' . $column . '", "$where' . Str::studly($column) . 'Cond");' . "\n";
                        $str .= "\t\t\t" . '}' . "\n";
                    } elseif ($value['filter'] == 'number_range') {
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'FromCond = $request->get("' . $column . '_from", getSessionFilter(config("'.$lowerName.'.cache.name") , "' . $column . '_from"));' . "\n";
                        $str .= "\t\t\t" . 'if($where' . Str::studly($column) . 'FromCond !== null) {' . "\n";
                        $str .= "\t\t\t\t" . '$collection->where("' . $column . '", ">=", $where' . Str::studly($column) . 'FromCond);' . "\n";
                        $str .= "\t\t\t" . '}' . "\n";
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'ToCond = $request->get("' . $column . '_to", getSessionFilter(config("'.$lowerName.'.cache.name") , "' . $column . '_to"));' . "\n";
                        $str .= "\t\t\t" . 'if($where' . Str::studly($column) . 'ToCond !== null) {' . "\n";
                        $str .= "\t\t\t\t" . '$collection->where("' . $column . '", "<=", $where' . Str::studly($column) . 'ToCond);' . "\n";
                        $str .= "\t\t\t" . '}' . "\n";
                    } elseif ($value['filter'] == 'date_range') {
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'FromCond = $request->get("' . $column . '_from", getSessionFilter(config("'.$lowerName.'.cache.name") , "' . $column . '_from"));' . "\n";
                        $str .= "\t\t\t" . 'if($where' . Str::studly($column) . 'FromCond !== null) {' . "\n";
                        $str .= "\t\t\t\t" . '$collection->whereRaw("DATE(' . $column . ' + INTERVAL {$timezoneOffset} SECOND) >= ?",  date_format(date_create_from_format(config("core.encrypt.php_datepicker_format"), $where' . Str::studly($column) . 'FromCond), "Y-m-d"));' . "\n";
                        $str .= "\t\t\t" . '}' . "\n";
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'ToCond = $request->get("' . $column . '_to", getSessionFilter(config("'.$lowerName.'.cache.name") , "' . $column . '_to"));' . "\n";
                        $str .= "\t\t\t" . 'if($where' . Str::studly($column) . 'ToCond !== null) {' . "\n";
                        $str .= "\t\t\t\t" . '$collection->whereRaw("DATE(' . $column . ' + INTERVAL {$timezoneOffset} SECOND) <= ?",  date_format(date_create_from_format(config("core.encrypt.php_datepicker_format"), $where' . Str::studly($column) . 'ToCond), "Y-m-d"));' . "\n";
                        $str .= "\t\t\t" . '}' . "\n";
                    } elseif ($value['filter'] == 'time_range') {
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'FromCond = $request->get("' . $column . '_from", getSessionFilter(config("'.$lowerName.'.cache.name") , "' . $column . '_from"));' . "\n";
                        $str .= "\t\t\t" . 'if($where' . Str::studly($column) . 'FromCond !== null) {' . "\n";
                        $str .= "\t\t\t\t" . '$collection->whereRaw("DATE(' . $column . ' + INTERVAL {$timezoneOffset} SECOND) >= ?",  date("H:i:s", strtotime($where' . Str::studly($column) . 'FromCond)));' . "\n";
                        $str .= "\t\t\t" . '}' . "\n";
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'ToCond = $request->get("' . $column . '_to", getSessionFilter(config("'.$lowerName.'.cache.name") , "' . $column . '_to"));' . "\n";
                        $str .= "\t\t\t" . 'if($where' . Str::studly($column) . 'ToCond !== null) {' . "\n";
                        $str .= "\t\t\t\t" . '$collection->whereRaw("DATE(' . $column . ' + INTERVAL {$timezoneOffset} SECOND) <= ?",  date("H:i:s", strtotime($where' . Str::studly($column) . 'ToCond)));' . "\n";
                        $str .= "\t\t\t" . '}' . "\n";
                    }
                }
            }
        }
        $str .= $temp;
        return $str;
    }

    protected function getColumnCountReplacement()
    {
        if (!$this->getColumns()) {
            return '7';
        }
        $count = 0;
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('grid', $value)) {
                $count++;
            }
        }
        $count = $count + 4;

        return $count;
    }

    protected function getDataReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $columns = $this->getColumns();
        // foreach ($columns as $column => $value) {
        //     if (array_key_exists('grid', $value)) {
        //         if (!array_key_exists('image', $value)) {
        //             if ($value['type'] == 'Date') {
        //                 $str .= "\t\t\t\t\t\t\t" . '<td> {{  getFormatedDate($' . $this->getLowerNameReplacement() . '->' . $column . ', "j M Y") }} </td>' . "\n";
        //             } elseif ($value['type'] == 'Time') {
        //                 $str .= "\t\t\t\t\t\t\t" . '<td> {{  date("g:i  A", strtotime($' . $this->getLowerNameReplacement() . '->' . $column . ')) }} </td>' . "\n";
        //             } else {
        //                 $str .= "\t\t\t\t\t\t\t" . '<td> {{ wordWrapper($' . $this->getLowerNameReplacement() . '->' . $column . ') }} </td>' . "\n";
        //             }
        //         } else {
        //             $str .=  "\t\t\t\t\t\t\t" . '<td>' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '@php' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '$og_image_param = [' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '"module" => Config::get("' . $this->getLowerNameReplacement() . '.name"),' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '"image" => $' . $this->getLowerNameReplacement() . '->' . $column . ',' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '];' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '$resize_image_param = [' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '"image-type" => "resize",' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '"image-size" => 100,' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '"module" => Config::get("' . $this->getLowerNameReplacement() . '.name"),' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '"image" => $' . $this->getLowerNameReplacement() . '->' . $column . ',' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '"defualt-image" => true,' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '];' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '@endphp' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '@if(getImageUrl($og_image_param))' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '<a href="{{getImageUrl($og_image_param)}}" target="_BLANK">' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '<img src="{{getImageUrl($resize_image_param)}}" alt="introduction">' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '</a>' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '@else' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '<img src="{{getImageUrl($resize_image_param)}}" alt="introduction">' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '@endif' . "\n";
        //             $str .=  "\t\t\t\t\t\t\t" . '</td>' . "\n";
        //         }
        //     }
        // }


        foreach ($columns as $column => $value) {
            if (array_key_exists('grid', $value)) {
                if (!array_key_exists('image', $value)) {
                    if ($value['type'] == 'Date') {
                        $str .=  "\t\t\t\t\t\t\t" . '@elseif($columnCode == "'.$column.'")' . "\n";
                        $str .= "\t\t\t\t\t\t\t" . '<td> {{  getFormatedDate($' . $this->getLowerNameReplacement() . '->' . $column . ', "j M Y") }} </td>' . "\n";
                    } elseif ($value['type'] == 'Time') {
                        $str .=  "\t\t\t\t\t\t\t" . '@elseif($columnCode == "'.$column.'")' . "\n";
                        $str .= "\t\t\t\t\t\t\t" . '<td> {{  date("g:i  A", strtotime($' . $this->getLowerNameReplacement() . '->' . $column . ')) }} </td>' . "\n";
                    } else {
                        $str .=  "\t\t\t\t\t\t\t" . '@elseif($columnCode == "'.$column.'")' . "\n";
                        $str .= "\t\t\t\t\t\t\t" . '<td> {{ wordWrapper($' . $this->getLowerNameReplacement() . '->' . $column . ') }} </td>' . "\n";
                    }
                } else {
                    $str .=  "\t\t\t\t\t\t\t" . '@elseif($columnCode == "'.$column.'")' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '<td>' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '@php' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '$og_image_param = [' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '"module" => Config::get("' . $this->getLowerNameReplacement() . '.name"),' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '"image" => $' . $this->getLowerNameReplacement() . '->' . $column . ',' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '];' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '$resize_image_param = [' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '"image-type" => "resize",' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '"image-size" => 100,' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '"module" => Config::get("' . $this->getLowerNameReplacement() . '.name"),' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '"image" => $' . $this->getLowerNameReplacement() . '->' . $column . ',' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '"defualt-image" => true,' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '];' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '@endphp' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '@if(getImageUrl($og_image_param))' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '<a href="{{getImageUrl($og_image_param)}}" target="_BLANK">' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '<img src="{{getImageUrl($resize_image_param)}}" alt="introduction">' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '</a>' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '@else' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '<img src="{{getImageUrl($resize_image_param)}}" alt="introduction">' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '@endif' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '</td>' . "\n";
                }
            }
        }

        return $str;
    }

    protected function getCreateFieldsReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            $required = array_key_exists('required', $value) ? 'required' : '';
            if (!array_key_exists('image', $value)) {

                if (!array_key_exists('type', $value)) {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType(" ","'
                        . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Text') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("' . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Number') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType("number","'
                        . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '", "min"=>"0" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Checkbox') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<label for="' . $this->getLowerNameReplacement() . '.' . $column . '">{{trans("' . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column . '")}}</label>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="switch">' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<input type="checkbox" value="{{config(' . "'" . 'core.enabled' . "'" . ')}}"  name="' . $this->getLowerNameReplacement() . '[' . $column . ']">' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<span class="slider round"></span>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</label>' . "\n";
                } elseif ($value['type'] == 'Select') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalSelect("' . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, [], null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Multiselect') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalSelect("' . $this->getLowerNameReplacement() . '[' . $column . '][]", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, [], null, ["class" => "form-control ' . $required . '", "multiple" => "true" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textarea') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalTextarea("' . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control formated-textarea ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textdescription') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalTextarea("' . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Date') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("'
                        . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["id"=>"' . $this->getLowerNameReplacement() . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Time') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("'
                        . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["id"=>"' . $this->getLowerNameReplacement() . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Email') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType("email","'
                        . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control valid_email ' . $required . '" ]) !!}' . "\n";
                }
            } else {
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {{ normalLabel(trans("' . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column . '") , null , [])}}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div class="input-group mb-3">' . "\n\t\t\t\t\t\t\t\t\t\t" . ' <div class="custom-file">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{{ normalFile("' . $column . '","",$errors,["class"=>"custom-file-label ' . $column . ' form-control ' . $required . '  is-invalid","id"=> "' . $this->getLowerNameReplacement() . '_' . $column . '", "accept" => $imageTypes ]) }}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="custom-file-label ' . $column . ' hideoverflow" for="' . $this->getLowerNameReplacement() . '_' . $column . '"  >{{ trans("core::core.labels.choose_file") }}</label> </div> <div class="input-group-append"></div></div>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{!! $errors->first("' . $column . '", "<label class=' . "'" . 'error' . "'" . '>:message</label>") !!}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@php' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_extension = (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_type")))?settings("' . $this->getLowerNameReplacement() . '", "image_type"):"jpeg, jpg, png";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$width = (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_width")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_width"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$height = (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_height")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_height"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$ratio = (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_ratio")))?settings("' . $this->getLowerNameReplacement() . '", "image_ratio"):"1";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_max_size = (!empty(settings("' . $this->getLowerNameReplacement() . '", "max_upload_size")))?settings("' . $this->getLowerNameReplacement() . '", "max_upload_size"):"5";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@endphp' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div class="image-note">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' <lh><b>{{trans("core::core.image-note.label")}}</b></lh>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<li>{{trans("core::core.image-note.min-dimension",["width"=>$width,"height" => $height])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<li>{{trans("core::core.image-note.ratio",["ratio"=>$ratio])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' <li>{{trans("core::core.image-note.max-size",["size"=>$image_max_size])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<li>{{trans("core::core.image-note.file-type",["file_type"=>$image_extension])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '</div>' . "\n";
            }
        }

        return $str;
    }

    protected function getCreateTranslatableFieldsReplacement()
    {


        $str = '';
        $trans_str = '';
        if (!$this->getColumns()) {
            return [
                'main_module' => $str,
                'translation_module' => $trans_str
            ];
        }
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('translation', $value)) {
                $required = array_key_exists('required', $value) ? 'required' : '';
                if (!array_key_exists('image', $value)) {
                    if ($value['type'] == 'Text') {
                        $trans_str .= ' {!! i18nInput("' . $column . '",trans( "'
                            . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                            . '"), $errors, $lang, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                    } elseif ($value['type'] == 'Textarea') {
                        $trans_str .= ' {!! i18nTextarea("' . $column . '", trans("'
                            . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                            . '"), $errors, $lang,null, ["class" => "form-control formated-textarea ' . $required . '" ]) !!}' . "\n";
                    } elseif ($value['type'] == 'Textdescription') {
                        $trans_str .= ' {!! i18nTextarea("' . $column . '", trans("'
                            . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                            . '"), $errors, $lang,null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                    } else {
                        $trans_str .= ' {!! i18nInputOfType(" ","' . $column . '",trans("'
                            . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                            . '"), $errors, $lang, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                    }
                }
                continue;
            }

            $required = array_key_exists('required', $value) ? 'required' : '';
            if (!array_key_exists('image', $value)) {
                if (!array_key_exists('type', $value)) {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType(" ","' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Text') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Number') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType("number","' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '", "min"=>"0" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Checkbox') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<label for="' . $column . '">{{trans("' . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column . '")}}</label>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="switch">' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<input type="checkbox" value="{{config(' . "'" . 'core.enabled' . "'" . ')}}"  name="' . $column . '">' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<span class="slider round"></span>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</label>' . "\n";
                } elseif ($value['type'] == 'Select') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalSelect("' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, [], null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Multiselect') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalSelect("' . $column . '[]", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, [], null, ["class" => "form-control ' . $required . '", "multiple" => "multiple" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textarea') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalTextarea("' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control formated-textarea ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textdescription') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalTextarea("' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Date') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["id"=>"' . $this->getLowerNameReplacement() . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Time') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["id"=>"' . $this->getLowerNameReplacement() . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Email') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType("email","' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control valid_email ' . $required . '" ]) !!}' . "\n";
                }
            } else {
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {{ normalLabel(trans("' . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column . '") , null , [])}}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div class="input-group mb-3">' . "\n\t\t\t\t\t\t\t\t\t\t" . ' <div class="custom-file">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{{ normalFile("' . $column . '","",$errors,["class"=>"custom-file-label ' . $column . ' form-control ' . $required . '  is-invalid","id"=> "' . $this->getLowerNameReplacement() . '_' . $column . '", "accept" => $imageTypes ]) }}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="custom-file-label ' . $column . ' hideoverflow" for="' . $this->getLowerNameReplacement() . '_' . $column . '"  >{{ trans("core::core.labels.choose_file") }}</label> </div> <div class="input-group-append"></div></div>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{!! $errors->first("' . $column . '", "<label class=' . "'" . 'error' . "'" . '>:message</label>") !!}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@php' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_extension = (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_type")))?settings("' . $this->getLowerNameReplacement() . '", "image_type"):"jpeg, jpg, png";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$width = (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_width")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_width"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$height = (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_height")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_height"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$ratio = (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_ratio")))?settings("' . $this->getLowerNameReplacement() . '", "image_ratio"):"1";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_max_size = (!empty(settings("' . $this->getLowerNameReplacement() . '", "max_upload_size")))?settings("' . $this->getLowerNameReplacement() . '", "max_upload_size"):"5";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@endphp' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div class="image-note">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' <lh><b>{{trans("core::core.image-note.label")}}</b></lh>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<li>{{trans("core::core.image-note.min-dimension",["width"=>$width,"height" => $height])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<li>{{trans("core::core.image-note.ratio",["ratio"=>$ratio])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' <li>{{trans("core::core.image-note.max-size",["size"=>$image_max_size])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<li>{{trans("core::core.image-note.file-type",["file_type"=>$image_extension])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '</div>' . "\n";
            }
        }

        return [
            'main_module' => $str,
            'translation_module' => $trans_str
        ];
    }

    protected function getScriptsReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $imageScripts = 0;
        $textArea = 0;
        $email = 0;
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (!array_key_exists('image', $value)) {
                if (!empty($value['type'])) {
                    if ($value['type'] == 'Textarea' && $textArea == 0) {
                        $textArea = 1;
                        $str .= "\t\t\t\t" . 'jQuery(".formated-textarea").summernote({' . "\n";
                        $str .= "\t\t\t\t\t" . 'height: 200' . "\n";
                        $str .= "\t\t\t\t" . ' });' . "\n\n";
                    } elseif ($value['type'] == 'Email' && $email == 0) {
                        $email = 1;
                        $str .= "\t\t\t\t" . 'jQuery.validator.addMethod("email", function(value, element) {' . "\n";
                        $str .= "\t\t\t\t\t" . 'return /^[+a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/i.test(value);' . "\n";
                        $str .= "\t\t\t\t" . "},'" . '{{ trans("core::core.messages.invalid_email") }}' . "');" . "\n\n";
                    } elseif ($value['type'] == 'Date') {
                        $str .= "\t\t\t\t" . 'jQuery("#' . $this->getLowerNameReplacement() . '_' . $column . '").datepicker({' . "\n";
                        $str .= "\t\t\t\t\t" . 'dateFormat: "{{config(' . "'" . 'core.encrypt.datepicker_format' . "'" . ')}}"' . "\n";
                        $str .= "\t\t\t\t" . "});" . "\n\n";
                    } elseif ($value['type'] == 'Time') {
                        $str .= "\t\t\t\t" . 'jQuery("#' . $this->getLowerNameReplacement() . '_' . $column . '").datetimepicker({' . "\n";
                        $str .= "\t\t\t\t\t" . 'format: "LT" ,' . "\n";
                        $str .= "\t\t\t\t\t" . ' icons: {' . "\n";
                        $str .= "\t\t\t\t\t\t" . 'up: "fa fa-chevron-up",' . "\n";
                        $str .= "\t\t\t\t\t\t" . 'down: "fa fa-chevron-down",' . "\n";
                        $str .= "\t\t\t\t\t" . '}' . "\n";
                        $str .= "\t\t\t\t" . "});" . "\n\n";
                    }
                }
            } else {
                $str .= "\t\t\t\t" . 'jQuery("#' . $this->getLowerNameReplacement() . '_' . $column . '").change(function(e) {' . "\n";
                $str .= "\t\t\t\t\t" . 'var fileName = e.target.files[0].name;' . "\n";
                $str .= "\t\t\t\t\t" . 'jQuery(".' . $column . '").html(fileName);' . "\n";
                $str .= "\t\t\t\t\t" . '$("input[type=' . "'" . 'file' . "'" . ']").addClass("valid_image");' . "\n";
                $str .= "\t\t\t\t\t" . '$("input[type=' . "'" . 'file' . "'" . ']").addClass("validImage");' . "\n";
                $str .= "\t\t\t\t\t" . '$("input[type=' . "'" . 'file' . "'" . ']").addClass("validDimension");' . "\n";
                $str .= "\t\t\t\t" . ' });' . "\n\n";
                if ($imageScripts == 0) {
                    $imageScripts = 1;
                    $str .= "\t\t\t\t" . 'jQuery.validator.addMethod("validImage", function(value, element) {' . "\n";
                    $str .= "\t\t\t\t\t" . 'var ext = value.split(".").pop().toLowerCase();' . "\n";
                    $str .= "\t\t\t\t\t" . 'var Image_extention_db = ' . "'" . '{{ (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_type")))?settings("' . $this->getLowerNameReplacement() . '", "image_type"):"jpeg,jpg,png" }}' . "'" . '.toLowerCase().split(",");' . "\n";
                    $str .= "\t\t\t\t\t" . ' return ($.inArray(ext, Image_extention_db) == -1 && ext != "") ? false : true;' . "\n";
                    $str .= "\t\t\t\t" . '}, ' . "'" . '{{ trans("core::core.messages.invalid_image") }}' . "'" . ');' . "\n\n";
                    $str .= "\t\t\t\t" . 'jQuery.validator.addMethod("validDimension", function(value, element) {' . "\n";
                    $str .= "\t\t\t\t\t" . 'var img = new Image();' . "\n";
                    $str .= "\t\t\t\t\t" . 'if ($(element)[0].files[0]) {' . "\n";
                    $str .= "\t\t\t\t\t" . 'img.src = window.URL.createObjectURL($(element)[0].files[0]);' . "\n";
                    $str .= "\t\t\t\t\t" . 'img.onload = function() {' . "\n";
                    $str .= "\t\t\t\t\t" . 'width = parseInt(img.naturalWidth);' . "\n";
                    $str .= "\t\t\t\t\t" . 'height = parseInt(img.naturalHeight);' . "\n";
                    $str .= "\t\t\t\t\t" . 'minWidth = parseInt(' . "'" . '{{ (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_width")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_width"):"100" }}' . "'" . ');' . "\n";
                    $str .= "\t\t\t\t\t" . 'minHeight = parseInt(' . "'" . '{{ (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_height")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_height"):"100" }}' . "'" . ');' . "\n";
                    $str .= "\t\t\t\t\t" . 'window.URL.revokeObjectURL(img.src);' . "\n";
                    $str .= "\t\t\t\t\t" . ' if ((width >= minWidth) && (height >= minHeight)) {' . "\n";
                    $str .= "\t\t\t\t\t" . 'if ((width / height).toFixed(2) != parseInt(' . "'" . '{{ (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_ratio")))?settings("' . $this->getLowerNameReplacement() . '", "image_ratio"):"1" }}' . "'" . ')) {' . "\n";
                    $str .= "\t\t\t\t\t" . 'alert(' . "'" . '{{ trans("core::core.messages.invalid_image_ratio") }}' . "'" . ');' . "\n";
                    $str .= "\t\t\t\t\t" . ' return false;' . "\n";
                    $str .= "\t\t\t\t\t" . '}' . "\n";
                    $str .= "\t\t\t\t\t" . 'return true;' . "\n";
                    $str .= "\t\t\t\t\t" . '} else {' . "\n";
                    $str .= "\t\t\t\t\t" . 'alert(' . "'" . '{{ trans("core::core.messages.invalid_dimension") }}' . "'" . ');' . "\n";
                    $str .= "\t\t\t\t\t" . 'return false;' . "\n";
                    $str .= "\t\t\t\t\t" . ' }' . "\n";
                    $str .= "\t\t\t\t\t" . '};' . "\n";
                    $str .= "\t\t\t\t\t" . '}' . "\n";
                    $str .= "\t\t\t\t\t" . 'return true;' . "\n";
                    $str .= "\t\t\t\t" . '}, ' . "'" . '{{ trans("core::core.messages.invalid_dimension") }}' . "'" . ');' . "\n\n";
                    $str .= "\t\t\t\t" . 'var msg;' . "\n";
                    $str .= "\t\t\t\t" . 'var dynamicmsg = function() {' . "\n";
                    $str .= "\t\t\t\t" . 'return msg;' . "\n";
                    $str .= "\t\t\t\t" . '};' . "\n\n";
                    $str .= "\t\t\t\t" . 'jQuery.validator.addMethod("valid_image", function(value, element) {' . "\n";
                    $str .= "\t\t\t\t\t" . 'if (typeof($(element)[0].files[0]) != "undefined") {' . "\n";
                    $str .= "\t\t\t\t\t" . 'var file_size = ($(element)[0].files[0].size / 1024);' . "\n";
                    $str .= "\t\t\t\t\t" . ' var maxImageSize = ' . "'" . '{{ (!empty(settings("' . $this->getLowerNameReplacement() . '", "max_upload_size")))?settings("' . $this->getLowerNameReplacement() . '", "max_upload_size"):"5" }}' . "'" . ';' . "\n";
                    $str .= "\t\t\t\t\t" . 'if (maxImageSize != "" && typeof(maxImageSize) != "undefined") {' . "\n";
                    $str .= "\t\t\t\t\t" . ' var maxFileSize = (1024 * maxImageSize);' . "\n";
                    $str .= "\t\t\t\t\t" . 'if (file_size > maxFileSize) {' . "\n";
                    $str .= "\t\t\t\t\t" . 'msg = ' . "'" . '{{ trans("core::core.validation-message.image.max-size",["size"=>( (!empty(settings("' . $this->getLowerNameReplacement() . '", "max_upload_size")))?settings("' . $this->getLowerNameReplacement() . '", "max_upload_size"):"5" )] ) }}' . "'" . ';' . "\n";
                    $str .= "\t\t\t\t\t" . 'return false;' . "\n";
                    $str .= "\t\t\t\t" . '}' . "\n";
                    $str .= "\t\t\t\t" . '}' . "\n";
                    $str .= "\t\t\t\t" . '}' . "\n";
                    $str .= "\t\t\t\t" . 'return true;' . "\n";
                    $str .= "\t\t\t\t" . '}, dynamicmsg);' . "\n";
                }
            }
        }

        return $str;
    }

    protected function getControllerDataReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $textArea = 0;
        $imageType = 0;
        $time = 0;
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (!array_key_exists('image', $value)) {
                if (!empty($value['type'])) {
                    if ($value['type'] == 'Textarea' && $textArea == 0) {
                        $textArea = 1;
                        $str .= "\t\t\t\t" . ' $this->getAssetManager()->addAsset("modules/pages/js/summernote.min.js");' . "\n";
                        $str .= "\t\t\t\t" . ' $this->getAssetManager()->addAsset("modules/pages/css/summernote.css");' . "\n";
                    } elseif ($value['type'] == 'Time' && $time == 0) {
                        $time = 1;
                        $str .= "\t\t\t\t" . '$this->getAssetManager()->addAsset("modules/theme/backend/js/moment.js");' . "\n";
                        $str .= "\t\t\t\t" . '$this->getAssetManager()->addAsset("modules/theme/backend/js/bootstrap-datetimepicker.js");' . "\n";
                        $str .= "\t\t\t\t" . '$this->getAssetManager()->addAsset("modules/theme/backend/css/bootstrap-datetimepicker.css");' . "\n";
                    }
                }
            } else {
                if ($imageType == 0) {
                    $imageType = 1;
                    $str .= "\t\t\t\t" . '$imageTypes = (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_type")))?settings("' . $this->getLowerNameReplacement() . '", "image_type"):"jpeg,jpg,png";' . "\n";
                    $str .= "\t\t\t\t" . '$imageTypes = explode(",", $imageTypes);' . "\n";
                    $str .= "\t\t\t\t" . '$imageTypes = "." . implode(",.", $imageTypes);' . "\n";
                }
            }
        }

        return $str;
    }

    protected function getControllerIndexReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $time = 0;
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('grid', $value)) {
                if (!array_key_exists('image', $value)) {
                    if (!empty($value['type'])) {
                        if ($value['type'] == 'Time' && $time == 0) {
                            $time = 1;
                            $str .= "\t\t\t\t" . '$this->getAssetManager()->addAsset("modules/core/js/backend/moment.min.js");' . "\n";
                            $str .= "\t\t\t\t" . '$this->getAssetManager()->addAsset("modules/core/js/backend/bootstrap-datetimepicker.min.js");' . "\n";
                            $str .= "\t\t\t\t" . '$this->getAssetManager()->addAsset("modules/core/css/bootstrap-datetimepicker.min.css");' . "\n";
                        }
                    }
                }
            }
        }

        return $str;
    }

    protected function getControllerVariableReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $imageType = 0;
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('image', $value)  && $imageType == 0) {
                $imageType = 1;
                $str .= '"imageTypes",';
            }
        }
        if ($this->translation) {
            $str .= '"languageOptions"';
        }
        $str = rtrim($str, ',');
        if(empty($str)) {
            return '';
        }
        return ', compact('.$str. ')';
    }

    protected function getControllerStoreReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('image', $value)) {
                $str .= "\t\t\t\t" . 'if ($request->file("' . $column . '")) {' . "\n";
                $str .= "\t\t\t\t\t" . ' $imageUploadParams = array(' . "\n";
                $str .= "\t\t\t\t\t\t" . '"module_name" => \Config::get("' . $this->getLowerNameReplacement() . '.name") ,' . "\n";
                $str .= "\t\t\t\t\t\t" . ' "dbfield" => "' . $column . '",' . "\n";
                $str .= "\t\t\t\t\t\t" . ' "thumbnail" => true,' . "\n";
                $str .= "\t\t\t\t\t\t" . ' "thumbnail_size" => 100' . "\n";
                $str .= "\t\t\t\t\t" . ');' . "\n";
                $str .= "\t\t\t\t\t" . ' $formData = $this->' . $this->getLowerNameReplacement() . '->setUploadParams($imageUploadParams)->uploadImage($request);' . "\n";
                if ($this->translation) {
                    $str .= "\t\t\t\t\t" . '$params["' . $column . '"] = $formData["' . $column . '"];' . "\n";
                } else {
                    $str .= "\t\t\t\t\t" . '$params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"] = $formData["' . $column . '"];' . "\n";
                }
                $str .= "\t\t\t\t" . '}' . "\n";
            } else {
                if (!empty($value['type'])) {
                    if ($this->translation) {
                        if ($value['type'] == 'Checkbox') {
                            $str .= "\t\t\t\t" . '$params["' . $column . '"] = (!empty( $params["' . $column . '"] )) ? "1" : "2";' . "\n";
                        }
                        if ($value['type'] == 'Date') {
                            $str .= "\t\t\t\t" . 'if($params["' . $column . '"]){' . "\n";
                            $str .= "\t\t\t\t\t" . '$params["' . $column . '"] = date_format(date_create_from_format(config("core.encrypt.php_datepicker_format"), $params["' . $column . '"]), "Y-m-d");' . "\n";
                            $str .= "\t\t\t\t" . '}' . "\n";
                        }
                        if ($value['type'] == 'Time') {
                            $str .= "\t\t\t\t" . 'if($params["' . $column . '"]){' . "\n";
                            $str .= "\t\t\t\t\t" . '$params["' . $column . '"] = date("H:i:s", strtotime( $params["' . $column . '"]));' . "\n";
                            $str .= "\t\t\t\t" . '}' . "\n";
                        }
                    } else {
                        if ($value['type'] == 'Checkbox') {
                            $str .= "\t\t\t\t" . '$params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"] = (!empty( $params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"] )) ? "1" : "2";' . "\n";
                        }
                        if ($value['type'] == 'Date') {
                            $str .= "\t\t\t\t" . 'if($params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"]){' . "\n";
                            $str .= "\t\t\t\t\t" . '$params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"] = date_format(date_create_from_format(config("core.encrypt.php_datepicker_format"), $params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"]), "Y-m-d");' . "\n";
                            $str .= "\t\t\t\t" . '}' . "\n";
                        }
                        if ($value['type'] == 'Time') {
                            $str .= "\t\t\t\t" . 'if($params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"]){' . "\n";
                            $str .= "\t\t\t\t\t" . '$params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"] = date("H:i:s", strtotime( $params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"]));' . "\n";
                            $str .= "\t\t\t\t" . '}' . "\n";
                        }
                    }
                }
            }
        }

        return $str;
    }

    protected function getControllerEditVariableReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $imageType = 0;
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('image', $value)  && $imageType == 0) {
                $imageType = 1;
                $str .= ', "imageTypes"';
            }
        }

        return $str;
    }

    protected function getEditFieldsReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            $required = array_key_exists('required', $value) ? 'required' : '';
            if (!array_key_exists('image', $value)) {

                if (!array_key_exists('type', $value)) {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType(" ","'
                        . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Text') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("' . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Number') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType("number","'
                        . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control ' . $required . '", "min"=>"0" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Checkbox') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<label for="' . $this->getLowerNameReplacement() . '.' . $column . '">{{trans("' . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column . '")}}</label>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="switch">' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<input type="checkbox" value="{{config(' . "'" . 'core.enabled' . "'" . ')}}"  name="' . $this->getLowerNameReplacement() . '[' . $column . ']"  {{ ($' . $this->getLowerNameReplacement() . '->' . $column . ' == config("core.enabled")) ? "checked" : ""}}>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<span class="slider round"></span>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</label>' . "\n";
                } elseif ($value['type'] == 'Select') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalSelect("' . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, [], $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Multiselect') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalSelect("' . $this->getLowerNameReplacement() . '[' . $column . '][]", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, [], $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control ' . $required . '", "multiple" => "true" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textarea') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalTextarea("' . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control formated-textarea ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textdescription') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalTextarea("' . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors,  $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Date') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("'
                        . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors,date(config("core.encrypt.php_datepicker_format"), strtotime($' . $this->getLowerNameReplacement() . '->' . $column . ')), ["id"=>"' . $this->getLowerNameReplacement() . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Time') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("'
                        . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, $' . $this->getLowerNameReplacement() . '->' . $column . ', ["id"=>"' . $this->getLowerNameReplacement() . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Email') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType("email","'
                        . $this->getLowerNameReplacement() . '[' . $column . ']", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control valid_email ' . $required . '" ]) !!}' . "\n";
                }
            } else {
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {{ normalLabel(trans("' . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column . '") , "" , [])}}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div class="input-group mb-3">' . "\n\t\t\t\t\t\t\t\t\t\t" . ' <div class="custom-file">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{{ normalFile("' . $column . '","",$errors,["class"=>"custom-file-label ' . $column . ' form-control  is-invalid","id"=> "' . $this->getLowerNameReplacement() . '_' . $column . '", "accept" => $imageTypes ]) }}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="custom-file-label ' . $column . ' hideoverflow" for="' . $this->getLowerNameReplacement() . '_' . $column . '"  >{{ trans("core::core.labels.choose_file") }}</label> </div> <div class="input-group-append"></div></div>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{!! $errors->first("' . $column . '", "<label class=' . "'" . 'error' . "'" . '>:message</label>") !!}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@php' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_extension = (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_type")))?settings("' . $this->getLowerNameReplacement() . '", "image_type"):"jpeg, jpg, png";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$width = (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_width")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_width"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$height = (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_height")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_height"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$ratio = (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_ratio")))?settings("' . $this->getLowerNameReplacement() . '", "image_ratio"):"1";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_max_size = (!empty(settings("' . $this->getLowerNameReplacement() . '", "max_upload_size")))?settings("' . $this->getLowerNameReplacement() . '", "max_upload_size"):"5";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$og_image_param = [' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"module" => Config::get("' . $this->getLowerNameReplacement() . '.name"),' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image" => $' . $this->getLowerNameReplacement() . '->' . $column . ',' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '];' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$resize_image_param = [' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image-type" => "resize",' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image-size" => 100,' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"module" => Config::get("' . $this->getLowerNameReplacement() . '.name"),' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image" => $' . $this->getLowerNameReplacement() . '->' . $column . ',' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '];' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@endphp' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@if(getImageUrl($og_image_param))' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<a href="{{getImageUrl($og_image_param)}}" target="_BLANK">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<img src="{{getImageUrl($resize_image_param)}}" alt="introduction">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '</a>' . "\n";
                if (!array_key_exists('required', $value)) {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalCheckbox("remove_' . $column . '", "Remove Image", $errors, null, ["class" => "form-control" ]) !!}' . "\n";
                }
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@endif' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div class="image-note">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' <lh><b>{{trans("core::core.image-note.label")}}</b></lh>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<li>{{trans("core::core.image-note.min-dimension",["width"=>$width,"height" => $height])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<li>{{trans("core::core.image-note.ratio",["ratio"=>$ratio])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' <li>{{trans("core::core.image-note.max-size",["size"=>$image_max_size])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<li>{{trans("core::core.image-note.file-type",["file_type"=>$image_extension])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '</div>' . "\n";
            }
        }

        return $str;
    }

    protected function getEditTranslatableFieldsReplacement()
    {
        $str = '';
        $trans_str = '';
        if (!$this->getColumns()) {
            return [
                'main_module' => $str,
                'translation_module' => $trans_str

            ];
        }
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('translation', $value)) {
                $required = array_key_exists('required', $value) ? 'required' : '';
                if (!array_key_exists('image', $value)) {
                    if ($value['type'] == 'Text') {
                        $trans_str .= ' {!! i18nInput("' . $column . '", trans("'
                            . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                            . '"), $errors, $lang, $' . $this->getLowerNameReplacement() . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                    } elseif ($value['type'] == 'Textarea') {
                        $trans_str .= ' {!! i18nTextarea("' . $column . '", trans("'
                            . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                            . '"), $errors, $lang, $' . $this->getLowerNameReplacement() . ', ["class" => "form-control formated-textarea ' . $required . '" ]) !!}' . "\n";
                    } elseif ($value['type'] == 'Textdescription') {
                        $trans_str .= ' {!! i18nTextarea("' . $column . '", trans("'
                            . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                            . '"), $errors, $lang, $' . $this->getLowerNameReplacement() . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                    } else {
                        $trans_str .= ' {!! i18nInputOfType(" ","' . $column . '", trans("'
                            . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                            . '"), $errors, $lang, $' . $this->getLowerNameReplacement() . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                    }
                }
                continue;
            }
            $required = array_key_exists('required', $value) ? 'required' : '';
            if (!array_key_exists('image', $value)) {
                if (!array_key_exists('type', $value)) {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType(" ","' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Text') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Number') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType("number","' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control ' . $required . '", "min"=>"0" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Checkbox') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<label for="' . $column . '">{{trans("' . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column . '")}}</label>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="switch">' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<input type="checkbox" value="{{config(' . "'" . 'core.enabled' . "'" . ')}}"  name="' . $column . '"  {{ ($' . $this->getLowerNameReplacement() . '->' . $column . ' == config("core.enabled")) ? "checked" : ""}}>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<span class="slider round"></span>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</label>' . "\n";
                } elseif ($value['type'] == 'Select') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalSelect("' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, [], $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Multiselect') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalSelect("' . $column . '[]", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, [], $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control ' . $required . '", "multiple" => "true" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textarea') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalTextarea("' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control formated-textarea ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textdescription') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalTextarea("' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors,  $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Date') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors,date(config("core.encrypt.php_datepicker_format"),strtotime( $' . $this->getLowerNameReplacement() . '->' . $column . ')), ["id"=>"' . $this->getLowerNameReplacement() . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Time') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, $' . $this->getLowerNameReplacement() . '->' . $column . ', ["id"=>"' . $this->getLowerNameReplacement() . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Email') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType("email","' . $column . '", "'
                        . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column
                        . '", $errors, $' . $this->getLowerNameReplacement() . '->' . $column . ', ["class" => "form-control valid_email ' . $required . '" ]) !!}' . "\n";
                }
            } else {
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {{ normalLabel(trans("' . $this->getLowerNameReplacement() . '::' . $this->getLowerNameReplacement() . '.labels.' . $column . '") , null , [])}}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div class="input-group mb-3">' . "\n\t\t\t\t\t\t\t\t\t\t" . ' <div class="custom-file">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{{ normalFile("' . $column . '","",$errors,["class"=>"custom-file-label ' . $column . ' form-control  is-invalid","id"=> "' . $this->getLowerNameReplacement() . '_' . $column . '", "accept" => $imageTypes ]) }}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="custom-file-label ' . $column . ' hideoverflow" for="' . $this->getLowerNameReplacement() . '_' . $column . '"  >{{ trans("core::core.labels.choose_file") }}</label> </div> <div class="input-group-append"></div></div>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{!! $errors->first("' . $column . '", "<label class=' . "'" . 'error' . "'" . '>:message</label>") !!}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@php' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_extension = (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_type")))?settings("' . $this->getLowerNameReplacement() . '", "image_type"):"jpeg, jpg, png";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$width = (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_width")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_width"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$height = (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_height")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_height"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$ratio = (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_ratio")))?settings("' . $this->getLowerNameReplacement() . '", "image_ratio"):"1";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_max_size = (!empty(settings("' . $this->getLowerNameReplacement() . '", "max_upload_size")))?settings("' . $this->getLowerNameReplacement() . '", "max_upload_size"):"5";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$og_image_param = [' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"module" => Config::get("' . $this->getLowerNameReplacement() . '.name"),' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image" => $' . $this->getLowerNameReplacement() . '->' . $column . ',' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '];' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$resize_image_param = [' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image-type" => "resize",' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image-size" => 100,' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"module" => Config::get("' . $this->getLowerNameReplacement() . '.name"),' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image" => $' . $this->getLowerNameReplacement() . '->' . $column . ',' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '];' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@endphp' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@if(getImageUrl($og_image_param))' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<a href="{{getImageUrl($og_image_param)}}" target="_BLANK">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<img src="{{getImageUrl($resize_image_param)}}" alt="introduction">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '</a>' . "\n";
                if (!array_key_exists('required', $value)) {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalCheckbox("remove_' . $column . '", "Remove Image", $errors, null, ["class" => "form-control" ]) !!}' . "\n";
                }
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@endif' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div class="image-note">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' <lh><b>{{trans("core::core.image-note.label")}}</b></lh>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<li>{{trans("core::core.image-note.min-dimension",["width"=>$width,"height" => $height])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<li>{{trans("core::core.image-note.ratio",["ratio"=>$ratio])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' <li>{{trans("core::core.image-note.max-size",["size"=>$image_max_size])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<li>{{trans("core::core.image-note.file-type",["file_type"=>$image_extension])}}</li>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '</div>' . "\n";
            }
        }

        return [
            'main_module' => $str,
            'translation_module' => $trans_str
        ];
    }


    protected function getControllerUpdateReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('image', $value)) {
                if (!array_key_exists('required', $value)) {
                    $str .= "\t\t\t\t" . 'if (!empty($params["remove_' . $column . '"])) {' . "\n";
                    $str .= "\t\t\t\t\t" . ' $imageRemoveParams = array(' . "\n";
                    $str .= "\t\t\t\t\t\t" . '"module_name" => \Config::get("' . $this->getLowerNameReplacement() . '.name") ,' . "\n";
                    $str .= "\t\t\t\t\t\t" . ' "dbfield" => "' . $column . '",' . "\n";
                    $str .= "\t\t\t\t\t" . ');' . "\n";
                    $str .= "\t\t\t\t\t" . '$this->' . $this->getLowerNameReplacement() . '->setUploadParams($imageRemoveParams)->setModel($' . $this->getLowerNameReplacement() . ')->removeFile($' . $this->getLowerNameReplacement() . '->' . $column . ',"' . $this->getLowerNameReplacement() . '");' . "\n";
                    if ($this->translation) {
                        $str .= "\t\t\t\t\t" . '$params["' . $column . '"] = null;';
                    } else {
                        $str .= "\t\t\t\t\t" . '$params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"] = null;';
                    }
                    $str .= "\t\t\t\t" . '}' . "\n";
                }
                $str .= "\t\t\t\t" . 'if ($request->file("' . $column . '")) {' . "\n";
                $str .= "\t\t\t\t" . 'if (isset($' . $this->getLowerNameReplacement() . '->' . $column . ')) {' . "\n";
                $str .= "\t\t\t\t\t" . ' $imageRemoveParams = array(' . "\n";
                $str .= "\t\t\t\t\t\t" . '"module_name" => \Config::get("' . $this->getLowerNameReplacement() . '.name") ,' . "\n";
                $str .= "\t\t\t\t\t\t" . ' "dbfield" => "' . $column . '",' . "\n";
                $str .= "\t\t\t\t\t" . ');' . "\n";
                $str .= "\t\t\t\t\t" . '$this->' . $this->getLowerNameReplacement() . '->setUploadParams($imageRemoveParams)->setModel($' . $this->getLowerNameReplacement() . ')->removeFile($' . $this->getLowerNameReplacement() . '->' . $column . ',"' . $this->getLowerNameReplacement() . '");' . "\n";
                $str .= "\t\t\t\t\t" . '$params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"] = null;';
                $str .= "\t\t\t\t\t" . '}' . "\n";
                $str .= "\t\t\t\t\t" . ' $imageUploadParams = array(' . "\n";
                $str .= "\t\t\t\t\t\t" . '"module_name" => \Config::get("' . $this->getLowerNameReplacement() . '.name") ,' . "\n";
                $str .= "\t\t\t\t\t\t" . ' "dbfield" => "' . $column . '",' . "\n";
                $str .= "\t\t\t\t\t\t" . ' "thumbnail" => true,' . "\n";
                $str .= "\t\t\t\t\t\t" . ' "thumbnail_size" => 100' . "\n";
                $str .= "\t\t\t\t\t" . ');' . "\n";
                $str .= "\t\t\t\t\t" . ' $formData = $this->' . $this->getLowerNameReplacement() . '->setUploadParams($imageUploadParams)->uploadImage($request);' . "\n";
                if ($this->translation) {
                    $str .= "\t\t\t\t\t" . '$params["' . $column . '"] = $formData["' . $column . '"];' . "\n";
                } else {
                    $str .= "\t\t\t\t\t" . '$params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"] = $formData["' . $column . '"];' . "\n";
                }
                $str .= "\t\t\t\t" . '}' . "\n";
            } else {
                if (!empty($value['type'])) {
                    if ($this->translation) {
                        if ($value['type'] == 'Checkbox') {
                            $str .= "\t\t\t\t" . '$params["' . $column . '"] = (!empty( $params["' . $column . '"] )) ? "1" : "2";' . "\n";
                        }
                        if ($value['type'] == 'Date') {
                            $str .= "\t\t\t\t" . 'if($params["' . $column . '"]){' . "\n";
                            $str .= "\t\t\t\t\t" . '$params["' . $column . '"] = date_format(date_create_from_format(config("core.encrypt.php_datepicker_format"),$params["' . $column . '"]), "Y-m-d");' . "\n";
                            $str .= "\t\t\t\t" . '}' . "\n";
                        }
                        if ($value['type'] == 'Time') {
                            $str .= "\t\t\t\t" . 'if($params["' . $column . '"]){' . "\n";
                            $str .= "\t\t\t\t\t" . '$params["' . $column . '"] = date("H:i:s", strtotime( $params["' . $column . '"]));' . "\n";
                            $str .= "\t\t\t\t" . '}' . "\n";
                        }
                    } else {
                        if ($value['type'] == 'Checkbox') {
                            $str .= "\t\t\t\t" . '$params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"] = (!empty( $params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"] )) ? "1" : "2";' . "\n";
                        }
                        if ($value['type'] == 'Date') {
                            $str .= "\t\t\t\t" . 'if($params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"]){' . "\n";
                            $str .= "\t\t\t\t\t" . '$params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"] = date_format(date_create_from_format(config("core.encrypt.php_datepicker_format"),$params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"]), "Y-m-d");' . "\n";
                            $str .= "\t\t\t\t" . '}' . "\n";
                        }
                        if ($value['type'] == 'Time') {
                            $str .= "\t\t\t\t" . 'if($params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"]){' . "\n";
                            $str .= "\t\t\t\t\t" . '$params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"] = date("H:i:s", strtotime( $params["' . $this->getLowerNameReplacement() . '"]["' . $column . '"]));' . "\n";
                            $str .= "\t\t\t\t" . '}' . "\n";
                        }
                    }
                }
            }
        }

        return $str;
    }

    protected function getCreateRulesReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('required', $value)) {
                if (!array_key_exists('image', $value)) {
                    $unique = (array_key_exists('unique', $value)) ? ' | unique:".$module->getTable().",' . $column : '';
                    $str .= "\t\t\t\t" . '"' . $this->getLowerNameReplacement() . '.' . $column . '" => "required' . $unique . '",' . "\n";
                }
            }
            if (array_key_exists('image', $value)) {
                $required = (array_key_exists('required', $value)) ? 'required' : '';
                $str .= "\t\t\t\t" . '"' . $column . '" => [' . "\n";
                $str .= "\t\t\t\t\t" . '"mimes:" . $this->getImageType() , "max:" . $this->getMaxUpload(), "dimensions:min_width=" . (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_width")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_width"):"100" , ",min_height=" . (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_height")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_height"):"100",' . "\n";
                $str .= "\t\t\t\t\t" . 'function($attribute, $value, $fail) {' . "\n";
                $str .= "\t\t\t\t\t\t" . ' $temp  = (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_ratio")))?settings("' . $this->getLowerNameReplacement() . '", "image_ratio"):"1";' . "\n";
                $str .= "\t\t\t\t\t\t" . '$ratio = (float)$temp;' . "\n";
                $str .= "\t\t\t\t\t\t" . '$origRatio = $this->getImageRatio' . Str::studly($column) . '();' . "\n";
                $str .= "\t\t\t\t\t\t" . ' if ($origRatio != $ratio) {' . "\n";
                $str .= "\t\t\t\t\t\t\t" . ' return $fail(trans("core::core.messages.invalid_image_ratio"));' . "\n";
                $str .= "\t\t\t\t\t\t" . '}' . "\n";
                $str .= "\t\t\t\t\t" . '}' . "\n";
                $str .= "\t\t\t\t" . '],' . "\n";
            }
        }
        return $str;
    }

    protected function getCreateTranslatableRulesReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $trans_str = "\t\t\t" . 'foreach (getLanguageOptions() as $locale => $value) {' . "\n";
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('translation', $value)) {
                if (array_key_exists('required', $value)) {
                    $trans_str .= "\t\t\t\t" . '$rules["{$locale}.' . $column . '"] = "required";' . "\n";
                    continue;
                }
            }

            if (array_key_exists('required', $value)) {
                if (!array_key_exists('image', $value)) {
                    $unique = (array_key_exists('unique', $value)) ? ' | unique:".$module->getTable().",' . $column : '';
                    $str .= "\t\t\t\t" . '$rules["' . $column . '"] = "required' . $unique . '";' . "\n";
                }
            }
            if (array_key_exists('image', $value)) {
                $required = (array_key_exists('required', $value)) ? '"required",' : '';
                $str .= "\t\t\t\t" . '$rules["' . $column . '"] = [' . "\n";
                $str .= "\t\t\t\t\t" . $required . '"mimes:" . $this->getImageType() , "max:" . $this->getMaxUpload(), "dimensions:min_width=" . (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_width")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_width"):"100" , ",min_height=" . (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_height")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_height"):"100",' . "\n";
                $str .= "\t\t\t\t\t" . 'function($attribute, $value, $fail) {' . "\n";
                $str .= "\t\t\t\t\t\t" . ' $temp  = (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_ratio")))?settings("' . $this->getLowerNameReplacement() . '", "image_ratio"):"1";' . "\n";
                $str .= "\t\t\t\t\t\t" . '$ratio = (float)$temp;' . "\n";
                $str .= "\t\t\t\t\t\t" . '$origRatio = $this->getImageRatio' . Str::studly($column) . '();' . "\n";
                $str .= "\t\t\t\t\t\t" . ' if ($origRatio != $ratio) {' . "\n";
                $str .= "\t\t\t\t\t\t\t" . ' return $fail(trans("core::core.messages.invalid_image_ratio"));' . "\n";
                $str .= "\t\t\t\t\t\t" . '}' . "\n";
                $str .= "\t\t\t\t\t" . '}' . "\n";
                $str .= "\t\t\t\t" . '];' . "\n";
            }
        }
        $trans_str .= "}" . "\n";;
        $str = $trans_str . $str;
        return $str;
    }


    protected function getUpdateRulesReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('required', $value)) {
                if (!array_key_exists('image', $value)) {
                    $unique = (array_key_exists('unique', $value)) ? '|unique:".$module->getTable().",' . $column . ',' : '';
                    $unique_id = (array_key_exists('unique', $value)) ? '. $this->id' : '';
                    $str .= "\t\t\t\t" . '"' . $this->getLowerNameReplacement() . '.' . $column . '" => "required' . $unique . '"' . $unique_id . ',' . "\n";
                }
            }
            if (array_key_exists('image', $value)) {
                $required = (array_key_exists('required', $value)) ? 'required' : '';
                $str .= "\t\t\t\t" . '"' . $column . '" => [' . "\n";
                $str .= "\t\t\t\t\t" . '"mimes:" . $this->getImageType() , "max:" . $this->getMaxUpload(), "dimensions:min_width=" . (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_width")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_width"):"100" , ",min_height=" . (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_height")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_height"):"100",' . "\n";
                $str .= "\t\t\t\t\t" . 'function($attribute, $value, $fail) {' . "\n";
                $str .= "\t\t\t\t\t\t" . ' $temp  = (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_ratio")))?settings("' . $this->getLowerNameReplacement() . '", "image_ratio"):"1";' . "\n";
                $str .= "\t\t\t\t\t\t" . '$ratio = (float)$temp;' . "\n";
                $str .= "\t\t\t\t\t\t" . '$origRatio = $this->getImageRatio' . Str::studly($column) . '();' . "\n";
                $str .= "\t\t\t\t\t\t" . ' if ($origRatio != $ratio) {' . "\n";
                $str .= "\t\t\t\t\t\t\t" . ' return $fail(trans("core::core.messages.invalid_image_ratio"));' . "\n";
                $str .= "\t\t\t\t\t\t" . '}' . "\n";
                $str .= "\t\t\t\t\t" . '}' . "\n";
                $str .= "\t\t\t\t" . '],' . "\n";
            }
        }
        return $str;
    }

    protected function getUpdateTranslatableRulesReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $trans_str = "\t\t\t" . 'foreach (getLanguageOptions() as $locale => $value) {' . "\n";
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {

            if (array_key_exists('translation', $value)) {
                if (array_key_exists('required', $value)) {
                    $trans_str .= "\t\t\t\t" . '$rules["{$locale}.' . $column . '"] = "required";' . "\n";
                    continue;
                }
            }

            if (array_key_exists('required', $value)) {
                if (!array_key_exists('image', $value)) {
                    $unique = (array_key_exists('unique', $value)) ? '|unique:".$module->getTable().",' . $column . ',' : '';
                    $unique_id = (array_key_exists('unique', $value)) ? '. $this->id' : '';
                    $str .= "\t\t\t\t" . '$rules["' . $column . '"] = "required' . $unique . '"' . $unique_id . ';' . "\n";
                }
            }
            if (array_key_exists('image', $value)) {
                $required = (array_key_exists('required', $value)) ? '"required",' : '';
                $str .= "\t\t\t\t" . '$rules["' . $column . '"] = [' . "\n";
                $str .= "\t\t\t\t\t" . $required . '"mimes:" . $this->getImageType() , "max:" . $this->getMaxUpload(), "dimensions:min_width=" . (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_width")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_width"):"100" , ",min_height=" . (!empty(settings("' . $this->getLowerNameReplacement() . '", "min_upload_height")))?settings("' . $this->getLowerNameReplacement() . '", "min_upload_height"):"100",' . "\n";
                $str .= "\t\t\t\t\t" . 'function($attribute, $value, $fail) {' . "\n";
                $str .= "\t\t\t\t\t\t" . ' $temp  = (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_ratio")))?settings("' . $this->getLowerNameReplacement() . '", "image_ratio"):"1";' . "\n";
                $str .= "\t\t\t\t\t\t" . '$ratio = (float)$temp;' . "\n";
                $str .= "\t\t\t\t\t\t" . '$origRatio = $this->getImageRatio' . Str::studly($column) . '();' . "\n";
                $str .= "\t\t\t\t\t\t" . ' if ($origRatio != $ratio) {' . "\n";
                $str .= "\t\t\t\t\t\t\t" . ' return $fail(trans("core::core.messages.invalid_image_ratio"));' . "\n";
                $str .= "\t\t\t\t\t\t" . '}' . "\n";
                $str .= "\t\t\t\t\t" . '}' . "\n";
                $str .= "\t\t\t\t" . '];' . "\n";
            }
        }
        $trans_str .= "}" . "\n";;
        $str = $trans_str . $str;
        return $str;
    }


    protected function getRequestFunctionsReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $image = 0;
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('image', $value)) {
                $str .= "\t" . 'public function getImageRatio' . Str::studly($column) . '() {' . "\n";
                $str .= "\t\t" . ' $image_info = getimagesize(Request::file("' . $column . '")->getRealPath());' . "\n";
                $str .= "\t\t" . '$value = round(($image_info[0]/$image_info[1]), 2);' . "\n";
                $str .= "\t\t" . 'return $value;' . "\n";
                $str .= "\t" . '}' . "\n\n";
                if ($image == 0) {
                    $image = 1;
                    $str .= "\t" . 'private function getMaxUpload() {' . "\n";
                    $str .= "\t" . '$maxUploadSize = (!empty(settings("' . $this->getLowerNameReplacement() . '", "max_upload_size"))) ? settings("' . $this->getLowerNameReplacement() . '", "max_upload_size") : "1";' . "\n";
                    $str .= "\t\t" . '$maxUploadServer' . " = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));" . "\n";
                    $str .= "\t\t" . ' $maxUpload = $maxUploadSize > $maxUploadServer ? $maxUploadServer : $maxUploadSize;' . "\n";
                    $str .= "\t\t" . 'return ($maxUpload * 1024);' . "\n";
                    $str .= "\t" . '}' . "\n\n";
                    $str .= "\t" . 'private function getImageType() {' . "\n";
                    $str .= "\t\t" . 'return (!empty(settings("' . $this->getLowerNameReplacement() . '", "image_type"))) ? settings("' . $this->getLowerNameReplacement() . '", "image_type") : "jpg,jpeg,png" ;' . "\n";
                    $str .= "\t" . '}' . "\n\n";
                }
            }
        }

        return $str;
    }

    protected function getRequestMessagesReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('image', $value)) {
                $str .= "\t\t\t" . '"' . $column . '.' . 'mimes" => trans("core::core.validation-message.image.file-type", ["file_type" => $this->getImageType()]), ' . "\n";
                $str .= "\t\t\t" . '"' . $column . '.' . 'max" => trans("core::core.validation-message.image.max-size", ["size" => ($this->getMaxUpload() / 1024)]),' . "\n";
                $str .= "\t\t\t" . '"' . $column . '.' . 'dimensions" => trans("core::core.messages.invalid_dimension"),' . "\n";
            } else {
                if (array_key_exists('unique', $value)) {
                    $str .= "\t\t\t" . '"' . $this->getLowerNameReplacement() . '.' . $column . '.unique" => trans("' .  $this->getLowerNameReplacement()  . '::' .  $this->getLowerNameReplacement()  . '.messages.' . $column . '_unique"),' . "\n";
                }
            }
        }

        return $str;
    }
    protected function getRequestTranslatableMessagesReplacement()
    {
        if (!$this->getColumns()) {
            return '';
        }
        $str = '';
        $columns = $this->getColumns();
        foreach ($columns as $column => $value) {
            if (array_key_exists('image', $value)) {
                $str .= "\t\t\t" . '$rules["' . $column . '.' . 'mimes"] = trans("core::core.validation-message.image.file-type", ["file_type" => $this->getImageType()]); ' . "\n";
                $str .= "\t\t\t" . '$rules["' . $column . '.' . 'max"] = trans("core::core.validation-message.image.max-size", ["size" => ($this->getMaxUpload() / 1024)]);' . "\n";
                $str .= "\t\t\t" . '$rules["' . $column . '.' . 'dimensions"] = trans("core::core.messages.invalid_dimension");' . "\n";
            } else {
                if (array_key_exists('unique', $value)) {
                    $str .= "\t\t\t" . '$rules["' . $column . '.unique"] = trans("' .  $this->getLowerNameReplacement()  . '::' .  $this->getLowerNameReplacement()  . '.messages.' . $column . '_unique");' . "\n";
                }
            }
        }

        return $str;
    }

    public function getSoftDelete()
    {
        $str = '';
        if ($this->translation) {
            if ($this->softDelete) {
                $str = ', SoftDeletes';
            }
        } else {
            if ($this->softDelete) {
                $str = 'use SoftDeletes;';
            }
        }
        return $str;
    }
}
