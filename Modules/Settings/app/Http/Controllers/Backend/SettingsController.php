<?php

namespace Modules\Settings\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Settings\Models\Settings;
use Modules\Settings\Repositories\SettingsRepository;
use Nwidart\Modules\Contracts\RepositoryInterface;

class SettingsController extends BackendController
{
    protected $settings;

    public function __construct(SettingsRepository $settings)
    {
        parent::__construct();
        $this->settings = $settings;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(RepositoryInterface $modules)
    {
        try {
            $settings = $this->settings;
            $moduleList = $modules->all();

            return view('settings::backend.index', compact('moduleList', 'settings'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function getModuleSetting(Request $request, RepositoryInterface $modules)
    {
        try {
            $setting = $this->settings->getModuleSettings($request->get('module'));

            $settingData = [];
            if ($setting) {
                $settingData = json_decode($setting->value, true);
            }

            $module = $modules->find($request->get('module'));
            $path = $module->getPath();
            if (file_exists($path.'/config/settings.php')) {
                $elements = require_once $path.'/config/settings.php';
                $view = view('settings::backend.partials.settings', compact('elements', 'module', 'settingData'));
            }

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => $module->getLowerName().'-content',
                    'html' => $view->__toString(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function save(Request $request, Settings $settingEntity, RepositoryInterface $modules)
    {
        $redirectTab = null;
        try {

            $params = $request->all();
            $firstModuleSettingOn = 1;
            $redirectTab = $params['last_module'] ?? null;
            if (isset($params['db']) && $params['db']) {
                foreach ($params['db'] as $moduleName => $values) {
                    $settings = $this->settings->getModuleSettings($moduleName);
                    $passwordFields = [];

                    $module = $modules->find($moduleName);
                    $path = $module->getPath();

                    if (file_exists($path.'/config/settings.php')) {
                        $elements = require_once $path.'/config/settings.php';

                        $passwordFields = collect($elements)
                            ->flatMap(fn ($settings) => $settings)
                            ->filter(fn ($field) => ($field['type'] ?? null) === 'password')
                            ->keys()
                            ->toArray();

                        foreach ($passwordFields as $passwordField) {
                            if ($values[$passwordField] === '************') {
                                if ($settings) {
                                    $oldValues = json_decode($settings->value, true);
                                    $oldStoredValue = $oldValues[$passwordField] ?? null;
                                    $values[$passwordField] = $oldStoredValue;
                                }
                            }
                        }
                    }

                    $data = [
                        'name' => $moduleName,
                        'value' => json_encode($values),
                    ];

                    if (! $settings) {
                        $this->settings->create($data, ['value']);
                    } else {
                        $this->settings->update($settings, $data, ['value']);
                    }
                }
                $this->settings->flushCache(config('settings.name'));
            }
            if (isset($params['env']) && $params['env']) {
                $envData = [];
                foreach ($params['env'] as $moduleName => $values) {
                    $envData += $values;
                }
                if ($envData) {
                    $this->settings->setEnvironmentValues($envData);
                    $response = redirect()->route('admin.settings.index', updateUrlParams())->withInput(['tab' => '#'.$redirectTab.'-tab', 'tabpanel' => $redirectTab, 'firstModuleSettingOn' => $firstModuleSettingOn])->with('success', trans('settings::settings.messages.updated_success'));
                    \Artisan::call('config:cache');

                    return $response;
                }
            }

            return redirect()->route('admin.settings.index', updateUrlParams())->withInput(['tab' => '#'.$redirectTab.'-tab', 'tabpanel' => $redirectTab, 'firstModuleSettingOn' => $firstModuleSettingOn])->with('success', trans('settings::settings.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.settings.index', updateUrlParams())->withInput(['tab' => '#'.$redirectTab.'-tab'])->with('error', $e->getMessage());
        }
    }
}
