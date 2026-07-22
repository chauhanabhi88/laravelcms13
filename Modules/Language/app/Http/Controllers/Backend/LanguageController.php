<?php

namespace Modules\Language\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Language\Models\Language;
use Modules\Language\Repositories\LanguageRepository;
use Modules\Language\Http\Requests\CreateRequest;
use Modules\Language\Http\Requests\UpdateRequest;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Core\Repositories\ModuleRepository;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Language\Exports\TranslationExport;
use Illuminate\Filesystem\Filesystem;
use App;
use Modules\Menu\Models\Menu;

class LanguageController extends BackendController
{
    /**
     * @var LanguageRepository
     */
    private $language;
    protected $module;
    private $data = [];
    private $inc = 0;
    private $key;
    private $count = 0;
    private $uniqueKey = [];

    /**
     * @var UserEntity
     */
    private $languageEntity;
    private $finder;

    public function __construct(LanguageRepository $language, Language $languageEntity, ModuleRepository $module, Filesystem $finder)
    {
        parent::__construct();
        $this->language = $language;
        $this->languageEntity = $languageEntity;
        $this->module = $module;
        $this->finder = $finder;
    }
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("language.name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            $this->requireAsstes();
            $statusOptions = $this->language->getStatusOptions(true);
            $yesNoOptions = $this->language->getYesNoOptions(true);
            // $columns = $this->language->sortColumns($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);
            $collection = $this->language->pagination($request);
            $filters = $this->language->getFilters($request, $statusOptions, $yesNoOptions);
            $translationOptions = $this->language->getTranslationOptions();
            $headers = \Config('language.import_headers');
            $otherNotes = trans('language::language.other_notes');
            return view('language::backend.index', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'yesNoOptions', 'translationOptions','headers','otherNotes','activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function filters(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("language.name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config("language.name"), $request);
            $statusOptions = $this->language->getStatusOptions(true);
            $yesNoOptions = $this->language->getYesNoOptions(true);
            // $columns = $this->language->sortColumns($request);
            $activeMenuId = getActiveMenuId($request, 'admin.language.index');
            $columns = getColumnObject()->getColumns($activeMenuId);
            $filters = $this->language->getFilters($request, $statusOptions, $yesNoOptions);
            $collection = $this->language->pagination($request);

            $content = view('language::backend.partials.grid', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'yesNoOptions', 'activeMenuId'));

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString()
                ],
                'message' => $request->get('message'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        try {
            $statusOptions = $this->language->getStatusOptions(true);
            $yesNoOptions = $this->language->getYesNoOptions(true);

            return view('language::backend.create', compact('statusOptions', 'yesNoOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.language.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CreateRequest $request)
    {
        try {
            $params = $request->all();
            if ($params['language']['is_default'] == config("core.yes")) {
                $this->language->where('is_default', '=', config("core.yes"))->update(['is_default' => config("core.no")]);
            }
            if ($params['language']['is_default'] == config("core.no")) {
                if (!$this->language->where('is_default', '=', config("core.yes"))->exists()) {
                    throw new \Exception(trans("language::language.messages.is_defalut_value"));
                }
            }
            $params['language']['status'] = (isset($params['language']['status'])) ? config('core.enabled') : config('core.disabled');
            $language = $this->language->create($params['language']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.language.edit', updateUrlParams([$language->id]))->with("success", trans("language::language.messages.created_success"));
            }
            return redirect()->route('admin.language.index', updateUrlParams())->with("success", trans("language::language.messages.created_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.language.create', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit(Request $request)
    {
        try {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("language::language.messages.data_invalid"));
            }
            $language = $this->language->find($id);
            if (!$language) {
                throw new \Exception(trans("language::language.messages.data_invalid"));
            }
            $statusOptions = $this->language->getStatusOptions();
            $yesNoOptions = $this->language->getYesNoOptions();

            return view('language::backend.edit', compact('language', 'statusOptions', 'yesNoOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.language.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(UpdateRequest $request)
    {
        try {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("language::language.messages.data_invalid"));
            }
            $params = $request->all();
            if ($params['language']['is_default'] == config("core.no")) {
                if (!$this->language->where('id', '!=', $id)->where('is_default', '=', config("core.yes"))->exists()) {
                    throw new \Exception(trans("language::language.messages.is_defalut_value"));
                }
            }
            if ($params['language']['is_default'] == config("core.yes")) {
                $this->language->where('id', '!=', $id)->update(['is_default' => config("core.no")]);
                App::setLocale($this->language->where('is_default', '=', config("core.yes"))->value('locale'));
            }
            $params['language']['status'] = (isset($params['language']['status'])) ? config('core.enabled') : config('core.disabled');
            $language = $this->language->find($id);
            if (!$language) {
                throw new \Exception(trans("language::language.messages.data_invalid"));
            }
            $this->language->update($language, $params['language']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.language.edit', updateUrlParams([$id]))->with("success", trans("language::language.messages.updated_success"));
            }
            return redirect()->route('admin.language.index', updateUrlParams())->with("success", trans("language::language.messages.updated_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.language.edit', updateUrlParams([$id]))->with("error", $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function updateStatus(Request $request)
    {
        if ($request->get('id')) {
            $id = $request->get('id');
            $status = $request->get('status');
            $languageRow = $this->language->find($id);
            $status = ($status == 1) ? 1 : 2;
            $params = array('status' => $status);
            $this->language->update($languageRow, $params);
        }
        $gridRequest = new Request();
        $gridRequest->merge([
            'active_menu_id' => $request->get('active_menu_id'),
            'message' => trans("core::core.messages.status_change_success")
        ]);
        return $this->filters($gridRequest);
    }

    /* Import & Export Translations */


    public function exportTranslation(Request $request)
    {
        try {
            $params = $request->all();
            if (!isset($params['options'])) {
                throw new \Exception(trans("language::language.messages.translation_option_invalid"));
            }
            $admin_key = 0;
            $frontend_key = 0;
            $options = $params['options'];
            foreach ($options as $value) {
                if ($value == config('language.admin_key')) {
                    $admin_key = 1;
                } elseif ($value == config('language.frontend_key')) {
                    $frontend_key = 1;
                }
            }
            $data = $this->module->getModulesName();
            $languages = getLanguageOptions();
            if (empty($languages)) {
                throw new \Exception(trans("core::core.messages.data_invalid"));
            }
            if (!empty($data)) {
                $i = 0;
                foreach ($data as $key => $module) {
                    foreach ($languages as $lang => $value) {
                        $dir = __DIR__ . '/../../../../' . $module . '/Resources/lang/' . $lang;
                        if (is_dir($dir)) {
                            $files = array_diff(scandir($dir), array('..', '.'));
                            if (!$files) {
                                continue;
                            }
                            foreach ($files as $key => $fileValue) {
                                if ($admin_key == 0) {
                                    if (!(str_starts_with($fileValue, 'front_'))) {
                                        continue;
                                    }
                                } elseif ($frontend_key == 0) {
                                    if (str_starts_with($fileValue, 'front_')) {
                                        continue;
                                    }
                                }
                                $file = __DIR__ . '/../../../../' . $module . '/Resources/lang/' . $lang . '/' . $fileValue;
                                $return = require $file;
                                $this->getExportData($module, $fileValue, $return, $lang);
                            }
                        }
                    }
                }
            }
            if (!$this->data) {
                throw new \Exception(trans("language::language.messages.data_invalid"));
            }
            return Excel::download(new TranslationExport($this->data), 'Translation.xlsx');
        } catch (\Throwable $e) {
            return redirect()->route('admin.language.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }
    public function getExportData($module, $file, $values, $lang, $count = null, $key = null)
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
                    $this->getExportData($module, $file, $value, $lang, $this->count, $this->key);
                } else {
                    if (!in_array(basename($file, ".php") . ':' . $this->key . '.' . $valuesData, $this->uniqueKey)) {
                        $this->data[$this->inc]['module'] = $module;
                        $this->data[$this->inc]['file'] = $file;
                        $this->data[$this->inc]['key'] = $this->key . '.' . $valuesData;
                        $this->uniqueKey[$this->inc] =   basename($file, ".php") . ':' . $this->key . '.' . $valuesData;
                        $this->data[$this->inc][$lang] = $value;
                        $this->inc++;
                        $this->count = 0;
                    } else {
                        $temp = array_search(basename($file, ".php") . ':' . $this->key . '.' . $valuesData,  $this->uniqueKey);
                        $this->data[$temp][$lang] = $value;
                    }
                }
            }
        } catch (\Throwable $e) {
            return redirect()->route('admin.language.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    public function importTranslation(Request $request)
    {
        try {
            $importedFile =  $request->file('import_file');
            $collection = Excel::toArray([], $importedFile);
            $collection = $collection[0];
            $modules = $this->module->getModulesName();
            unset($modules['']);
            if (!$modules) {
                throw new \Exception(trans("core::core.messages.data_invalid"));
            }
            $languages = [];
            if (!$collection) {
                throw new \Exception(trans("core::core.messages.data_invalid"));
            }
            $headers = $collection[0];
            foreach ($collection[0] as $key => $value) {
                $languages[$value] = $value;
            }
            unset($languages['module']);
            unset($languages['file']);
            unset($languages['key']);
            unset($languages['']);
            foreach ($collection as $value) {
                if ($value == $headers) {
                    continue;
                }
                $value = array_combine($headers, $value);
                $module = $value['module'];
                $file = $value['file'];
                $key =  $value['key'];
                $group =  explode('.', $key);
                $basePath = base_path();
                foreach ($languages as $langKey => $lang) {
                    $i = 0;
                    if (!is_dir("{$basePath}/Modules/{$module}/Resources/lang/{$lang}")) {
                        mkdir("{$basePath}/Modules/{$module}/Resources/lang/{$lang}", 0777, true);
                    }
                    if (!file_exists("{$basePath}/Modules/{$module}/Resources/lang/{$lang}/{$file}")) {
                        $temp  = "<?php" . "\r\n return [ \n ]; ";
                        file_put_contents("{$basePath}/Modules/{$module}/Resources/lang/{$lang}/{$file}", $temp);
                    }
                    $trans = $this->finder->getRequire("{$basePath}/Modules/{$module}/Resources/lang/{$lang}/{$file}");
                    if (array_key_exists($group[$i], $trans)) {
                        $tempp  = explode('.', $key, 2);
                        $temp = &$trans[$tempp[0]];
                        $level = explode('.', $tempp[1]);
                        foreach ($level as $keyChange => $valueChange) {
                            if ($keyChange == (count($level) - 1)) {
                                $temp[$valueChange] =  $value[$lang];
                            }
                            $temp = &$temp[$valueChange];
                        }
                    } else {
                        $tempp  = explode('.', $key, 2);
                        $temp = &$trans[$tempp[0]];
                        $level = explode('.', $tempp[1]);
                        foreach ($level as $keyChange => $valueChange) {
                            if ($keyChange == (count($level) - 1)) {
                                $temp[$valueChange] =  $value[$lang];
                            }
                            $temp = &$temp[$valueChange];
                        }
                    }
                    $content  = "<?php" . "\r\n return ";
                    $content .= $this->var_export54($trans);
                    $content .= ";";
                    file_put_contents("{$basePath}/Modules/{$module}/Resources/lang/{$lang}/{$file}", $content);
                    \Artisan::call('module:publish-translation', ["module" => $module]);
                }
            }
            return redirect()->route('admin.language.index', updateUrlParams())->with("success", trans("core::core.messages.import_translations_message"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.language.index', updateUrlParams())->with("error", $e->getMessage());
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
    private function requireAsstes()
    {
        $this->_assetManager->addAsset("modules/theme/backend/select2/css/select2.min.css");
        $this->_assetManager->addAsset("modules/theme/backend/select2-bootstrap4-theme/select2-bootstrap4.min.css");
        $this->_assetManager->addAsset("modules/theme/backend/select2/js/select2.full.min.js");
    }
}
