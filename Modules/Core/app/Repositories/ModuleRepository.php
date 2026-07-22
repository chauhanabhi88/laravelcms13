<?php

namespace Modules\Core\Repositories;

use Illuminate\Cache\Repository;
use Nwidart\Modules\Facades\Module;

class ModuleRepository
{
    public function getModules()
    {
        
        // if (!getModule("core", "cache")) {
        //     $collection = Module::getCached();
        // } else {
            $collection = Module::toCollection()->toArray();
        //}
        return $collection;
    }

    public function getModulesName()
    {
        $collection = $this->getModules();
        $modules = [];
        $modules[''] = '-- Select Module --';
        foreach ($collection as $name => $value) {
            $modules[$name] = $name;
        }
        ksort($modules);
        return $modules;
    }

    public function update($params)
    {
        $moduleName = (isset($params['module']) && $params['module']) ? $params['module'] : "";
        $cache = (isset($params['cache']) && $params['cache'] == "true") ? true : false;

        $module = Module::findOrFail($moduleName);
        if (isset($params['cache'])) {
            $module->json()
                ->set('cache', $cache)
                ->save();
        }

        if (isset($params['clear_cache'])) {
            $repository = app(Repository::class);
            $config =  strtolower($moduleName) . '.' . 'cache';
            $keys = config($config);
            if (!empty($keys)) {
                foreach ($keys as $key) {
                    $cacheKey = "modules -entity:" . $key . ":";
                    $repository->modulePath($cacheKey)->flushModuleCache();
                }
            } else {
                $cacheKey = $this->getBaseKey($module->getLowerName());
                $repository->modulePath($cacheKey)->flushModuleCache();
            }
        } else if (!getModule("core", "cache")) {
            $repository = app(Repository::class);
            $cacheKey = $this->getBaseKey("core");
            $repository->modulePath($cacheKey)->flushModuleCache();
        }
    }

    /**
     * @return string
     */
    protected function getBaseKey($moduleName): string
    {
        return sprintf(
            'modules -entity:%s:',
            \Config::get($moduleName . ".name")
        );
    }

    public function getTables()
    {
        $data = [];
        $database =   \DB::connection()->getDatabaseName();
        $name = 'Tables_in_' . $database;
        $tables = \DB::select('SHOW TABLES');
        foreach ($tables as $table) {
            $data[$table->$name] = $table->$name;
        }
        $data[''] = '--  Table Name  --';
        ksort($data);
        return $data;
    }

    public function getForeignKeyDeleteOptions()
    {
        $data = [
            '' => '--  On Delete  --',
            'cascade' => 'Cascade',
            ' ' => 'No Action',
            'restrict' => 'Restrict'
        ];
        ksort($data);
        return $data;
    }

    public function getForeignKeyUpdateOptions()
    {
        $data = [
            '' => '--  On Update  --',
            'cascade' => 'Cascade',
            ' ' => 'No Action',
            'restrict' => 'Restrict'
        ];
        ksort($data);
        return $data;
    }

    public function getColumnList($table)
    {
        $data = array();
        $data[''] = '-- After Column --';
        $columns  = \DB::getSchemaBuilder()->getColumnListing($table);
        foreach ($columns as $value => $key) {
            $data[$key] = $key;
        }
        // ksort($data);
        return $data;
    }

