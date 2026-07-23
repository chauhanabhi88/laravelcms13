<?php

namespace Modules\Core\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Attribute\Repositories\AttributeRepository;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Core\Http\Requests\CreateRequest;
use Modules\Core\Repositories\FolderRepository;
use Modules\Core\Repositories\ModuleRepository;
use Modules\Menu\Models\Menu;
use Modules\Menu\Repositories\MenuRepository;

class ModuleController extends BackendController
{
    protected $module;

    protected $folder;

    public function __construct(ModuleRepository $module, FolderRepository $folder, MenuRepository $menuRepo)
    {
        $this->module = $module;
        $this->folder = $folder;
        parent::__construct();
        $this->menu = $menuRepo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(AttributeRepository $attribute)
    {
        try {
            $this->getAssetManager()->addAsset('modules/theme/backend/bootstrap/bootstrap-switch.min.js');
            $tables = $this->module->getTables();
            // $deleteOptions = $attribute->getAttributeData('on-delete');
            $deleteOptions = $this->module->getForeignKeyDeleteOptions();
            $updateOptions = $this->module->getForeignKeyUpdateOptions();
            $dataTypes = $attribute->getAttributeData('data-type');
            $dbOperations = $attribute->getAttributeData('database-operation');
            $filterOptions = $attribute->getAttributeData('filters', '-- Filter Type --');
            $inputOptions = $attribute->getAttributeData('input-options', '-- Input Type --');
            $collection = $this->module->getModules();
            $modules = $this->module->getModulesName();
            $moduleCheck = $this->module->getEnabledModules();
            $data[''] = '-- After Column --';
            $seeder_table_data[''] = '-- Table Name --';
            $dependent_table_data = [];
            $moduleTypes = $this->module->getModuleTypes(true);
            $yesNoOptions = $this->module->getYesNoOptions(true);

            return view('core::backend.index', compact('collection', 'dataTypes', 'tables', 'deleteOptions', 'updateOptions', 'dbOperations', 'data', 'filterOptions', 'inputOptions', 'modules', 'seeder_table_data', 'moduleCheck', 'dependent_table_data', 'moduleTypes', 'yesNoOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function createModule(Request $request, AttributeRepository $attribute)
    {
        try {
            $moduleType = $request->module_type;
            $tables = $this->module->getTables();
            $deleteOptions = $this->module->getForeignKeyDeleteOptions();
            $updateOptions = $this->module->getForeignKeyUpdateOptions();
            $dataTypes = $attribute->getAttributeData('data-type', '-- Data Type --');
            $filterOptions = $attribute->getAttributeData('filters', '-- Filter Type --');
            $inputOptions = $attribute->getAttributeData('input-options', '-- Input Type --');
            $data[''] = '-- After Column --';
            $yesNoOptions = $this->module->getYesNoOptions(true);

            return view('core::backend.partials.create-module', compact('filterOptions', 'inputOptions', 'deleteOptions', 'updateOptions', 'dataTypes', 'data', 'tables', 'moduleType', 'yesNoOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function create(Request $request)
    {
        try {
            $params = $request->all();
            $request->validate([
                'module_name' => 'required|regex:/^[\w-]*$/',
            ]);
            $columnString = '';
            $columns = (! empty($params['columnName'])) ? $params['columnName'] : null;
            if ($columns) {
                foreach ($columns as $key => $column) {
                    $value = (! empty($params['gridView']) && array_key_exists($key, $params['gridView']) ? 'on' : 'Null');
                    $checkboxValue = $params['filterType'][$key] ? 'on' : $value;
                    $columnString .= $column.';'
                        .($params['filterType'][$key] ? $params['filterType'][$key] : 'Null').';'
                        .$checkboxValue.';'
                        .($params['inputType'][$key] ? $params['inputType'][$key] : 'Null').';'
                        .(! empty($params['isRequired']) && array_key_exists($key, $params['isRequired']) ? 'on' : 'Null').';'
                        .(! empty($params['image']) && array_key_exists($key, $params['image']) ? 'on' : 'Null').';'
                        .(! empty($params['database']['uniqueKey']) && array_key_exists($key, $params['database']['uniqueKey']) ? 'on' : 'Null').';'
                        .(! empty($params['database']['translatable_key']) && array_key_exists($key, $params['database']['translatable_key']) ? 'on' : 'Null').'##';
                }
                $columnString = rtrim($columnString, '##');
            }
            $translation = false;
            $softDelete = ($params['database']['softDelete'] == config('core.yes')) ? true : false;
            if (array_key_exists('translatable_module', $params)) {
                $translation = true;
            }
            \Artisan::call('module:make', [
                'name' => [$params['module_name']],
                '--translation' => $translation,
                '--soft-delete' => $softDelete,
                '--columns' => $columnString,
            ]);
            $params['database'] = (! empty($params['database'])) ? $params['database'] : [];
            $this->createModuleMigration($params['database'], $params['module_name'], null, $translation);

            $studlyName = Str::studly($params['module_name']);
            $moduleName = strtolower($studlyName);
            $permissions = [
                $studlyName => [
                    'admin.'.$moduleName.'.index' => $studlyName.' List',
                    'admin.'.$moduleName.'.create' => $studlyName.' Create',
                ],
            ];
            $this->MenuDataEntry($permissions);

            return redirect()->route('admin.module.index', updateUrlParams())->with('success', trans('core::core.messages.module_create'));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function MenuDataEntry($permissions)
    {
        try {
            if (isset($permissions) && ! empty($permissions)) {
                $array = [];
                $menu = new Menu;
                $menuId = getIncrementedValue($menu->getTable());
                $i = 1;
                foreach ($permissions as $module => $modulePermission) {

                    $menuData = Menu::create([
                        'label' => $module,
                        'parent_id' => 0,
                        'sort_order' => $i++,
                        'link' => null,
                        'status' => config('core.enabled'),
                    ]);
                    $j = 0;
                    foreach ($modulePermission as $value => $label) {
                        $array[] = [
                            'label' => $label,
                            'parent_id' => $menuData->id,
                            'sort_order' => ++$j,
                            'link' => $value,
                            'status' => config('core.enabled'),
                        ];
                    }
                    $menuId = ++$menuId + $j;
                }
                $this->menu->insert($array);
            }
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function update(Request $request)
    {
        try {
            $params = $request->all();
            $this->module->update($params);

            return response()->json([
                'type' => 'success',
                'message' => trans('core::core.messages.module_update'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function clearCache()
    {
        try {
            \Artisan::call('cache:clear');

            return redirect()->route('admin.module.index', updateUrlParams())->with('success', trans('core::core.messages.clear_cache'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function publish(Request $request)
    {
        try {
            if ($module = $request->get('module')) {
                \Artisan::call('module:publish', ['module' => $module]);
            } else {
                \Artisan::call('module:publish');
            }

            return redirect()->route('admin.module.index', updateUrlParams())->with('success', trans('core::core.messages.module_publish_assets'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function migrate(Request $request)
    {
        try {
            if ($module = $request->get('module')) {
                \Artisan::call('module:migrate', ['module' => $module]);
            } else {
                \Artisan::call('module:migrate');
            }

            return redirect()->route('admin.module.index', updateUrlParams())->with('success', trans('core::core.messages.module_migrate'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function createMigration(CreateRequest $request)
    {
        try {
            $params = $request->all();
            $name = $params['name'];
            if ($params['dbOperation'] == 'create') {
                $params['name'] = $params['dbOperation'].'_'.$params['name'].'_table';
            } elseif ($params['dbOperation'] == 'drop') {
                $params['name'] = $params['dbOperation'].'_'.$params['dbTable'].'_table';
            } elseif ($params['dbOperation'] == 'add') {
                $params['name'] = $params['dbOperation'].'_col_to_'.$params['dbTable'].'_table';
            } elseif ($params['dbOperation'] == 'delete') {
                $params['name'] = $params['dbOperation'].'_col_from_'.$params['dbTable'].'_table';
            }

            $fieldsString = '';
            $entitiy = '';

            if ((isset($params['addFields']) && ! empty($params['addFields'])) && (isset($params['dataTypes']) && ! empty($params['dataTypes']))) {
                foreach ($params['addFields'] as $addFieldKey => $addFieldValue) {
                    $entitiy .= "'".$addFieldValue."',";
                    $str = '';
                    $null = '';
                    if (isset($params['defaultKey'][$addFieldKey])) {
                        if ($params['defaultKey'][$addFieldKey] == 'NULL') {
                            $str .= ':default('.$params['defaultKey'][$addFieldKey].')';
                            $null .= ':nullable';
                        } else {
                            $str .= ":default('".$params['defaultKey'][$addFieldKey]."')";
                        }
                    }
                    $fieldLength = (isset($params['lengthKey'][$addFieldKey])) ? ' ,'.$params['lengthKey'][$addFieldKey] : '';
                    $fieldsString .= $addFieldValue.$fieldLength.':'.$params['dataTypes'][$addFieldKey]
                        .(! empty($params['nullableKey']) && array_key_exists($addFieldKey, $params['nullableKey']) ? ':nullable' : $null)
                        .(! empty($params['iKey']) && array_key_exists($addFieldKey, $params['iKey']) ? ':index' : '')
                        .(! empty($params['uniqueKey']) && array_key_exists($addFieldKey, $params['uniqueKey']) ? ':unique' : '')
                        .(! empty($params['unsignedKey']) && array_key_exists($addFieldKey, $params['unsignedKey']) ? ':unsigned' : '')
                        .$str
                        .((isset($params['afterKey'][$addFieldKey])) ? ":after('".$params['afterKey'][$addFieldKey]."')" : '')
                        .(($params['comment'][$addFieldKey]) ? ":comment('".$params['comment'][$addFieldKey]."')" : '')
                        .(! empty($params['foreign']) && array_key_exists($addFieldKey, $params['foreign']) ? ':unsigned' : '')
                        .'##';
                    if (! empty($params['foreign']) && array_key_exists($addFieldKey, $params['foreign'])) {
                        if (! Schema::hasTable($params['foreignTable'][$addFieldKey])) {
                            throw new \Exception(trans('core::core.messages.table_not_found'));
                        }
                        $result = \DB::select(\DB::raw('SHOW KEYS FROM `'.$params['foreignTable'][$addFieldKey].'` WHERE Key_name = \'PRIMARY\''));
                        $primaryKey = $result[0]->Column_name;
                        $onDelete = (isset($params['foreignTableDelete'][$addFieldKey]) ? ':onDelete("'.$params['foreignTableDelete'][$addFieldKey].'")' : '');
                        $onUpdate = (isset($params['foreignTableUpdate'][$addFieldKey]) ? ':onUpdate("'.$params['foreignTableUpdate'][$addFieldKey].'")' : '');
                        $data = '&foreign&'.$addFieldValue.':foreign("'.$addFieldValue.'"):references("'.$primaryKey.'"):on("'.$params['foreignTable'][$addFieldKey].'")'.$onDelete.$onUpdate;
                        $fieldsString .= $data.'##';
                    }
                }

                $entitiy = rtrim($entitiy, ',');
                if ($params['dbOperation'] == 'create') {
                    $softDelete = '';
                    if ($params['softDelete'] == config('core.yes')) {
                        $fieldsString = $fieldsString.'soft_delete##';
                        $softDelete = 'use SoftDeletes;';
                    }
                    \Artisan::call('module:make-custom-entity', [
                        'entity' => $name,
                        'lower_name' => $name,
                        'studly_name' => Str::studly($params['module']),
                        'table_columns' => $entitiy,
                        'module' => $params['module'],
                        'soft_delete' => $softDelete,
                    ]);
                }

                $fieldsString = rtrim($fieldsString, '##');

                \Artisan::call('module:make-migration', [
                    'name' => $params['name'],
                    'module' => $params['module'],
                    '--fields' => $fieldsString,
                ]);
            } else {
                \Artisan::call('module:make-migration', [
                    'name' => $params['name'],
                    'module' => $params['module'],
                ]);
            }

            return redirect()->route('admin.module.index', updateUrlParams())->with('success', trans('core::core.messages.module_create_migrate'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function publishTranslation(Request $request)
    {
        try {
            if ($module = $request->get('module')) {
                \Artisan::call('module:publish-translation', ['module' => $module]);
            } else {
                \Artisan::call('module:publish-translation');
            }

            return redirect()->route('admin.module.index', updateUrlParams())->with('success', trans('core::core.messages.module_publish_translation'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function publishConfig(Request $request)
    {
        try {
            if ($module = $request->get('module')) {
                \Artisan::call('module:publish-config', ['module' => $module]);
            } else {
                \Artisan::call('module:publish-config');
            }

            return redirect()->route('admin.module.index', updateUrlParams())->with('success', trans('core::core.messages.module_publish_config'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function seed(Request $request)
    {
        try {
            if ($module = $request->get('module')) {
                \Artisan::call('module:seed', ['module' => $module]);
            } else {
                \Artisan::call('module:seed');
            }

            return redirect()->route('admin.module.index', updateUrlParams())->with('success', trans('core::core.messages.module_seed'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function getColumns(Request $request)
    {
        try {
            $name = $request->get('value');
            if (! $name) {
                throw new \Exception(trans('core::core.messages.table_not_found'));
            }
            $data = $this->module->getColumnList($name);
            $content = view('core::backend.partials.table-columns', compact('data'));

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'table_column',
                    'html' => $content->__toString(),
                ],
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function createModuleMigration($params, $module, $folder = null, $translation = false)
    {
        try {
            $name = 'create_'.strtolower($module).'_table';
            if ($folder) {
                $name = 'create_'.$folder.'_table';
            }
            $module = Str::studly($module);
            $fieldsString = '';
            $translationString = '';
            $entitiy = '';
            if ((isset($params['addFields']) && ! empty($params['addFields'])) && (isset($params['dataTypes']) && ! empty($params['dataTypes']))) {
                foreach ($params['addFields'] as $addFieldKey => $addFieldValue) {
                    $str = '';
                    $null = '';
                    if (isset($params['defaultKey'][$addFieldKey])) {
                        if ($params['defaultKey'][$addFieldKey] == 'NULL') {
                            $str .= ':default('.$params['defaultKey'][$addFieldKey].')';
                            $null .= ':nullable';
                        } else {
                            $str .= ":default('".$params['defaultKey'][$addFieldKey]."')";
                        }
                    }

                    if ($translation) {
                        if (! empty($params['translatable_key']) && array_key_exists($addFieldKey, $params['translatable_key'])) {
                            $entitiy .= "'".$addFieldValue."',";
                            $translationString .= $addFieldValue.':'.$params['dataTypes'][$addFieldKey]
                                .(! empty($params['nullableKey']) && array_key_exists($addFieldKey, $params['nullableKey']) ? ':nullable' : $null)
                                .(! empty($params['iKey']) && array_key_exists($addFieldKey, $params['iKey']) ? ':index' : '')
                                .(! empty($params['uniqueKey']) && array_key_exists($addFieldKey, $params['uniqueKey']) ? ':unique' : '')
                                .(! empty($params['unsignedKey']) && array_key_exists($addFieldKey, $params['unsignedKey']) ? ':unsigned' : '')
                                .$str
                                .((isset($params['afterKey'][$addFieldKey])) ? ":after('".$params['afterKey'][$addFieldKey]."')" : '')
                                .(($params['comment'][$addFieldKey]) ? ":comment('".$params['comment'][$addFieldKey]."')" : '')
                                .(! empty($params['foreign']) && array_key_exists($addFieldKey, $params['foreign']) ? ':unsigned' : '')
                                .'##';
                            if (! empty($params['foreign']) && array_key_exists($addFieldKey, $params['foreign'])) {
                                if (! Schema::hasTable($params['foreignTable'][$addFieldKey])) {
                                    throw new \Exception(trans('core::core.messages.table_not_found'));
                                }
                                $result = \DB::select(\DB::raw('SHOW KEYS FROM `'.$params['foreignTable'][$addFieldKey].'` WHERE Key_name = \'PRIMARY\''));
                                $primaryKey = $result[0]->Column_name;
                                $onDelete = (isset($params['foreignTableDelete'][$addFieldKey]) ? ':onDelete("'.$params['foreignTableDelete'][$addFieldKey].'")' : '');
                                $onUpdate = (isset($params['foreignTableUpdate'][$addFieldKey]) ? ':onUpdate("'.$params['foreignTableUpdate'][$addFieldKey].'")' : '');
                                $data = '&foreign&'.$addFieldValue.':foreign("'.$addFieldValue.'"):references("'.$primaryKey.'"):on("'.$params['foreignTable'][$addFieldKey].'")'.$onDelete.$onUpdate;
                                $translationString .= $data.'##';
                            }

                            continue;
                        }
                    }

                    $fieldsString .= $addFieldValue.':'.$params['dataTypes'][$addFieldKey]
                        .(! empty($params['nullableKey']) && array_key_exists($addFieldKey, $params['nullableKey']) ? ':nullable' : $null)
                        .(! empty($params['iKey']) && array_key_exists($addFieldKey, $params['iKey']) ? ':index' : '')
                        .(! empty($params['uniqueKey']) && array_key_exists($addFieldKey, $params['uniqueKey']) ? ':unique' : '')
                        .(! empty($params['unsignedKey']) && array_key_exists($addFieldKey, $params['unsignedKey']) ? ':unsigned' : '')
                        .$str
                        .((isset($params['afterKey'][$addFieldKey])) ? ":after('".$params['afterKey'][$addFieldKey]."')" : '')
                        .(($params['comment'][$addFieldKey]) ? ":comment('".$params['comment'][$addFieldKey]."')" : '')
                        .(! empty($params['foreign']) && array_key_exists($addFieldKey, $params['foreign']) ? ':unsigned' : '')
                        .'##';
                    if (! empty($params['foreign']) && array_key_exists($addFieldKey, $params['foreign'])) {
                        if (! Schema::hasTable($params['foreignTable'][$addFieldKey])) {
                            throw new \Exception(trans('core::core.messages.table_not_found'));
                        }
                        $result = \DB::select(\DB::raw('SHOW KEYS FROM `'.$params['foreignTable'][$addFieldKey].'` WHERE Key_name = \'PRIMARY\''));
                        $primaryKey = $result[0]->Column_name;
                        $onDelete = (isset($params['foreignTableDelete'][$addFieldKey]) ? ':onDelete("'.$params['foreignTableDelete'][$addFieldKey].'")' : '');
                        $onUpdate = (isset($params['foreignTableUpdate'][$addFieldKey]) ? ':onUpdate("'.$params['foreignTableUpdate'][$addFieldKey].'")' : '');
                        $data = '&foreign&'.$addFieldValue.':foreign("'.$addFieldValue.'"):references("'.$primaryKey.'"):on("'.$params['foreignTable'][$addFieldKey].'")'.$onDelete.$onUpdate;
                        $fieldsString .= $data.'##';
                    }
                }
                if ($params['softDelete'] == config('core.yes')) {
                    $fieldsString = $fieldsString.'soft_delete##';
                }
                $fieldsString = rtrim($fieldsString, '##');
                if ($fieldsString != '') {
                    \Artisan::call('module:make-migration', [
                        'name' => $name,
                        'module' => $module,
                        '--fields' => $fieldsString,
                    ]);
                }
                if ($translation) {
                    $temp = strtolower($module);
                    if ($folder) {
                        $temp = $folder;
                    }
                    $entitiy = "'".$temp."_id',".$entitiy;
                    $translation_name = 'create_'.$temp.'_translation_table';
                    $relation = $temp.'_id:unsignedBigInteger##&foreign&'.$temp.'_id:foreign("'.$temp.'_id"):references("id"):on("'.$temp.'"):onDelete("cascade"):onUpdate("cascade")##locale:string:index##';
                    $translationString = $relation.$translationString;
                    $translationString = rtrim($translationString, '##');
                    $entitiy = rtrim($entitiy, ',');

                    \Artisan::call('module:make-migration', [
                        'name' => $translation_name,
                        'module' => $module,
                        '--fields' => $translationString,
                    ]);
                    \Artisan::call('module:make-custom-entity', [
                        'entity' => $module.'_translation',
                        'lower_name' => $temp.'_translation',
                        'studly_name' => $module,
                        'table_columns' => $entitiy,
                        'module' => $module,
                        'soft_delete' => '',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function createFolder(AttributeRepository $attribute, Request $request)
    {
        try {
            $folderType = $request->folder_type;
            $tables = $this->module->getTables();
            $deleteOptions = $this->module->getForeignKeyDeleteOptions();
            $updateOptions = $this->module->getForeignKeyUpdateOptions();
            $dataTypes = $attribute->getAttributeData('data-type', '-- Data Type --');
            $filterOptions = $attribute->getAttributeData('filters', '-- Filter Type --');
            $inputOptions = $attribute->getAttributeData('input-options', '-- Input Type --');
            $collection = $this->module->getModules();
            $modules = $this->module->getModulesName();
            $data[''] = '-- After Column --';
            $yesNoOptions = $this->module->getYesNoOptions(true);

            return view('core::backend.partials.create-folder', compact('modules', 'filterOptions', 'inputOptions', 'deleteOptions', 'updateOptions', 'dataTypes', 'data', 'tables', 'yesNoOptions', 'folderType'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function saveFolder(Request $request)
    {
        try {
            $params = $request->all();
            $columnString = '';
            $columns = (! empty($params['columnName'])) ? $params['columnName'] : null;
            if ($columns) {
                foreach ($columns as $key => $column) {
                    $value = (! empty($params['gridView']) && array_key_exists($key, $params['gridView']) ? 'on' : 'Null');
                    $checkboxValue = $params['filterType'][$key] ? 'on' : $value;
                    $columnString .= $column.';'
                        .($params['filterType'][$key] ? $params['filterType'][$key] : 'Null').';'
                        .$checkboxValue.';'
                        .($params['inputType'][$key] ? $params['inputType'][$key] : 'Null').';'
                        .(! empty($params['isRequired']) && array_key_exists($key, $params['isRequired']) ? 'on' : 'Null').';'
                        .(! empty($params['image']) && array_key_exists($key, $params['image']) ? 'on' : 'Null').';'
                        .(! empty($params['database']['uniqueKey']) && array_key_exists($key, $params['database']['uniqueKey']) ? 'on' : 'Null').';'
                        .(! empty($params['database']['translatable_key']) && array_key_exists($key, $params['database']['translatable_key']) ? 'on' : 'Null').'##';
                }
                $columnString = rtrim($columnString, '##');
            }

            $columnsData = $this->folder->getColumns($columnString);
            $folderName = str_replace(' ', '_', strtolower($params['folder_name']));
            $moduleName = $params['module_name'];
            $database = (array_key_exists('database', $params)) ? $params['database'] : [];
            $translation = (array_key_exists('translatable_folder', $params)) ? true : false;
            $softDelete = ($params['database']['softDelete'] == config('core.yes')) ? true : false;
            $this->folder->createEntity($columnsData, $folderName, $moduleName, $softDelete, $translation);
            $this->folder->editConfig($params['folder_name'], $moduleName);
            $this->folder->addLang($columnsData, $params['folder_name'], $moduleName);
            $this->folder->editServiceProvider($params['folder_name'], $moduleName);
            $this->createModuleMigration($database, $moduleName, $folderName, $translation);

            if (array_key_exists('include_view', $params)) {
                $this->folder->editRoute($folderName, $moduleName);
                $this->folder->editPermission($folderName, $moduleName);
                // $this->folder->editMenusSidebar($folderName, $moduleName);
                // $this->folder->editCoreMenu($moduleName);
                $this->folder->createRepository($columnsData, $params['folder_name'], $moduleName, $translation);
                $this->folder->createRequest($columnsData, $params['folder_name'], $moduleName, $translation);
                $this->folder->createController($columnsData, $params['folder_name'], $moduleName, $translation);
                $this->folder->createViews($columnsData, $folderName, $moduleName, $translation);
                $studlyFolderName = Str::studly($params['folder_name']);
                $permissions = [
                    $studlyFolderName => [
                        'admin.'.$folderName.'.index' => $studlyFolderName.' List',
                        'admin.'.$folderName.'.create' => $studlyFolderName.' Create',
                    ],
                ];
                $this->MenuDataEntry($permissions);
            } else {
                $this->folder->createEmptyRepository($params['folder_name'], $moduleName);
            }

            return redirect()->route('admin.module.index', updateUrlParams())->with('success', trans('core::core.messages.folder_create'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function getEntities(Request $request)
    {
        try {
            $name = $request->get('value');
            if (! $name) {
                throw new \Exception(trans('core::core.messages.module_not_found'));
            }
            $studlyName = Str::studly($name);
            if (! array_key_exists($studlyName, $this->module->getModulesName())) {
                throw new \Exception(trans('core::core.messages.module_not_found'));
            }
            $seeder_table_data = $this->module->getEntities($studlyName);
            $content = view('core::backend.partials.seeder-table-names', compact('seeder_table_data'));

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'seeder_table_data',
                    'html' => $content->__toString(),
                ],
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function createSeed(Request $request)
    {
        try {
            $params = $request->all();
            if (! $params['seeder']['module']) {
                throw new \Exception(trans('core::core.messages.module_not_found'));
            }
            if (! array_key_exists($params['seeder']['module'], $this->module->getModulesName())) {
                throw new \Exception(trans('core::core.messages.module_not_found'));
            }
            $this->module->createSeeder($params['seeder']);

            return redirect()->route('admin.module.index', updateUrlParams())->with('success', trans('core::core.messages.module_seed'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function enable(Request $request)
    {
        try {
            if (! $module = $request->get('module')) {
                throw new \Exception(trans('core::core.messages.module_not_found'));
            }
            if (! array_key_exists($module, $this->module->getModulesName())) {
                throw new \Exception(trans('core::core.messages.module_not_found'));
            }
            \Artisan::call('module:enable', ['module' => $module]);

            return redirect()->route('admin.module.index', updateUrlParams())->with('success', trans('core::core.messages.module_enable'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function getDependentModules(Request $request)
    {
        try {
            if (! $module = $request->get('module_value')) {
                throw new \Exception(trans('core::core.messages.module_not_found'));
            }
            $modules = $this->module->getModulesName();
            $modules = $this->module->getJsonModules($module);
            $content = view('core::backend.partials.dependent-modules-names', compact('modules'));
            $hint = $this->module->getHint($module);

            return response()->json([
                'type' => 'success',
                'content' => [
                    [
                        'element' => 'dependent_module_data',
                        'html' => $content->__toString(),
                    ],
                    [
                        'element' => 'note_modules',
                        'html' => $hint,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function addDependency(Request $request)
    {
        try {
            $params = $request->get('dependency');
            $this->module->addDependency($params['module'], $params['support_modules']);

            return redirect()->route('admin.module.index', updateUrlParams())->with('success', trans('core::core.messages.module_dependency'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function maintenanceModeUp(Request $request)
    {
        try {
            \Artisan::call('up');

            return redirect()->route('admin.module.index', updateUrlParams())->with('success', trans('core::core.messages.maintenance_mode_up'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function maintenanceModeDown(Request $request)
    {
        try {
            \Artisan::call('down', ['--secret' => settings('core', 'maintenance_mode_secret')]);

            return redirect()->route('admin.module.index', updateUrlParams())->with('success', trans('core::core.messages.maintenance_mode_down'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function resetFilter(Request $request)
    {
        try {
            $moduleName = $request->module_name;
            $sessionKey = $request->session_key;
            $filterSessionKey = ! empty($sessionKey) ? strtolower($sessionKey) : (isset($moduleName) && ! empty($moduleName) ? strtolower($moduleName) : '');
            if (isset($filterSessionKey) && ! empty($filterSessionKey)) {
                \Session::forget($filterSessionKey.'_filter');
            }
            app(AttributeRepository::class)->flushCache($moduleName);

            return redirect()->back();
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
