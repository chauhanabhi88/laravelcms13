<?php

namespace Modules\Language\Http\Controllers\Backend;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Language\Http\Controllers\Backend\Concerns\HandlesTranslationFiles;
use Modules\Language\Repositories\TranslationRepository;

class TranslationController extends BackendController
{
    use HandlesTranslationFiles;

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
     *
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
            $resolvedModuleName = ! empty($moduleName) ? $this->resolveModuleName($moduleName, $data) : null;
            if (! empty($data) && ! empty($languages) && ! empty($resolvedModuleName)) {
                $i = 0;
                foreach ($languages as $langKey => $langValue) {
                    $dir = base_path('Modules/'.$resolvedModuleName.'/resources/lang/'.$langKey);
                    if (is_dir($dir)) {
                        $files = array_diff(scandir($dir), ['..', '.']);
                        foreach ($files as $key => $fileValue) {
                            $file = $dir.'/'.$fileValue;
                            $return = require $file;
                            $this->getExportData($langKey, $moduleName, $fileValue, $return);
                        }
                    }
                }
            }
            $result = [];
            $result = $this->data;

            return view('language::backend.translation.index', compact('data', 'result', 'languages', 'moduleName'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function getExportData($langOption, $module, $file, $values, $count = null, $key = null)
    {
        try {
            foreach ($values as $valuesData => $value) {
                if (is_array($value)) {
                    $this->data[$this->inc]['module'] = $module;
                    $this->data[$this->inc]['file'] = $file;
                    $this->data[$this->inc]['key'] = $valuesData;
                    $this->key = $this->data[$this->inc]['key'];
                    if ($count > 0) {
                        $this->key = $key.'.'.$this->key;
                    }
                    $this->count++;
                    unset($this->data[$this->inc]);
                    $this->getExportData($langOption, $module, $file, $value, $this->count, $this->key);
                } else {
                    if (! in_array(basename($file, '.php').':'.$this->key.'.'.$valuesData, $this->uniqueKey)) {
                        $this->data[$this->inc]['module'] = $module;
                        $this->data[$this->inc]['file'] = $file;
                        $this->data[$this->inc]['key'] = $this->key.'.'.$valuesData;
                        $this->uniqueKey[$this->inc] = basename($file, '.php').':'.$this->key.'.'.$valuesData;
                        $this->data[$this->inc][$langOption] = $value;
                        $this->data[$this->inc]['display'] = strtolower($module).'::'.basename($file, '.php').'.'.$this->key.'.'.$valuesData;
                        $this->inc++;
                        $this->count = 0;
                    } else {
                        $temp = array_search(basename($file, '.php').':'.$this->key.'.'.$valuesData, $this->uniqueKey);
                        $this->data[$temp][$langOption] = $value;
                    }
                }
            }
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function update(Request $request)
    {
        try {
            $locale = $request->get('locale');
            $key = $request->get('key');
            $value = $request->get('value');
            $group = explode('.', $key);
            $namespace = explode('::', $group[0]);
            $fileName = $namespace[1] ?? null;
            $moduleName = $namespace[0] ?? null;

            $moduleName = $moduleName ? $this->resolveModuleName($moduleName, $this->translation->getModulesName()) : null;
            $locale = $locale ? $this->resolveLocale($locale, getLanguageOptions()) : null;
            $fileName = $fileName ? $this->sanitizeFileName($fileName) : null;
            if (! $moduleName || ! $locale || ! $fileName) {
                throw new \Exception(trans('language::language.messages.data_invalid'));
            }

            unset($group[0]);
            $checkKey = implode('.', $group);
            $i = 1;
            $groupCount = count($group);
            $basePath = base_path();
            if (! is_dir("{$basePath}/Modules/{$moduleName}/resources/lang/{$locale}")) {
                mkdir("{$basePath}/Modules/{$moduleName}/resources/lang/{$locale}", 0777, true);
            }
            if (! file_exists("{$basePath}/Modules/{$moduleName}/resources/lang/{$locale}/{$fileName}.php")) {
                $temp = '<?php'."\r\n return [ \n ]; ";
                file_put_contents("{$basePath}/Modules/{$moduleName}/resources/lang/{$locale}/{$fileName}.php", $temp);
            }
            $trans = $this->finder->getRequire("{$basePath}/Modules/{$moduleName}/resources/lang/{$locale}/{$fileName}.php");
            if (array_key_exists($group[$i], $trans)) {
                $tempp = explode('.', $checkKey, 2);
                $temp = &$trans[$tempp[0]];
                $level = explode('.', $tempp[1]);
                foreach ($level as $keyChange => $valueChange) {
                    if ($keyChange == (count($level) - 1)) {
                        $temp[$valueChange] = $value;
                    }
                    $temp = &$temp[$valueChange];
                }
            } else {
                $tempp = explode('.', $checkKey, 2);
                $temp = &$trans[$tempp[0]];
                $level = explode('.', $tempp[1]);
                foreach ($level as $keyChange => $valueChange) {
                    if ($keyChange == (count($level) - 1)) {
                        $temp[$valueChange] = $value;
                    }
                    $temp = &$temp[$valueChange];
                }
            }
            $content = '<?php'."\r\n return ";
            $content .= $this->var_export54($trans);
            $content .= ';';
            file_put_contents("{$basePath}/Modules/{$moduleName}/resources/lang/{$locale}/{$fileName}.php", $content);
            \Artisan::call('module:publish-translation', ['module' => $moduleName]);

            return response()->json([
                'type' => 'success',
                'message' => 'success',
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }
}
