<?php

namespace Modules\Core\Repositories;

use Illuminate\Support\Str;

class FolderRepository
{
    public function getColumns($column_string)
    {
        if (!$column_string) {
            return null;
        }
        $array = [];
        $data = [];
        $string = $column_string;
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

    public function createEntity($columns, $table_name, $module, $soft_delete, $translation)
    {
        try {

            $str = '';
            $transStr = '';
            $softDelete = '';
            if ($soft_delete) {
                $softDelete = 'use SoftDeletes;';
            }
            if ($columns) {
                foreach ($columns as $column => $value) {
                    if (array_key_exists('translation', $value)) {
                        $transStr .= "'" . $column . "',";
                        continue;
                    }
                    $str .= "'" . $column . "',";
                }
                $str = rtrim($str, ',');
                $transStr = rtrim($transStr, ',');
            }
            if ($translation) {
                \Artisan::call('module:make-custom-translatable-entity', [
                    'entity' => $table_name,
                    'lower_name' => $table_name,
                    'studly_name' => Str::studly($module),
                    'table_columns' => $str,
                    'translatable_columns' => $transStr,
                    'module' => $module,
                    'soft_delete' => $softDelete
                ]);
            } else {
                \Artisan::call('module:make-custom-entity', [
                    'entity' => $table_name,
                    'lower_name' => $table_name,
                    'studly_name' => Str::studly($module),
                    'table_columns' => $str,
                    'module' => $module,
                    'soft_delete' => $softDelete
                ]);
            }
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with("error", $e->getTraceAsString());
        }
    }

    public function editRoute($table, $module)
    {
        try {
            $str = '
Route::prefix("' . $table . '")->group(function() {
    Route::get("/", [
        "as" => "admin.' . $table . '.index",
        "uses" => "IndexController@index",
        "middleware" => "can:admin.' . $table . '.index"
    ]);

    Route::post("/filters", [
        "as" => "admin.' . $table . '.filters",
        "uses" => "IndexController@filters",
        "middleware" => "can:admin.' . $table . '.filters"
    ]);

    Route::get("/create", [
        "as" => "admin.' . $table . '.create",
        "uses" => "IndexController@create",
        "middleware" => "can:admin.' . $table . '.create"
    ]);

    Route::post("/", [
        "as" => "admin.' . $table . '.store",
        "uses" => "IndexController@store",
        "middleware" => "can:admin.' . $table . '.create"
    ]);

    Route::get("/edit/{id}", [
        "as" => "admin.' . $table . '.edit",
        "uses" => "IndexController@edit",
        "middleware" => "can:admin.' . $table . '.edit"
    ]);

    Route::put("/{id}", [
        "as" => "admin.' . $table . '.update",
        "uses" => "IndexController@update",
        "middleware" => "can:admin.' . $table . '.edit"
    ]);

    Route::post("/update_status", [
        "as" => "admin.' . $table . '.update_status",
        "uses" => "IndexController@updateStatus",
        "middleware" => "can:admin.' . $table . '.edit"
    ]);

    Route::delete("/delete/{id}", [
        "as" => "admin.' . $table . '.delete",
        "uses" => "IndexController@delete",
        "middleware" => "can:admin.' . $table . '.delete"
    ]);

    Route::delete("/massDelete", [
        "as" => "admin.' . $table . '.mass_delete",
        "uses" => "IndexController@massDelete",
        "middleware" => "can:admin.' . $table . '.mass_delete"
    ]);
});
        ';

            $data = str_replace('IndexController', Str::studly($table) . 'Controller', $str);
            $dir = __DIR__ . '/../../../' .  Str::studly($module) . '/routes/backend.php';
            $filecontent = file_get_contents($dir);
            $filecontent .= $data;
            file_put_contents($dir, $filecontent);
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with("error", $e->getTraceAsString());
        }
    }

    public function editPermission($table, $module)
    {
        try {
            $data = '
        "admin.' . $table . '.index" => "' .  strtolower($module) . '::' . $table . '.labels.list",
        "admin.' . $table . '.filters" => "' .  strtolower($module) . '::' . $table . '.labels.filters",
        "admin.' . $table . '.create" => "' .  strtolower($module) . '::' . $table . '.labels.create",
        "admin.' . $table . '.edit" => "' .  strtolower($module) . '::' . $table . '.labels.edit",
        "admin.' . $table . '.delete" => "' .  strtolower($module) . '::' . $table . '.labels.delete",
        "admin.' . $table . '.mass_delete" => "' .  strtolower($module) . '::' . $table . '.labels.mass_delete",
        "admin.' . $table . '.update_status" => "' .  strtolower($module) . '::' . $table . '.labels.update_status",';
            $dir = __DIR__ . '/../../../' .  Str::studly($module) . '/config/permissions.php';
            $filecontent = file_get_contents($dir);
            $pos = strpos($filecontent, '],');
            $filecontent = substr($filecontent, 0, $pos) . "\n" . $data . "\n\t" . substr($filecontent, $pos);
            file_put_contents($dir, $filecontent);
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with("error", $e->getTraceAsString());
        }
    }

    public function editConfig($table, $module)
    {
        try {
            $lower_name = str_replace(' ', '_', strtolower($table));
            $data = '
    "' . $lower_name . '_name" => "' . Str::studly($table) . '",';
            $dir = __DIR__ . '/../../../' .  Str::studly($module) . '/config/config.php';
            
            $filecontent = file_get_contents($dir);
            $pos = strpos($filecontent, '];');
            $filecontent = substr($filecontent, 0, $pos) . "\n" . $data . "\n" . substr($filecontent, $pos);
            file_put_contents($dir, $filecontent);
            $filecontent = file_get_contents($dir);
            $string = "'cache' => [";
            $length = strlen($string);
            $pos = strpos($filecontent, $string);
            if ($pos) {
                $filecontent = substr($filecontent, 0, $pos + $length) . "\n" . $data . "\n" . substr($filecontent, $pos + $length);

                file_put_contents($dir, $filecontent);
            }
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with("error", $e->getTraceAsString());
        }
    }

    public function addLang($columns, $table, $module)
    {
        try {
            $titles = '';
            $unique_message = '';
            if ($columns) {
                foreach ($columns as $column => $value) {
                    $titles .= "\t\t" . '"' . $column . '" => "' . $value['column'] . '",' . "\n";
                    if (!empty($value['unique'])) {
                        $unique_message .= "\t\t" . '"' . $column . '_unique" => "' . $value['column'] . ' should be unique.",' . "\n";
                    }
                }
            }

            \Artisan::call('module:make-custom-lang', [
                'lower_name' => str_replace(' ', '_', strtolower($table)),
                'studly_name' => $table,
                'titles' => $titles,
                'module' => $module,
                'message' => $unique_message,
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with("error", $e->getTraceAsString());
        }
    }

    public function editMenusSidebar($table, $module)
    {
        try {
            $dir = __DIR__ . '/../../../' .  Str::studly($module) . '/Sidebar/MenuSidebar.php';
            $filecontent = file_get_contents($dir);
            $pos = strpos($filecontent, '"group" => "core::core.menu.single",');
            if ($pos) {
                $end_pos = strpos($filecontent, '];');
                $end = '
            ],';
                $filecontent = substr($filecontent, 0, $end_pos) . "\r\n" . $end . "\r\n\t\t\t" . substr($filecontent, $end_pos);
                file_put_contents($dir, $filecontent);
                $filecontent = str_replace('"group" => "core::core.menu.single",', '[ "group" => "core::core.menu.' . strtolower($module) . '",', $filecontent);
                $filecontent = str_replace('$this->_menu->addMenuItem($menuItems);', '$this->_menu->addMenuItems($menuItems);', $filecontent);
            }
            $data = '
            [
                "group" => "core::core.menu.' . strtolower($module) . '",
                "title" => "' . strtolower($module) . '::' . $table . '.titles.' . $table . '",
                "route" => "admin.' . $table . '.index",
                "icon" => "fas fa-images nav-icon",
                "active_actions" => [
                    "admin.' . $table . '.index",
                    "admin.' . $table . '.create",
                    "admin.' . $table . '.edit"
                ],
                //"create" => "admin.' . $table . '.create",
                "order" => 20
            ],
            ';
            $end_pos = strpos($filecontent, '];');
            $filecontent = substr($filecontent, 0, $end_pos) . "\n" . $data . "\n\t\t\t" . substr($filecontent, $end_pos);
            file_put_contents($dir, $filecontent);
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with("error", $e->getTraceAsString());
        }
    }

    public function editCoreMenu($module)
    {
        try {
            $dir = __DIR__ . '/../../resources/lang/en/core.php';
            $filecontent = file_get_contents($dir);
            $pos = strpos($filecontent, "'core_modules' => 'Core Modules',");
            $data = "'" . strtolower($module) . "' => '" . $module . "',";
            $filecontent = substr($filecontent, 0, $pos) . $data . "\n\t\t" . substr($filecontent, $pos);
            file_put_contents($dir, $filecontent);
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with("error", $e->getTraceAsString());
        }
    }

    public function createRepository($columns, $table, $module, $translation)
    {
        $namespace = "Modules";
        $lower_name = str_replace(' ', '_', strtolower($table));
        try {
            \Artisan::call('module:make-custom-repository', [
                'studly_name' => $module,
                'module_namespace' => $namespace,
                'module' => $module,
                'repository' => Str::studly($table),
            ]);

            \Artisan::call('module:make-custom-eloquent', [
                'lower_name' => $lower_name,
                'studly_name' => $module,
                'module_namespace' => $namespace,
                'grid_columns' => $this->getGridColumns($columns, $lower_name, $module),
                'filters_options' => $this->getFiltersOptions($columns, $lower_name, $module),
                'pagination' => $this->getPagination($columns, $translation, $lower_name, $module),
                'eloquent' =>  Str::studly($table),
                'cache_key' => strtolower($module) . '.cache.' . $lower_name . '_',
                'module' => $module,
            ]);

            \Artisan::call('module:make-custom-cache', [
                'lower_name' => $lower_name,
                'studly_name' => $module,
                'cache' => Str::studly($table),
                'module' => $module,
                'entity' => strtolower($module) . '.cache.' . $lower_name . '_',
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with("error", $e->getTraceAsString());
        }
    }


    public function createEmptyRepository($table, $module)
    {
        $namespace = "Modules";
        $lower_name = str_replace(' ', '_', strtolower($table));
        try {
            \Artisan::call('module:make-custom-repository', [
                'studly_name' => $module,
                'module_namespace' => $namespace,
                'module' => $module,
                'repository' => Str::studly($table),
            ]);

            \Artisan::call('module:make-custom-empty-eloquent', [
                'lower_name' => $lower_name,
                'studly_name' => $module,
                'module_namespace' => $namespace,
                'eloquent' =>  Str::studly($table),
                'module' => $module,
            ]);

            \Artisan::call('module:make-custom-empty-cache', [
                'lower_name' => $lower_name,
                'studly_name' => $module,
                'cache' => Str::studly($table),
                'module' => $module,
                'entity' => strtolower($module) . '.' . $lower_name . '_',
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with("error", $e->getTraceAsString());
        }
    }

    public function editServiceProvider($table, $module)
    {
        try {
            $dir = __DIR__ . '/../../../' .  Str::studly($module) . '/app/Providers/' . Str::studly($module) . 'ServiceProvider.php';
            $filecontent = file_get_contents($dir);
            $pos = strpos($filecontent, '  $this->app->bind(');
            $data = '
        $this->app->bind(
            "Modules' . '\\' . $module . '\\' . 'Repositories\\' . Str::studly($table) . 'Repository",
            function () {
                $repository = new \Modules' . '\\' . $module . '\\' . 'Repositories\Eloquent\Eloquent' . Str::studly($table) . 'Repository(new \Modules' . '\\' . $module . '\\' . 'Models\\' . Str::studly($table) . ');

                if (! getModule("' . strtolower($module) . '", "cache")) {
                    return $repository;
                }

                return new \Modules' . '\\' . $module . '\\' . 'Repositories\Cache\Cache' . Str::studly($table) . 'Decorator($repository);
            }
        );
            ';
            $filecontent = substr($filecontent, 0, $pos) . $data . "\n\t\t" . substr($filecontent, $pos);
            file_put_contents($dir, $filecontent);
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with("error", $e->getTraceAsString());
        }
    }

    public function createRequest($columns, $table, $module, $translation)
    {
        try {
            $lower_name = str_replace(' ', '_', strtolower($table));

            if ($translation) {
                \Artisan::call('module:make-custom-folder-translatable-create-request', [
                    'studly_name' => $module,
                    'request_functions' =>  $this->getRequestFunctions($columns, $module),
                    'create_translatable_rules' => $this->getCreateTranslatableRules($columns, $lower_name),
                    'request_translatable_messages' => $this->getRequestTranslatableMessages($columns, $lower_name),
                    'module' => $module,
                    'request' => Str::studly($table),
                ]);

                \Artisan::call('module:make-custom-folder-translatable-update-request', [
                    'studly_name' => $module,
                    'request_functions' =>  $this->getRequestFunctions($columns, $module),
                    'update_translatable_rules' => $this->getUpdateTranslatableRules($columns, $lower_name),
                    'request_translatable_messages' => $this->getRequestTranslatableMessages($columns, $lower_name),
                    'module' => $module,
                    'request' => Str::studly($table),
                ]);
            } else {
                \Artisan::call('module:make-custom-create-request', [
                    'studly_name' => $module,
                    'module' => $module,
                    'request' => Str::studly($table),
                    'lower_name' => $lower_name,
                    'create_rules' => $this->getCreateRules($columns, $lower_name, $module),
                    'request_functions' => $this->getRequestFunctions($columns, $module),
                    'request_messages' => $this->getRequestMessages($columns, $lower_name, $module)
                ]);

                \Artisan::call('module:make-custom-update-request', [
                    'studly_name' => $module,
                    'module' => $module,
                    'request' => Str::studly($table),
                    'lower_name' => $lower_name,
                    'update_rules' => $this->getUpdateRules($columns, $lower_name, $module),
                    'request_functions' => $this->getRequestFunctions($columns, $module),
                    'request_messages' => $this->getRequestMessages($columns, $lower_name, $module)
                ]);
            }
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with("error", $e->getTraceAsString());
        }
    }

    public function createController($columns, $table, $module, $translation)
    {
        try {
            $lower_name = str_replace(' ', '_', strtolower($table));
            if ($translation) {
                \Artisan::call('module:make-custom-folder-translatable-controller', [
                    'studly_name' => $module,
                    'module' => $module,
                    'controller' => Str::studly($table),
                    'lower_name' => $lower_name,
                    'controller_data' => $this->getControllerData($columns, $module),
                    'controller_variable' => $this->getControllerVariable($columns, $translation),
                    'controller_store' => $this->getControllerStore($columns, $lower_name, $module, $translation),
                    'module_lower_name' => strtolower($module),
                    'controller_edit_variable' => $this->getControllerEditVariable($columns),
                    'controller_update' => $this->getControllerUpdate($columns, $lower_name, $module, $translation),
                    'controller_index' => $this->getControllerIndex($columns)
                ]);
            } else {
                \Artisan::call('module:make-custom-controller', [
                    'studly_name' => $module,
                    'module' => $module,
                    'controller' => Str::studly($table),
                    'lower_name' => $lower_name,
                    'controller_data' => $this->getControllerData($columns, $module),
                    'controller_variable' => $this->getControllerVariable($columns, $translation),
                    'controller_store' => $this->getControllerStore($columns, $lower_name, $module, $translation),
                    'module_lower_name' => strtolower($module),
                    'controller_edit_variable' => $this->getControllerEditVariable($columns),
                    'controller_update' => $this->getControllerUpdate($columns, $lower_name, $module, $translation),
                    'controller_index' => $this->getControllerIndex($columns)
                ]);
            }
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with("error", $e->getTraceAsString());
        }
    }

    public function createViews($columns, $table, $module, $translation)
    {
        try {

            \Artisan::call('module:make-custom-blade-index', [
                'module' => $module,
                'lower_name' => $table,
                'module_lower_name' => strtolower($module),
            ]);

            \Artisan::call('module:make-custom-blade-grid', [
                'module' => $module,
                'lower_name' => $table,
                'column_count' => $this->getColumnCount($columns),
                'data' => $this->getData($columns, $table, $module)
            ]);

            if ($translation) {
                \Artisan::call('module:make-custom-folder-translatable-blade-create', [
                    'module' => $module,
                    'lower_name' => $table,
                    'module_lower_name' => strtolower($module),
                    'create_fields' => $this->getCreateTranslatableFields($columns, $table, $module)['main_module'],
                    'scripts' => $this->getScripts($columns, $table, $module)
                ]);

                \Artisan::call('module:make-custom-folder-translatable-blade-create-translatable', [
                    'create_translatable_fields' => $this->getCreateTranslatableFields($columns, $table, $module)['translation_module'],
                    'module' =>  $module,
                    'lower_name' => $table,
                ]);

                \Artisan::call('module:make-custom-folder-translatable-blade-edit', [
                    'module' => $module,
                    'lower_name' => $table,
                    'module_lower_name' => strtolower($module),
                    'edit_fields' => $this->getEditTranslatableFields($columns, $table, $module)['main_module'],
                    'scripts' => $this->getScripts($columns, $table, $module)
                ]);

                \Artisan::call('module:make-custom-folder-translatable-blade-edit-translatable', [
                    'edit_translatable_fields' => $this->getEditTranslatableFields($columns, $table, $module)['translation_module'],
                    'module' =>  $module,
                    'lower_name' => $table,
                ]);
            } else {
                \Artisan::call('module:make-custom-blade-create', [
                    'module' => $module,
                    'lower_name' => $table,
                    'module_lower_name' => strtolower($module),
                    'create_fields' => $this->getCreateFields($columns, $table, $module),
                    'scripts' => $this->getScripts($columns, $table, $module)
                ]);

                \Artisan::call('module:make-custom-blade-edit', [
                    'module' => $module,
                    'lower_name' => $table,
                    'module_lower_name' => strtolower($module),
                    'edit_fields' => $this->getEditFields($columns, $table, $module),
                    'scripts' => $this->getScripts($columns, $table, $module)
                ]);
            }
        } catch (\Throwable $e) {
            return redirect()->route('admin.module.index', updateUrlParams())->with("error", $e->getTraceAsString());
        }
    }

    public function getGridColumns($columns, $table, $module)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        foreach ($columns as $column => $value) {
            if (array_key_exists('grid', $value)) {
                $str .= "\t\t\t[\n";
                $str .= "\t\t\t\t" . '"title" => trans("' . strtolower($module) . '::' . $table . '.titles.' . $column . '"),' . "\n";
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

    public function getFiltersOptions($columns, $table, $module)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        foreach ($columns as $column => $value) {
            if (array_key_exists('grid', $value)) {
                if (array_key_exists('filter', $value)) {
                    $str .= "\t\t\t[\n";
                    $str .= "\t\t\t" . '"type" => "' . $value['filter'] . '",' . "\n";
                    $str .= "\t\t\t" . '"row"  => "1",' . "\n";
                    if ($value['filter']  == 'text' || $value['filter'] == 'select') {
                        $str .= "\t\t\t" . '"name" => "' . $column . '",' . "\n";
                        $str .= "\t\t\t" . '"value" => $request->get("' . $column . '", getSessionFilter(config("'.strtolower($module).'.cache.'.$table.'_name") , "' . $column . '")),' . "\n";
                        if ($value['filter']  == 'text') {
                            $str .= "\t\t\t" . '"options" => ["placeholder" => trans("' . strtolower($module) . '::' . $table . '.titles.' . $column . '"), "class" => "form-control"]' . "\n";
                        } else {
                            $str .= "\t\t\t" . '"select_options" => [],' . "\n";
                            $str .= "\t\t\t" . '"options" => ["label" => trans("' . strtolower($module) . '::' . $table . '.titles.' . $column . '"), "class" => "form-control"]' . "\n";
                        }
                    } elseif ($value['filter'] == 'number_range' || $value['filter'] == 'date_range' || $value['filter'] == 'time_range') {
                        $str .= "\t\t\t" . '"name" => ["' . $column . '_from","' . $column . '_to"],' . "\n";
                        $str .= "\t\t\t" . '"value" => [' . "\n";
                        $str .= "\t\t\t\t" . '"' . $column . '_from" => $request->get("' . $column . '_from", getSessionFilter(config("'.strtolower($module).'.cache.'.$table.'_name") , "' . $column . '_from")),' . "\n";
                        $str .= "\t\t\t\t" . '"' . $column . '_to" => $request->get("' . $column . '_to", getSessionFilter(config("'.strtolower($module).'.cache.'.$table.'_name") , "' . $column . '_to")),' . "\n";
                        $str .= "\t\t\t" . '],' . "\n";
                        $str .= "\t\t\t" . '"options" => [' . "\n";
                        $str .= "\t\t\t\t" . '"' . $column . '_from" => ["label" => trans("' . strtolower($module) . '::' . $table . '.titles.' . $column . '"),"placeholder" => trans("core::core.labels.from"), "class" => "form-control"],' . "\n";
                        $str .= "\t\t\t\t" . '"' . $column . '_to"   => ["placeholder" => trans("core::core.labels.to"), "class" => "form-control"]' . "\n";
                        $str .= "\t\t\t" . ']' . "\n";
                    }
                    $str .= "\t\t\t" . '],' . "\n";
                }
            }
        }
        return $str;
    }

    public function getPagination($columns, $translation, $table, $module)
    {
        $transListings = '';
        if ($translation) {
            $transListings = '->listsTranslations([' . $this->getTableColumns($columns)['translation_module'] . '])';
        }
        $temp = "\t\t\t" . 'return $collection' . $transListings.';';
        if (!$columns) {
            return $temp;
        }
        $str = '';
        foreach ($columns as $column => $value) {
            if (array_key_exists('grid', $value)) {
                if (array_key_exists('filter', $value)) {
                    $module = strtolower($module);
                    if ($value['filter']  == 'text') {
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'Cond = $request->get("' . $column . '", getSessionFilter(config("'.$module.'.cache.'.$table.'_name") , "' . $column . '"));' . "\n";
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
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'Cond = $request->get("' . $column . '", getSessionFilter(config("'.$module.'.cache.'.$table.'_name") , "' . $column . '"));' . "\n";
                        $str .= "\t\t\t" . 'if($where' . Str::studly($column) . 'Cond !== null) {' . "\n";
                        $str .= "\t\t\t\t" . '$collection->where("' . $column . '", "$where' . Str::studly($column) . 'Cond");' . "\n";
                        $str .= "\t\t\t" . '}' . "\n";
                    } elseif ($value['filter'] == 'number_range') {
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'FromCond = $request->get("' . $column . '_from", getSessionFilter(config("'.$module.'.cache.'.$table.'_name") , "' . $column . '_from"));' . "\n";
                        $str .= "\t\t\t" . 'if($where' . Str::studly($column) . 'FromCond !== null) {' . "\n";
                        $str .= "\t\t\t\t" . '$collection->where("' . $column . '", ">=", $where' . Str::studly($column) . 'FromCond);' . "\n";
                        $str .= "\t\t\t" . '}' . "\n";
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'ToCond = $request->get("' . $column . '_to", getSessionFilter(config("'.$module.'.cache.'.$table.'_name") , "' . $column . '_to"));' . "\n";
                        $str .= "\t\t\t" . 'if($where' . Str::studly($column) . 'ToCond !== null) {' . "\n";
                        $str .= "\t\t\t\t" . '$collection->where("' . $column . '", "<=", $where' . Str::studly($column) . 'ToCond);' . "\n";
                        $str .= "\t\t\t" . '}' . "\n";
                    } elseif ($value['filter'] == 'date_range') {
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'FromCond = $request->get("' . $column . '_from", getSessionFilter(config("'.$module.'.cache.'.$table.'_name") , "' . $column . '_from"));' . "\n";
                        $str .= "\t\t\t" . 'if($where' . Str::studly($column) . 'FromCond !== null) {' . "\n";
                        $str .= "\t\t\t\t" . '$collection->whereRaw("DATE(' . $column . ' + INTERVAL {$timezoneOffset} SECOND) >= ?",  date_format(date_create_from_format(config("core.encrypt.php_datepicker_format"), $where' . Str::studly($column) . 'FromCond), "Y-m-d"));' . "\n";
                        $str .= "\t\t\t" . '}' . "\n";
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'ToCond = $request->get("' . $column . '_to", getSessionFilter(config("'.$module.'.cache.'.$table.'_name") , "' . $column . '_to"));' . "\n";
                        $str .= "\t\t\t" . 'if($where' . Str::studly($column) . 'ToCond !== null) {' . "\n";
                        $str .= "\t\t\t\t" . '$collection->whereRaw("DATE(' . $column . ' + INTERVAL {$timezoneOffset} SECOND) <= ?",  date_format(date_create_from_format(config("core.encrypt.php_datepicker_format"), $where' . Str::studly($column) . 'ToCond), "Y-m-d"));' . "\n";
                        $str .= "\t\t\t" . '}' . "\n";
                    } elseif ($value['filter'] == 'time_range') {
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'FromCond = $request->get("' . $column . '_from", getSessionFilter(config("'.$module.'.cache.'.$table.'_name") , "' . $column . '_from"));' . "\n";
                        $str .= "\t\t\t" . 'if($where' . Str::studly($column) . 'FromCond !== null) {' . "\n";
                        $str .= "\t\t\t\t" . '$collection->whereRaw("DATE(' . $column . ' + INTERVAL {$timezoneOffset} SECOND) >= ?",  date("H:i:s", strtotime($where' . Str::studly($column) . 'FromCond)));' . "\n";
                        $str .= "\t\t\t" . '}' . "\n";
                        $str .= "\t\t\t" . '$where' . Str::studly($column) . 'ToCond = $request->get("' . $column . '_to", getSessionFilter(config("'.$module.'.cache.'.$table.'_name") , "' . $column . '_to"));' . "\n";
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

    public function getCreateRules($columns, $table, $module)
    {
        if (!$columns) {
            return '';
        }
        $str = '';

        foreach ($columns as $column => $value) {
            if (array_key_exists('required', $value)) {
                if (!array_key_exists('image', $value)) {
                    $unique = (array_key_exists('unique', $value)) ? ' | unique:".$module->getTable().",' . $column : '';
                    $str .= "\t\t\t\t" . '"' . $table . '.' . $column . '" => "required' . $unique . '",' . "\n";
                }
            }
            if (array_key_exists('image', $value)) {
                $required = (array_key_exists('required', $value)) ? 'required' : '';
                $str .= "\t\t\t\t" . '"' . $column . '" => [' . "\n";
                $str .= "\t\t\t\t\t" . '"mimes:" . $this->getImageType() , "max:" . $this->getMaxUpload(), "dimensions:min_width=" . (!empty(settings("' . strtolower($module) . '", "min_upload_width")))?settings("' . strtolower($module) . '", "min_upload_width"):"100" , ",min_height=" . (!empty(settings("' . strtolower($module) . '", "min_upload_height")))?settings("' . strtolower($module) . '", "min_upload_height"):"100",' . "\n";
                $str .= "\t\t\t\t\t" . 'function($attribute, $value, $fail) {' . "\n";
                $str .= "\t\t\t\t\t\t" . ' $temp  = (!empty(settings("' . strtolower($module) . '", "image_ratio")))?settings("' . strtolower($module) . '", "image_ratio"):"1";' . "\n";
                $str .= "\t\t\t\t\t\t" . '$ratio = (float)$temp;' . "\n";
                $str .= "\t\t\t\t\t\t" . '$origRatio = $this->getImageRatio();' . "\n";
                $str .= "\t\t\t\t\t\t" . ' if ($origRatio != $ratio) {' . "\n";
                $str .= "\t\t\t\t\t\t\t" . ' return $fail(trans("core::core.messages.invalid_image_ratio"));' . "\n";
                $str .= "\t\t\t\t\t\t" . '}' . "\n";
                $str .= "\t\t\t\t\t" . '}' . "\n";
                $str .= "\t\t\t\t" . '],' . "\n";
            }
        }
        return $str;
    }

    public function getRequestFunctions($columns, $module)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        $image = 0;
        foreach ($columns as $column => $value) {
            if (array_key_exists('image', $value)) {
                $str .= "\t" . 'public function getImageRatio() {' . "\n";
                $str .= "\t\t" . ' $image_info = getimagesize(Request::file("' . $column . '")->getRealPath());' . "\n";
                $str .= "\t\t" . '$value = round(($image_info[0]/$image_info[1]), 2);' . "\n";
                $str .= "\t\t" . 'return $value;' . "\n";
                $str .= "\t" . '}' . "\n\n";
                if ($image == 0) {
                    $image = 1;
                    $str .= "\t" . 'private function getMaxUpload() {' . "\n";
                    $str .= "\t" . '$maxUploadSize = (!empty(settings("' . strtolower($module) . '", "max_upload_size"))) ? settings("' . strtolower($module) . '", "max_upload_size") : "1";' . "\n";
                    $str .= "\t\t" . '$maxUploadServer' . " = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));" . "\n";
                    $str .= "\t\t" . ' $maxUpload = $maxUploadSize > $maxUploadServer ? $maxUploadServer : $maxUploadSize;' . "\n";
                    $str .= "\t\t" . 'return ($maxUpload * 1024);' . "\n";
                    $str .= "\t" . '}' . "\n\n";
                    $str .= "\t" . 'private function getImageType() {' . "\n";
                    $str .= "\t\t" . 'return (!empty(settings("' . strtolower($module) . '", "image_type"))) ? settings("' . strtolower($module) . '", "image_type") : "jpg,jpeg,png" ;' . "\n";
                    $str .= "\t" . '}' . "\n\n";
                }
            }
        }

        return $str;
    }

    public function getRequestMessages($columns, $table, $module)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        foreach ($columns as $column => $value) {
            if (array_key_exists('image', $value)) {
                $str .= "\t\t\t" . '"' . $column . '.' . 'mimes" => trans("core::core.validation-message.image.file-type", ["file_type" => $this->getImageType()]), ' . "\n";
                $str .= "\t\t\t" . '"' . $column . '.' . 'max" => trans("core::core.validation-message.image.max-size", ["size" => ($this->getMaxUpload() / 1024)]),' . "\n";
                $str .= "\t\t\t" . '"' . $column . '.' . 'dimensions" => trans("core::core.messages.invalid_dimension"),' . "\n";
            } else {
                if (array_key_exists('unique', $value)) {
                    $str .= "\t\t\t" . '"' . $table . '.' . $column . '.unique" => trans("' .  strtolower($module)  . '::' .  $table  . '.messages.' . $column . '_unique"),' . "\n";
                }
            }
        }

        return $str;
    }

    public function getUpdateRules($columns, $table, $module)
    {
        if (!$columns) {
            return '';
        }
        $str = '';

        foreach ($columns as $column => $value) {
            if (array_key_exists('required', $value)) {
                if (!array_key_exists('image', $value)) {
                    $unique = (array_key_exists('unique', $value)) ? '|unique:".$module->getTable().",' . $column . ',' : '';
                    $unique_id = (array_key_exists('unique', $value)) ? '. $this->id' : '';
                    $str .= "\t\t\t\t" . '"' . $table . '.' . $column . '" => "required' . $unique . '"' . $unique_id . ',' . "\n";
                }
            }
            if (array_key_exists('image', $value)) {
                $required = (array_key_exists('required', $value)) ? 'required' : '';
                $str .= "\t\t\t\t" . '"' . $column . '" => [' . "\n";
                $str .= "\t\t\t\t\t" . '"mimes:" . $this->getImageType() , "max:" . $this->getMaxUpload(), "dimensions:min_width=" . (!empty(settings("' . strtolower($module) . '", "min_upload_width")))?settings("' . strtolower($module) . '", "min_upload_width"):"100" , ",min_height=" . (!empty(settings("' . strtolower($module) . '", "min_upload_height")))?settings("' . strtolower($module) . '", "min_upload_height"):"100",' . "\n";
                $str .= "\t\t\t\t\t" . 'function($attribute, $value, $fail) {' . "\n";
                $str .= "\t\t\t\t\t\t" . ' $temp  = (!empty(settings("' . strtolower($module) . '", "image_ratio")))?settings("' . strtolower($module) . '", "image_ratio"):"1";' . "\n";
                $str .= "\t\t\t\t\t\t" . '$ratio = (float)$temp;' . "\n";
                $str .= "\t\t\t\t\t\t" . '$origRatio = $this->getImageRatio();' . "\n";
                $str .= "\t\t\t\t\t\t" . ' if ($origRatio != $ratio) {' . "\n";
                $str .= "\t\t\t\t\t\t\t" . ' return $fail(trans("core::core.messages.invalid_image_ratio"));' . "\n";
                $str .= "\t\t\t\t\t\t" . '}' . "\n";
                $str .= "\t\t\t\t\t" . '}' . "\n";
                $str .= "\t\t\t\t" . '],' . "\n";
            }
        }
        return $str;
    }

    public function getControllerData($columns, $module)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        $textArea = 0;
        $imageType = 0;
        $time = 0;
        foreach ($columns as $column => $value) {
            if (!array_key_exists('image', $value)) {
                if (!empty($value['type'])) {
                    if ($value['type'] == 'Textarea' && $textArea == 0) {
                        $textArea = 1;
                        $str .= "\t\t\t\t" . ' $this->getAssetManager()->addAsset("modules/pages/js/summernote.min.js");' . "\n";
                        $str .= "\t\t\t\t" . ' $this->getAssetManager()->addAsset("modules/pages/css/summernote.css");' . "\n";
                    } elseif ($value['type'] == 'Time' && $time == 0) {
                        $time = 1;
                        $str .= "\t\t\t\t" . '$this->getAssetManager()->addAsset("modules/theme/backend/js/moment.min.js");' . "\n";
                        $str .= "\t\t\t\t" . '$this->getAssetManager()->addAsset("modules/theme/backend/js/bootstrap-datetimepicker.min.js");' . "\n";
                        $str .= "\t\t\t\t" . '$this->getAssetManager()->addAsset("modules/theme/backend/css/bootstrap-datetimepicker.min.css");' . "\n";
                    }
                }
            } else {
                if ($imageType == 0) {
                    $imageType = 1;
                    $str .= "\t\t\t\t" . '$imageTypes = (!empty(settings("' . strtolower($module) . '", "image_type")))?settings("' . strtolower($module) . '", "image_type"):"jpeg,jpg,png";' . "\n";
                    $str .= "\t\t\t\t" . '$imageTypes = explode(",", $imageTypes);' . "\n";
                    $str .= "\t\t\t\t" . '$imageTypes = "." . implode(",.", $imageTypes);' . "\n";
                }
            }
        }

        return $str;
    }

    public function getControllerVariable($columns, $translation)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        $imageType = 0;
        foreach ($columns as $column => $value) {
            if (array_key_exists('image', $value)  && $imageType == 0) {
                $imageType = 1;
                $str .= '"imageTypes",';
            }
        }
        if ($translation) {
            $str .= '"languageOptions"';
        }
        $str = rtrim($str, ',');
        if(empty($str)) {
            return '';
        }
        return ', compact('.$str. ')';
    }

    public function getControllerStore($columns, $table, $module, $translation)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        foreach ($columns as $column => $value) {
            if (array_key_exists('image', $value)) {
                $str .= "\t\t\t\t" . 'if ($request->file("' . $column . '")) {' . "\n";
                $str .= "\t\t\t\t\t" . ' $imageUploadParams = array(' . "\n";
                $str .= "\t\t\t\t\t\t" . '"module_name" => \Config::get("' . strtolower($module) . '.name") . "/" . \Config::get("' . strtolower($module) . '.' . $table . '_name"),' . "\n";
                $str .= "\t\t\t\t\t\t" . ' "dbfield" => "' . $column . '",' . "\n";
                $str .= "\t\t\t\t\t\t" . ' "thumbnail" => true,' . "\n";
                $str .= "\t\t\t\t\t\t" . ' "thumbnail_size" => 100' . "\n";
                $str .= "\t\t\t\t\t" . ');' . "\n";
                $str .= "\t\t\t\t\t" . ' $formData = $this->' . $table . '->setUploadParams($imageUploadParams)->uploadImage($request);' . "\n";
                if ($translation) {
                    $str .= "\t\t\t\t\t" . '$params["' . $column . '"] = $formData["' . $column . '"];' . "\n";
                } else {
                    $str .= "\t\t\t\t\t" . '$params["' . $table . '"]["' . $column . '"] = $formData["' . $column . '"];' . "\n";
                }
                $str .= "\t\t\t\t" . '}' . "\n";
            } else {
                if (!empty($value['type'])) {
                    if ($translation) {
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
                            $str .= "\t\t\t\t" . '$params["' . $table . '"]["' . $column . '"] = (!empty( $params["' . $table . '"]["' . $column . '"] )) ? "1" : "2";' . "\n";
                        }
                        if ($value['type'] == 'Date') {
                            $str .= "\t\t\t\t" . 'if($params["' . $table . '"]["' . $column . '"]){' . "\n";
                            $str .= "\t\t\t\t\t" . '$params["' . $table . '"]["' . $column . '"] = date_format(date_create_from_format(config("core.encrypt.php_datepicker_format"), $params["' . $table . '"]["' . $column . '"]), "Y-m-d");' . "\n";
                            $str .= "\t\t\t\t" . '}' . "\n";
                        }
                        if ($value['type'] == 'Time') {
                            $str .= "\t\t\t\t" . 'if($params["' . $table . '"]["' . $column . '"]){' . "\n";
                            $str .= "\t\t\t\t\t" . '$params["' . $table . '"]["' . $column . '"] = date("H:i:s", strtotime( $params["' . $table . '"]["' . $column . '"]));' . "\n";
                            $str .= "\t\t\t\t" . '}' . "\n";
                        }
                    }
                }
            }
        }

        return $str;
    }

    public function getControllerEditVariable($columns)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        $imageType = 0;
        foreach ($columns as $column => $value) {
            if (array_key_exists('image', $value)  && $imageType == 0) {
                $imageType = 1;
                $str .= ', "imageTypes"';
            }
        }

        return $str;
    }


    public function getControllerUpdate($columns, $table, $module, $translation)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        foreach ($columns as $column => $value) {
            if (array_key_exists('image', $value)) {
                if (!array_key_exists('required', $value)) {
                    $str .= "\t\t\t\t" . 'if (!empty($params["remove_' . $column . '"])) {' . "\n";
                    $str .= "\t\t\t\t\t" . ' $imageRemoveParams = array(' . "\n";
                    $str .= "\t\t\t\t\t\t" . '"module_name" => \Config::get("' . strtolower($module) . '.name") . "/" . \Config::get("' . strtolower($module) . '.' . $table . '_name"),' . "\n";
                    $str .= "\t\t\t\t\t\t" . ' "dbfield" => "' . $column . '",' . "\n";
                    $str .= "\t\t\t\t\t" . ');' . "\n";
                    $str .= "\t\t\t\t\t" . '$this->' . $table . '->setUploadParams($imageRemoveParams)->setModel($' . $table . ')->removeFile($' . $table . '->' . $column . ',strtolower(\Config::get("' . strtolower($module) . '.name") . "/" . \Config::get("' . strtolower($module) . '.' . $table . '_name")));' . "\n";
                    if ($translation) {
                        $str .= "\t\t\t\t\t" . '$params["' . $column . '"] = null;';
                    } else {
                        $str .= "\t\t\t\t\t" . '$params["' . $table . '"]["' . $column . '"] = null;';
                    }
                    $str .= "\t\t\t\t" . '}' . "\n";
                }
                $str .= "\t\t\t\t" . 'if ($request->file("' . $column . '")) {' . "\n";
                $str .= "\t\t\t\t" . 'if (isset($' . $table . '->' . $column . ')) {' . "\n";
                $str .= "\t\t\t\t\t" . ' $imageRemoveParams = array(' . "\n";
                $str .= "\t\t\t\t\t\t" . '"module_name" => \Config::get("' . strtolower($module) . '.name") . "/" . \Config::get("' . strtolower($module) . '.' . $table . '_name"),' . "\n";
                $str .= "\t\t\t\t\t\t" . ' "dbfield" => "' . $column . '",' . "\n";
                $str .= "\t\t\t\t\t" . ');' . "\n";
                $str .= "\t\t\t\t\t" . '$this->' . $table . '->setUploadParams($imageRemoveParams)->setModel($' . $table . ')->removeFile($' . $table . '->' . $column . ',strtolower(\Config::get("' . strtolower($module) . '.name") . "/" . \Config::get("' . strtolower($module) . '.' . $table . '_name")));' . "\n";
                $str .= "\t\t\t\t\t" . '$params["' . $table . '"]["' . $column . '"] = null;';
                $str .= "\t\t\t\t\t" . '}' . "\n";
                $str .= "\t\t\t\t\t" . ' $imageUploadParams = array(' . "\n";
                $str .= "\t\t\t\t\t\t" . '"module_name" => \Config::get("' . strtolower($module) . '.name") . "/" . \Config::get("' . strtolower($module) . '.' . $table . '_name"),' . "\n";
                $str .= "\t\t\t\t\t\t" . ' "dbfield" => "' . $column . '",' . "\n";
                $str .= "\t\t\t\t\t\t" . ' "thumbnail" => true,' . "\n";
                $str .= "\t\t\t\t\t\t" . ' "thumbnail_size" => 100' . "\n";
                $str .= "\t\t\t\t\t" . ');' . "\n";
                $str .= "\t\t\t\t\t" . ' $formData = $this->' . $table . '->setUploadParams($imageUploadParams)->uploadImage($request);' . "\n";
                if ($translation) {
                    $str .= "\t\t\t\t\t" . '$params["' . $column . '"] = $formData["' . $column . '"];' . "\n";
                } else {
                    $str .= "\t\t\t\t\t" . '$params["' . $table . '"]["' . $column . '"] = $formData["' . $column . '"];' . "\n";
                }
                $str .= "\t\t\t\t" . '}' . "\n";
            } else {
                if (!empty($value['type'])) {
                    if ($translation) {
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
                            $str .= "\t\t\t\t" . '$params["' . $table . '"]["' . $column . '"] = (!empty( $params["' . $table . '"]["' . $column . '"] )) ? "1" : "2";' . "\n";
                        }
                        if ($value['type'] == 'Date') {
                            $str .= "\t\t\t\t" . 'if($params["' . $table . '"]["' . $column . '"]){' . "\n";
                            $str .= "\t\t\t\t\t" . '$params["' . $table . '"]["' . $column . '"] = date_format(date_create_from_format(config("core.encrypt.php_datepicker_format"), $params["' . $table . '"]["' . $column . '"]), "Y-m-d");' . "\n";
                            $str .= "\t\t\t\t" . '}' . "\n";
                        }
                        if ($value['type'] == 'Time') {
                            $str .= "\t\t\t\t" . 'if($params["' . $table . '"]["' . $column . '"]){' . "\n";
                            $str .= "\t\t\t\t\t" . '$params["' . $table . '"]["' . $column . '"] = date("H:i:s", strtotime( $params["' . $table . '"]["' . $column . '"]));' . "\n";
                            $str .= "\t\t\t\t" . '}' . "\n";
                        }
                    }
                }
            }
        }

        return $str;
    }

    public function getCreateFields($columns, $table, $module)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        foreach ($columns as $column => $value) {
            $required = array_key_exists('required', $value) ? 'required' : '';
            if (!array_key_exists('image', $value)) {

                if (!array_key_exists('type', $value)) {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType(" ","'
                        . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Text') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("' . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Number') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType("number","'
                        . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '", "min"=>"0" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Checkbox') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<label for="' . $table . '.' . $column . '">{{trans("' . strtolower($module)  . '::' . $table . '.labels.' . $column . '")}}</label>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="switch">' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<input type="checkbox" value="{{config(' . "'" . 'core.enabled' . "'" . ')}}"  name="' . $table . '[' . $column . ']">' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<span class="slider round"></span>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</label>' . "\n";
                } elseif ($value['type'] == 'Select') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalSelect("' . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, [], null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Multiselect') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalSelect("' . $table . '[' . $column . '][]", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, [], null, ["class" => "form-control ' . $required . '", "multiple" => "true" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textarea') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalTextarea("' . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control formated-textarea ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textdescription') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalTextarea("' . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Date') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("'
                        . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["id"=>"' . $table . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Time') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("'
                        . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["id"=>"' . $table . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Email') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType("email","'
                        . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control valid_email ' . $required . '" ]) !!}' . "\n";
                }
            } else {
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {{ normalLabel(trans("' . strtolower($module) . '::' . $table . '.labels.' . $column . '" , null , []))}}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div class="input-group mb-3">' . "\n\t\t\t\t\t\t\t\t\t\t" . ' <div class="custom-file">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{{ normalFile("' . $column . '","",$errors,["class"=>"custom-file-label ' . $column . ' form-control ' . $required . '  is-invalid","id"=> "' . $table . '_' . $column . '", "accept" => $imageTypes ]) }}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="custom-file-label ' . $column . ' hideoverflow" for="' . $table . '_' . $column . '"  > Choose file </label> </div> <div class="input-group-append"></div></div>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{!! $errors->first("' . $column . '", "<label class=' . "'" . 'error' . "'" . '>:message</label>") !!}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@php' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_extension = (!empty(settings("' . strtolower($module) . '", "image_type")))?settings("' . strtolower($module) . '", "image_type"):"jpeg, jpg, png";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$width = (!empty(settings("' . strtolower($module) . '", "min_upload_width")))?settings("' . strtolower($module) . '", "min_upload_width"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$height = (!empty(settings("' . strtolower($module) . '", "min_upload_height")))?settings("' . strtolower($module) . '", "min_upload_height"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$ratio = (!empty(settings("' . strtolower($module) . '", "image_ratio")))?settings("' . strtolower($module) . '", "image_ratio"):"1";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_max_size = (!empty(settings("' . strtolower($module) . '", "max_upload_size")))?settings("' . strtolower($module) . '", "max_upload_size"):"5";' . "\n";
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

    public function getCreateTranslatableFields($columns, $table, $module)
    {
        $str = '';
        $trans_str = '';
        if (!$columns) {
            return [
                'main_module' => $str,
                'translation_module' => $trans_str
            ];
        }
        foreach ($columns as $column => $value) {
            if (array_key_exists('translation', $value)) {
                $required = array_key_exists('required', $value) ? 'required' : '';
                if (!array_key_exists('image', $value)) {
                    if ($value['type'] == 'Text') {
                        $trans_str .= ' {!! i18nInput("' . $column . '",trans( "'
                            . strtolower($module) . '::' . $table  . '.labels.' . $column
                            . '"), $errors, $lang, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                    } elseif ($value['type'] == 'Textarea') {
                        $trans_str .= ' {!! i18nTextarea("' . $column . '", trans("'
                            . strtolower($module) . '::' . $table  . '.labels.' . $column
                            . '"), $errors, $lang,null, ["class" => "form-control formated-textarea ' . $required . '" ]) !!}' . "\n";
                    } elseif ($value['type'] == 'Textdescription') {
                        $trans_str .= ' {!! i18nTextarea("' . $column . '", trans("'
                            . strtolower($module) . '::' . $table  . '.labels.' . $column
                            . '"), $errors, $lang,null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                    } else {
                        $trans_str .= ' {!! i18nInputOfType(" ","' . $column . '",trans("'
                            . strtolower($module) . '::' . $table  . '.labels.' . $column
                            . '"), $errors, $lang, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                    }
                }
                continue;
            }

            $required = array_key_exists('required', $value) ? 'required' : '';
            if (!array_key_exists('image', $value)) {

                if (!array_key_exists('type', $value)) {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType(" ","' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Text') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Number') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType("number","' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '", "min"=>"0" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Checkbox') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<label for="' . $table . '.' . $column . '">{{trans("' . strtolower($module) . '::' . $table . '.labels.' . $column . '")}}</label>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="switch">' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<input type="checkbox" value="{{config(' . "'" . 'core.enabled' . "'" . ')}}"  name="' . $column . '">' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<span class="slider round"></span>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</label>' . "\n";
                } elseif ($value['type'] == 'Select') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalSelect("' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, [], null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Multiselect') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalSelect("' . $column . '[]", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, [], null, ["class" => "form-control ' . $required . '", "multiple" => "true" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textarea') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalTextarea("' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control formated-textarea ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textdescription') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalTextarea("' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Date') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["id"=>"' . $table . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Time') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalText("' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["id"=>"' . $table . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Email') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!! normalInputOfType("email","' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, null, ["class" => "form-control valid_email ' . $required . '" ]) !!}' . "\n";
                }
            } else {
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {{ normalLabel(trans("' . strtolower($module) . '::' . $table . '.labels.' . $column . '"),"",[])}}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div class="input-group mb-3">' . "\n\t\t\t\t\t\t\t\t\t\t" . ' <div class="custom-file">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{{ normalFile("' . $column . '","",$errors,["class"=>"custom-file-label ' . $column . ' form-control ' . $required . '  is-invalid","id"=> "' . $table . '_' . $column . '", "accept" => $imageTypes ]) }}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="custom-file-label ' . $column . ' hideoverflow" for="' . $table . '_' . $column . '"  > Choose file </label> </div> <div class="input-group-append"></div></div>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{!! $errors->first("' . $column . '", "<label class=' . "'" . 'error' . "'" . '>:message</label>") !!}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@php' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_extension = (!empty(settings("' . strtolower($module) . '", "image_type")))?settings("' . strtolower($module) . '", "image_type"):"jpeg, jpg, png";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$width = (!empty(settings("' . strtolower($module) . '", "min_upload_width")))?settings("' . strtolower($module) . '", "min_upload_width"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$height = (!empty(settings("' . strtolower($module) . '", "min_upload_height")))?settings("' . strtolower($module) . '", "min_upload_height"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$ratio = (!empty(settings("' . strtolower($module) . '", "image_ratio")))?settings("' . strtolower($module) . '", "image_ratio"):"1";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_max_size = (!empty(settings("' . strtolower($module) . '", "max_upload_size")))?settings("' . strtolower($module) . '", "max_upload_size"):"5";' . "\n";
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

    public function getScripts($columns, $table, $module)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        $imageScripts = 0;
        $textArea = 0;
        $email = 0;
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
                        $str .= "\t\t\t\t" . 'jQuery("#' . $table . '_' . $column . '").datepicker({' . "\n";
                        $str .= "\t\t\t\t\t" . 'dateFormat: "{{config(' . "'" . 'core.encrypt.datepicker_format' . "'" . ')}}"' . "\n";
                        $str .= "\t\t\t\t" . "});" . "\n\n";
                    } elseif ($value['type'] == 'Time') {
                        $str .= "\t\t\t\t" . 'jQuery("#' . $table . '_' . $column . '").datetimepicker({' . "\n";
                        $str .= "\t\t\t\t\t" . 'format: "LT" ,' . "\n";
                        $str .= "\t\t\t\t\t" . ' icons: {' . "\n";
                        $str .= "\t\t\t\t\t\t" . 'up: "fa fa-chevron-up",' . "\n";
                        $str .= "\t\t\t\t\t\t" . 'down: "fa fa-chevron-down",' . "\n";
                        $str .= "\t\t\t\t\t" . '}' . "\n";
                        $str .= "\t\t\t\t" . "});" . "\n\n";
                    }
                }
            } else {
                $str .= "\t\t\t\t" . 'jQuery("#' . $table . '_' . $column . '").change(function(e) {' . "\n";
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
                    $str .= "\t\t\t\t\t" . 'var Image_extention_db = ' . "'" . '{{ (!empty(settings("' . strtolower($module) . '", "image_type")))?settings("' . strtolower($module) . '", "image_type"):"jpeg,jpg,png" }}' . "'" . '.toLowerCase().split(",");' . "\n";
                    $str .= "\t\t\t\t\t" . ' return ($.inArray(ext, Image_extention_db) == -1 && ext != "") ? false : true;' . "\n";
                    $str .= "\t\t\t\t" . '}, ' . "'" . '{{ trans("core::core.messages.invalid_image") }}' . "'" . ');' . "\n\n";
                    $str .= "\t\t\t\t" . 'jQuery.validator.addMethod("validDimension", function(value, element) {' . "\n";
                    $str .= "\t\t\t\t\t" . 'var img = new Image();' . "\n";
                    $str .= "\t\t\t\t\t" . 'if ($(element)[0].files[0]) {' . "\n";
                    $str .= "\t\t\t\t\t" . 'img.src = window.URL.createObjectURL($(element)[0].files[0]);' . "\n";
                    $str .= "\t\t\t\t\t" . 'img.onload = function() {' . "\n";
                    $str .= "\t\t\t\t\t" . 'width = parseInt(img.naturalWidth);' . "\n";
                    $str .= "\t\t\t\t\t" . 'height = parseInt(img.naturalHeight);' . "\n";
                    $str .= "\t\t\t\t\t" . 'minWidth = parseInt(' . "'" . '{{ (!empty(settings("' . strtolower($module) . '", "min_upload_width")))?settings("' . strtolower($module) . '", "min_upload_width"):"100" }}' . "'" . ');' . "\n";
                    $str .= "\t\t\t\t\t" . 'minHeight = parseInt(' . "'" . '{{ (!empty(settings("' . strtolower($module) . '", "min_upload_height")))?settings("' . strtolower($module) . '", "min_upload_height"):"100" }}' . "'" . ');' . "\n";
                    $str .= "\t\t\t\t\t" . 'window.URL.revokeObjectURL(img.src);' . "\n";
                    $str .= "\t\t\t\t\t" . ' if ((width >= minWidth) && (height >= minHeight)) {' . "\n";
                    $str .= "\t\t\t\t\t" . 'if ((width / height).toFixed(2) != parseInt(' . "'" . '{{ (!empty(settings("' . strtolower($module) . '", "image_ratio")))?settings("' . strtolower($module) . '", "image_ratio"):"1" }}' . "'" . ') {' . "\n";
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
                    $str .= "\t\t\t\t\t" . ' var maxImageSize = ' . "'" . '{{ (!empty(settings("' . strtolower($module) . '", "max_upload_size")))?settings("' . strtolower($module) . '", "max_upload_size"):"5" }}' . "'" . ';' . "\n";
                    $str .= "\t\t\t\t\t" . 'if (maxImageSize != "" && typeof(maxImageSize) != "undefined") {' . "\n";
                    $str .= "\t\t\t\t\t" . ' var maxFileSize = (1024 * maxImageSize);' . "\n";
                    $str .= "\t\t\t\t\t" . 'if (file_size > maxFileSize) {' . "\n";
                    $str .= "\t\t\t\t\t" . 'msg = ' . "'" . '{{ trans("core::core.validation-message.image.max-size",["size"=>( (!empty(settings("' . strtolower($module) . '", "max_upload_size")))?settings("' . strtolower($module) . '", "max_upload_size"):"5" )] ) }}' . "'" . ';' . "\n";
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

    public function getEditFields($columns, $table, $module)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        foreach ($columns as $column => $value) {
            $required = array_key_exists('required', $value) ? 'required' : '';
            if (!array_key_exists('image', $value)) {

                if (!array_key_exists('type', $value)) {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalInputOfType(" ","'
                        . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, $' . $table . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Text') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalText("' . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, $' . $table . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Number') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalInputOfType("number","'
                        . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, $' . $table . '->' . $column . ', ["class" => "form-control ' . $required . '", "min"=>"0" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Checkbox') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<label for="' . $table . '.' . $column . '">{{trans("' . $table . '::' . $table . '.labels.' . $column . '")}}</label>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="switch">' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<input type="checkbox" value="{{config(' . "'" . 'core.enabled' . "'" . ')}}"  name="' . $table . '[' . $column . ']"  {{ ($' . $table . '->' . $column . ' == config("core.enabled")) ? "checked" : ""}}>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<span class="slider round"></span>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</label>' . "\n";
                } elseif ($value['type'] == 'Select') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalSelect("' . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, [], $' . $table . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Multiselect') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalSelect("' . $table . '[' . $column . '][]", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, [], $' . $table . '->' . $column . ', ["class" => "form-control ' . $required . '", "multiple" => "true" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textarea') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalTextarea("' . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, $' . $table . '->' . $column . ', ["class" => "form-control formated-textarea ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textdescription') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalTextarea("' . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors,  $' . $table . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Date') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalText("'
                        . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors,date(config("core.encrypt.php_datepicker_format"), strtotime( $' . $table . '->' . $column . ')), ["id"=>"' . $table . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Time') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalText("'
                        . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, $' . $table . '->' . $column . ', ["id"=>"' . $table . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Email') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalInputOfType("email","'
                        . $table . '[' . $column . ']", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, $' . $table . '->' . $column . ', ["class" => "form-control valid_email ' . $required . '" ]) !!}' . "\n";
                }
            } else {
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {{ normalLabel(trans("' . strtolower($module) . '::' . $table . '.labels.' . $column . '") , "" , [])}}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div class="input-group mb-3">' . "\n\t\t\t\t\t\t\t\t\t\t" . ' <div class="custom-file">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{{ normalFile("' . $column . '","",$errors,["class"=>"custom-file-label ' . $column . ' form-control  is-invalid","id"=> "' . $table . '_' . $column . '", "accept" => $imageTypes ]) }}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="custom-file-label ' . $column . ' hideoverflow" for="' . $table . '_' . $column . '"  > Choose file </label> </div> <div class="input-group-append"></div></div>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{!! $errors->first("' . $column . '", "<label class=' . "'" . 'error' . "'" . '>:message</label>") !!}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@php' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_extension = (!empty(settings("' . strtolower($module) . '", "image_type")))?settings("' . strtolower($module) . '", "image_type"):"jpeg, jpg, png";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$width = (!empty(settings("' . strtolower($module) . '", "min_upload_width")))?settings("' . strtolower($module) . '", "min_upload_width"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$height = (!empty(settings("' . strtolower($module) . '", "min_upload_height")))?settings("' . strtolower($module) . '", "min_upload_height"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$ratio = (!empty(settings("' . strtolower($module) . '", "image_ratio")))?settings("' . strtolower($module) . '", "image_ratio"):"1";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_max_size = (!empty(settings("' . strtolower($module) . '", "max_upload_size")))?settings("' . strtolower($module) . '", "max_upload_size"):"5";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$og_image_param = [' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"module" => Config::get("' . strtolower($module) . '.name")."/".Config::get("' . strtolower($module) . '.' . $table . '_name"),' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image" => $' . $table . '->' . $column . ',' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '];' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$resize_image_param = [' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image-type" => "resize",' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image-size" => 100,' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"module" => Config::get("' . strtolower($module) . '.name")."/".Config::get("' . strtolower($module) . '.' . $table . '_name"),' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image" => $' . $table . '->' . $column . ',' . "\n";
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

        return $str;
    }
    public function getEditTranslatableFields($columns, $table, $module)
    {
        $str = '';
        $trans_str = '';
        if (!$columns) {
            return [
                'main_module' => $str,
                'translation_module' => $trans_str

            ];
        }
        foreach ($columns as $column => $value) {
            if (array_key_exists('translation', $value)) {
                $required = array_key_exists('required', $value) ? 'required' : '';
                if (!array_key_exists('image', $value)) {
                    if ($value['type'] == 'Text') {
                        $trans_str .= ' {!!  i18nInput("' . $column . '", trans("'
                            . strtolower($module) . $table . '.labels.' . $column
                            . '"), $errors, $lang, $' . $table . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                    } elseif ($value['type'] == 'Textarea') {
                        $trans_str .= ' {!!  i18nTextarea("' . $column . '", trans("'
                            . strtolower($module) . $table . '.labels.' . $column
                            . '"), $errors, $lang, $' . $table . ', ["class" => "form-control formated-textarea ' . $required . '" ]) !!}' . "\n";
                    } elseif ($value['type'] == 'Textdescription') {
                        $trans_str .= ' {!!  i18nTextarea("' . $column . '", trans("'
                            . strtolower($module) . $table . '.labels.' . $column
                            . '"), $errors, $lang, $' . $table . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                    } else {
                        $trans_str .= ' {!!  i18nInputOfType(" ","' . $column . '", trans("'
                            . strtolower($module) . $table . '.labels.' . $column
                            . '"), $errors, $lang, $' . $table . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                    }
                }
                continue;
            }
            $required = array_key_exists('required', $value) ? 'required' : '';
            if (!array_key_exists('image', $value)) {

                if (!array_key_exists('type', $value)) {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalInputOfType(" ","' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, $' . $table . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Text') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalText("' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, $' . $table . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Number') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalInputOfType("number","' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, $' . $table . '->' . $column . ', ["class" => "form-control ' . $required . '", "min"=>"0" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Checkbox') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<label for="' . $table . '.' . $column . '">{{trans("' .  strtolower($module) . '::' . $table . '.labels.' . $column . '")}}</label>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</div>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="switch">' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<input type="checkbox" value="{{config(' . "'" . 'core.enabled' . "'" . ')}}"  name="' . $column . '"  {{ ($' . $table . '->' . $column . ' == config("core.enabled")) ? "checked" : ""}}>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t\t" . '<span class="slider round"></span>' . "\n";
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . '</label>' . "\n";
                } elseif ($value['type'] == 'Select') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalSelect("' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, [], $' . $table . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Multiselect') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalSelect("' . $column . '[]", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, [], $' . $table . '->' . $column . ', ["class" => "form-control ' . $required . '", "multiple" => "true" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textarea') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalTextarea("' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, $' . $table . '->' . $column . ', ["class" => "form-control formated-textarea ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Textdescription') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalTextarea("' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors,  $' . $table . '->' . $column . ', ["class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Date') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalText("' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors,date(config("core.encrypt.php_datepicker_format"), strtotime( $' . $table . '->' . $column . ')), ["id"=>"' . $table . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Time') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalText("' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, $' . $table . '->' . $column . ', ["id"=>"' . $table . '_' . $column . '","class" => "form-control ' . $required . '" ]) !!}' . "\n";
                } elseif ($value['type'] == 'Email') {
                    $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {!!  normalInputOfType("email","' . $column . '", "'
                        . strtolower($module) . '::' . $table . '.labels.' . $column
                        . '", $errors, $' . $table . '->' . $column . ', ["class" => "form-control valid_email ' . $required . '" ]) !!}' . "\n";
                }
            } else {
                $str .= "\t\t\t\t\t\t\t\t\t\t" . ' {{ normalLabel(trans("' . strtolower($module) . '::' . $table . '.labels.' . $column . '"),"",[])}}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<div class="input-group mb-3">' . "\n\t\t\t\t\t\t\t\t\t\t" . ' <div class="custom-file">' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{{ normalFile("' . $column . '","",$errors,["class"=>"custom-file-label ' . $column . ' form-control  is-invalid","id"=> "' . $table . '_' . $column . '", "accept" => $imageTypes ]) }}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '<label class="custom-file-label ' . $column . ' hideoverflow" for="' . $table . '_' . $column . '"  > Choose file </label> </div> <div class="input-group-append"></div></div>' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '{!! $errors->first("' . $column . '", "<label class=' . "'" . 'error' . "'" . '>:message</label>") !!}' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '@php' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_extension = (!empty(settings("' . strtolower($module) . '", "image_type")))?settings("' . strtolower($module) . '", "image_type"):"jpeg, jpg, png";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$width = (!empty(settings("' . strtolower($module) . '", "min_upload_width")))?settings("' . strtolower($module) . '", "min_upload_width"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$height = (!empty(settings("' . strtolower($module) . '", "min_upload_height")))?settings("' . strtolower($module) . '", "min_upload_height"):"100";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$ratio = (!empty(settings("' . strtolower($module) . '", "image_ratio")))?settings("' . strtolower($module) . '", "image_ratio"):"1";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$image_max_size = (!empty(settings("' . strtolower($module) . '", "max_upload_size")))?settings("' . strtolower($module) . '", "max_upload_size"):"5";' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$og_image_param = [' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"module" => Config::get("' . strtolower($module) . '.name")."/".Config::get("' . strtolower($module) . '.' . $table . '_name"),' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image" => $' . $table . '->' . $column . ',' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '];' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '$resize_image_param = [' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image-type" => "resize",' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image-size" => 100,' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"module" => Config::get("' . strtolower($module) . '.name")."/".Config::get("' . strtolower($module) . '.' . $table . '_name"),' . "\n";
                $str .= "\t\t\t\t\t\t\t\t\t\t" . '"image" => $' . $table . '->' . $column . ',' . "\n";
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

    public function getColumnCount($columns)
    {
        if (!$columns) {
            return '7';
        }
        $count = 0;
        foreach ($columns as $column => $value) {
            if (array_key_exists('grid', $value)) {
                $count++;
            }
        }
        $count = $count + 4;

        return $count;
    }

    public function getData($columns, $table, $module)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        foreach ($columns as $column => $value) {
            if (array_key_exists('grid', $value)) {
                if (!array_key_exists('image', $value)) {
                    if ($value['type'] == 'Date') {
                        $str .= "\t\t\t\t\t\t\t" . '<td> {{  getFormatedDate($' . $table . '->' . $column . ', "j M Y") }} </td>' . "\n";
                    } elseif ($value['type'] == 'Time') {
                        $str .= "\t\t\t\t\t\t\t" . '<td> {{  date("g:i  A", strtotime($' . $table . '->' . $column . ')) }} </td>' . "\n";
                    } else {
                        $str .= "\t\t\t\t\t\t\t" . '<td> {{ wordWrapper($' . $table . '->' . $column . ') }} </td>' . "\n";
                    }
                } else {
                    $str .=  "\t\t\t\t\t\t\t" . '<td>' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '@php' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '$og_image_param = [' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '"module" => Config::get("' . strtolower($module) . '.name")."/".Config::get("' . strtolower($module) . '.' . $table . '_name"),' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '"image" => $' . $table . '->' . $column . ',' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '];' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '$resize_image_param = [' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '"image-type" => "resize",' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '"image-size" => 100,' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '"module" => Config::get("' . strtolower($module) . '.name")."/".Config::get("' . strtolower($module) . '.' . $table . '_name"),' . "\n";
                    $str .=  "\t\t\t\t\t\t\t" . '"image" => $' . $table . '->' . $column . ',' . "\n";
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

    public function getControllerIndex($columns)
    {
        if ($columns) {
            return '';
        }
        $str = '';
        $time = 0;
        if(isset($columns) && !empty($columns)) {
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
        }

        return $str;
    }

    protected function getTableColumns($columns)
    {
        $str = '';
        $trans_str = '';
        if (!$columns) {
            return [
                'main_module' => $str,
                'translation_module' => $trans_str
            ];
        }
        foreach ($columns as $column => $value) {
            if (array_key_exists('translation', $value)) {
                $trans_str .= "'" . $column . "',";
                continue;
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

    public function getCreateTranslatableRules($columns, $lower_name)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        $trans_str = "\t\t\t" . 'foreach (getLanguageOptions() as $locale => $value) {' . "\n";
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
                $str .= "\t\t\t\t\t" . $required . '"mimes:" . $this->getImageType() , "max:" . $this->getMaxUpload(), "dimensions:min_width=" . (!empty(settings("' . $lower_name . '", "min_upload_width")))?settings("' . $lower_name . '", "min_upload_width"):"100" , ",min_height=" . (!empty(settings("' . $lower_name . '", "min_upload_height")))?settings("' . $lower_name . '", "min_upload_height"):"100",' . "\n";
                $str .= "\t\t\t\t\t" . 'function($attribute, $value, $fail) {' . "\n";
                $str .= "\t\t\t\t\t\t" . ' $temp  = (!empty(settings("' . $lower_name . '", "image_ratio")))?settings("' . $lower_name . '", "image_ratio"):"1";' . "\n";
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

    public function getRequestTranslatableMessages($columns, $lower_name)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        foreach ($columns as $column => $value) {
            if (array_key_exists('image', $value)) {
                $str .= "\t\t\t" . '$rules["' . $column . '.' . 'mimes"] = trans("core::core.validation-message.image.file-type", ["file_type" => $this->getImageType()]); ' . "\n";
                $str .= "\t\t\t" . '$rules["' . $column . '.' . 'max"] = trans("core::core.validation-message.image.max-size", ["size" => ($this->getMaxUpload() / 1024)]);' . "\n";
                $str .= "\t\t\t" . '$rules["' . $column . '.' . 'dimensions"] = trans("core::core.messages.invalid_dimension");' . "\n";
            } else {
                if (array_key_exists('unique', $value)) {
                    $str .= "\t\t\t" . '$rules["' . $column . '.unique"] = trans("' .  $lower_name  . '::' .  $lower_name  . '.messages.' . $column . '_unique");' . "\n";
                }
            }
        }

        return $str;
    }

    public function getUpdateTranslatableRules($columns, $lower_name)
    {
        if (!$columns) {
            return '';
        }
        $str = '';
        $trans_str = "\t\t\t" . 'foreach (getLanguageOptions() as $locale => $value) {' . "\n";
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
                $str .= "\t\t\t\t\t" . $required . '"mimes:" . $this->getImageType() , "max:" . $this->getMaxUpload(), "dimensions:min_width=" . (!empty(settings("' . $lower_name . '", "min_upload_width")))?settings("' . $lower_name . '", "min_upload_width"):"100" , ",min_height=" . (!empty(settings("' . $lower_name . '", "min_upload_height")))?settings("' . $lower_name . '", "min_upload_height"):"100",' . "\n";
                $str .= "\t\t\t\t\t" . 'function($attribute, $value, $fail) {' . "\n";
                $str .= "\t\t\t\t\t\t" . ' $temp  = (!empty(settings("' . $lower_name . '", "image_ratio")))?settings("' . $lower_name . '", "image_ratio"):"1";' . "\n";
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
}