    public function getEntities($name, $flag = true)
    {
        $dir = __DIR__ . '/../../../' .  $name . '/app/Models';
        $data = [];
        if ($flag) {
            $data[''] = "-- Table Name --";
        }
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), array('.', '..'));
            if (!$files) {
                return [];
            }
            foreach ($files as $key => $file) {
                if (substr($file, strpos($file, ".") + 1) == 'php') {
                    $content = file_get_contents($dir . '/' . $file);
                    $content = str_replace([' ', "'"], ['', '"'], $content);
                    $data[basename($file, '.php')] = $this->getStringBetween($content, 'protected$table="', '";');
                }
            }
        }
        return $data;
    }

    public function getStringBetween($str, $from, $to)
    {
        $sub = substr($str, strpos($str, $from) + strlen($from), strlen($str));
        return substr($sub, 0, strpos($sub, $to));
    }

    public function createSeeder($data)
    {
        $module = $data['module'];
        $fileNames = $data['table'];
        
        // echo "<pre>"; print_r($fileNames); die;
        $entities = $this->getEntities($module);
        $alphabet = "A";
        $dir = __DIR__ . '/../../../' .  $module . '/app/Models';
        if (is_dir($dir)) {
            if ($fileNames) {
                foreach ($fileNames as $key => $file) {
                    $path = __DIR__ . '/../../../' .  $module . '/database/seeders/' . $alphabet . $file . 'Seeder.php';
                    if (is_file($path)) {
                        @chmod($path, '777');
                        @unlink($path);
                    }
                    \Artisan::call('module:make-seed', [
                        'name' => $alphabet . $file,
                        'module' => $module,
                    ]);
                    
                    $this->createSeederContent($path, $file, $module, $entities[$file]);
                    $alphabet++;
                }
            }
        }
    }

    public function createSeederContent($filepath, $fileName, $module, $tableName)
    {
        $columns = $this->getColumnList($tableName);
        unset($columns['']);
        $rows =  \DB::table($tableName)->get()->toArray();
        $filecontent = file($filepath);
        foreach ($filecontent as $key => $value) {
            $value = str_replace(' ', '', $value);
            if (strpos($value, 'useIlluminate\Database\Seeder;') !== false) {
                $filecontent[$key + 1] = 'use Modules\\' . $module . '\Models\\' . $fileName . ';' . "\n";
            }
            if (strpos($value, 'publicfunctionrun()') !== false) {
                if ($rows) {
                    $filecontent[$key + 1] = "\t{\n\t\t" . $fileName . '::insert([' . "\n";
                    foreach ($rows as $row) {
                        $filecontent[$key + 1] .= "\t\t\t[\n";
                        foreach ($columns as $column) {
                            if(!$this->isJSON($row->$column)) {
                                // $resultData = ($row->$column) ? "'" . str_replace("'", '"', $row->$column) . "'," : 'null,';
                                $resultData = ($row->$column) ? "'" . addcslashes($row->$column,"\'\"") . "'," : 'null,';


                            } else {
                                $resultData = ($row->$column) ? "'" . $row->$column . "'," : 'null,';
                            }

                            $filecontent[$key + 1] .= "\t\t\t\t" . '"' . $column . '" => ' . $resultData . "\n";
                        }
                        $filecontent[$key + 1] .= "\t\t\t],\n";
                    }
                    $filecontent[$key + 1] .= "\t\t]);\n";
                }
            }
        }
        file_put_contents($filepath, $filecontent);
    }

    public function isJSON($string)
    {
        return is_string($string) && is_array(json_decode($string, true)) ? true : false;
    }

    public function getEnabledModules()
    {
        $data = [];
        $collection =  Module::collections()->toArray();
        foreach ($collection as $key => $value) {
            $data[$key] = $key;
        }
        return $data;
    }

    public function getHint($name)
    {
        $dir = __DIR__ . '/../../' .  $name . '/Models';
        $data = [];
        $string = '';
        $data = $this->getHintData($name, $dir);
        $dir = __DIR__ . '/../../' .  $name . '/Repositories';
        $data = array_merge($data, $this->getHintData($name, $dir));
        $dir = __DIR__ . '/../../' .  $name . '/Repositories/Eloquent';
        $data = array_merge($data, $this->getHintData($name, $dir));
        $dir = __DIR__ . '/../../' .  $name . '/Http/Controllers/Backend';
        $data = array_merge($data, $this->getHintData($name, $dir));
        $string .= '<div class="image-note">';
        $string .= '<lh><b>Other modules used in ' . $name . '</b></lh>' . "\n";
        foreach ($data as $key => $value) {
            $string .= "\t\n<li>" . $value . "</li>";
        }
        $string .= '</div>';
        return $string;
    }

    public function getHintData($name, $dir)
    {
        $data = [];
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), array('.', '..'));
            if ($files) {
                foreach ($files as $key => $file) {
                    if (substr($file, strpos($file, ".") + 1) == 'php') {
                        $rows = file($dir . '/' . $file);
                        foreach ($rows as $key => $row) {
                            if (strpos($row, 'class') !== false) {
                                break;
                            }
                            if (strpos($row, ';') === false || strpos($row, 'namespace') !== false || strpos($row, 'Modules') === false || strpos($row, 'Nwidart') !== false) {
                                continue;
                            }
                            $string = $this->getStringBetween($row, "Modules\\", "\\");
                            if ($string != $name) {
                                $data[$string] = $string;
                            }
                        }
                    }
                }
            }
        }
        return $data;
    }

    public function getJsonModules($module)
    {
        $modules = $this->getModulesName();
        unset($modules['']);
        unset($modules[$module]);
        $file = __DIR__ . '/../../' .  $module . '/module.json';
        if (!is_file($file)) {
            return $modules;
        }
        $module = Module::findOrFail($module);
        $jsonModules =  $module->json()->get('depends');
        if (!is_array($jsonModules)) {
            return $modules;
        }
        foreach ($modules as $key => $value) {
            if (in_array(strtolower($value), $jsonModules)) {
                unset($modules[$key]);
            }
        }
        return $modules;
    }

    public function addDependency($module, $data)
    {
        $file = __DIR__ . '/../../' .  $module . '/module.json';
        if (!is_file($file)) {
            return false;
        }
        $data = implode(',', $data);
        $data = strtolower($data);
        $data = explode(',', $data);
        $module = Module::findOrFail($module);
        $org_data =  $module->json()
            ->get('depends');
        if (!is_array($org_data)) {
            $org_data = [];
        }
        $data = array_merge($data, $org_data);
        $module->json()
            ->set('depends', $data)
            ->save();
    }

    public function getModuleTypes($flag = false)
    {
        $modules = [
            1 => trans("core::core.labels.module_without_translation"),
            2 => trans("core::core.labels.module_with_translation")
        ];
        if ($flag) {
            $modules[''] = '-- Select Module --';
        }
        ksort($modules);
        return $modules;
    }

    public function getYesNoOptions($flag = false)
    {
        $options = [];
        if ($flag) {
            $options[''] = ' -- ' . trans('core::core.labels.select') . ' -- ';
        }
        return $options + [
            config("core.yes") => trans('core::core.options.yesno.yes'),
            config("core.no") => trans('core::core.options.yesno.no')
        ];
    }
}
