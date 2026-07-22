<?php

namespace Modules\Language\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Language\Models\Language;
use Modules\Language\Repositories\TranslationRepository;
use Modules\Core\Http\Controllers\BackendController;
use Illuminate\Filesystem\Filesystem;
use App;

class TranslationController extends BackendController
{
    /**
     * @var LanguageRepository
     */
    private $translation;
    protected $module;
    private $data = [];
    private $inc = 0;
    private $key;
    private $count = 0;
    private $uniqueKey = [];
    private $finder;

    /**
     * @var UserEntity
     */
    public function __construct(TranslationRepository $translation, Filesystem $finder)
    {
        parent::__construct();
        $this->translation = $translation;
        $this->finder = $finder;
    }
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            $this->getAssetManager()->addAsset('modules/theme/backend/css/dataTables.min.css');
            $this->getAssetManager()->addAsset('modules/theme/backend/js/dataTables.min.js');
            $this->getAssetManager()->addAsset('modules/theme/backend/js/dataTables.bootstrap4.min.js');
            $this->getAssetManager()->addAsset('modules/theme/backend/css/bootstrap-editable.css');
            $this->getAssetManager()->addAsset('modules/theme/backend/js/bootstrap-editable.min.js');
            $languages = getLanguageOptions();
            $data = $this->translation->getModulesName();
            $moduleName = $request->moduleName;
            if (!empty($data) && !empty($languages) && !empty($moduleName)) {
                $i = 0;
                foreach ($languages as $langKey => $langValue) {
                    $dir = base_path('Modules/' . ucfirst($moduleName) . '/resources/lang/' . $langKey);
                    if (is_dir($dir)) {
                        $files = array_diff(scandir($dir), array('..', '.'));
                        foreach ($files as $key => $fileValue) {
                            $file = $dir.'/'. $fileValue;
                            $return = require $file;
                            $this->getExportData($langKey, $moduleName, $fileValue, $return);
                        }
                    }
                }
            }
            $result = [];
            $result = $this->data;
            return view('language::backend.translation.index', compact('data','result', 'languages','moduleName'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    public function getExportData($langOption, $module, $file, $values, $count = null, $key = null)
    {
        try {
            foreach ($values as $valuesData => $value) {
                if (is_array($value)) {
                    $this->data[$this->inc]['module'] = $module;
                    $this->data[$this->inc]['file'] = $file;
                    $this->data[$this->inc]['key'] =  $valuesData;
                    $this->key = $this->data[$this->inc]['key'];
                    if ($count > 0) {
                        $this->key = $key . '.' . $this->key;
                    }
                    $this->count++;
                    unset( $this->data[$this->inc]);
                    $this->getExportData($langOption, $module, $file, $value, $this->count, $this->key);
                } else {
                    if (!in_array(basename($file, ".php") . ':' . $this->key . '.' . $valuesData, $this->uniqueKey)) {
                        $this->data[$this->inc]['module'] = $module;
                        $this->data[$this->inc]['file'] = $file;
                        $this->data[$this->inc]['key'] = $this->key . '.' . $valuesData;
                        $this->uniqueKey[$this->inc] =   basename($file, ".php") . ':' . $this->key . '.' . $valuesData;
                        $this->data[$this->inc][$langOption] = $value;
                        $this->data[$this->inc]['display'] = strtolower($module) . '::' . basename($file, ".php") . '.' . $this->key . '.' . $valuesData;
                        $this->inc++;
                        $this->count = 0;
                    } else {
                        $temp = array_search(basename($file, ".php") . ':' . $this->key . '.' . $valuesData,  $this->uniqueKey);
                        $this->data[$temp][$langOption] = $value;
                    }
                }
            }
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }
    public function update(Request $request)
    {
        try {
            $locale = $request->get('locale');
            $key = $request->get('key');
            $value = $request->get('value');
            $group =  explode('.', $key);
            $namespace = explode('::', $group[0]);
            $fileName = $namespace[1];
            $moduleName = ucfirst($namespace[0]);
            unset($group[0]);
            $checkKey = implode('.', $group);
            $i = 1;
            $groupCount = count($group);
            $basePath = base_path();
            if (!is_dir("{$basePath}/Modules/{$moduleName}/resources/lang/{$locale}")) {
                mkdir("{$basePath}/Modules/{$moduleName}/resources/lang/{$locale}", 0777, true);
            }
            if (!file_exists("{$basePath}/Modules/{$moduleName}/resources/lang/{$locale}/{$fileName}.php")) {
                $temp  = "<?php" . "\r\n return [ \n ]; ";
                file_put_contents("{$basePath}/Modules/{$moduleName}/resources/lang/{$locale}/{$fileName}.php", $temp);
            }
            $trans = $this->finder->getRequire("{$basePath}/Modules/{$moduleName}/resources/lang/{$locale}/{$fileName}.php");
            if (array_key_exists($group[$i], $trans)) {
                $tempp  = explode('.', $checkKey, 2);
                $temp = &$trans[$tempp[0]];
                $level = explode('.', $tempp[1]);
                foreach ($level as $keyChange => $valueChange) {
                    if ($keyChange == (count($level) - 1)) {
                        $temp[$valueChange] =  $value;
                    }
                    $temp = &$temp[$valueChange];
                }
            } else {
                $tempp  = explode('.', $checkKey, 2);
                $temp = &$trans[$tempp[0]];
                $level = explode('.', $tempp[1]);
                foreach ($level as $keyChange => $valueChange) {
                    if ($keyChange == (count($level) - 1)) {
                        $temp[$valueChange] =  $value;
                    }
                    $temp = &$temp[$valueChange];
                }
            }
            $content  = "<?php" . "\r\n return ";
            $content .= $this->var_export54($trans);
            $content .= ";";
            file_put_contents("{$basePath}/Modules/{$moduleName}/resources/lang/{$locale}/{$fileName}.php", $content);
            \Artisan::call('module:publish-translation', ["module" => $moduleName]);
            return response()->json([
                'type' => 'success',
                'message' => 'success',
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    public function var_export54($var, $indent = "")
    {
        switch (gettype($var)) {
            case "string":
                return '"' . addcslashes($var, "\\\$\"\r\n\t\v\f") . '"';
            case "array":
                $indexed = array_keys($var) === range(0, count($var) - 1);
                $r = [];
                foreach ($var as $key => $value) {
                    $r[] = "$indent    "
                        . ($indexed ? "" : $this->var_export54($key) . " => ")
                        . $this->var_export54($value, "$indent    ");
                }
                return "[\n" . implode(",\n", $r) . "\n" . $indent . "]";
            case "boolean":
                return $var ? "TRUE" : "FALSE";
            default:
                return var_export($var, TRUE);
        }
    }
}
