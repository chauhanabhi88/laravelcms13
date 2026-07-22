<?php

namespace Modules\Cron\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Cron\Models\Cron;
use Modules\Cron\Repositories\CronRepository;
use Modules\Cron\Repositories\CronScheduleRepository;
use Modules\Cron\Http\Requests\UpdateRequest;
use Modules\Cron\Http\Requests\CreateRequest;
use Nwidart\Modules\Facades\Module;
use Illuminate\Support\Str;
use Modules\Menu\Models\Menu;

class CronController extends BackendController
{

    protected $cron = null;
    protected $cronEntity = null;

    public function __construct(CronRepository $cron, Cron $cronEntity)
    {
        parent::__construct();
        $this->cron = $cron;
        $this->cronEntity = $cronEntity;
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("cron.cache.name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            $statusOptions = $this->cron->getStatusOptions();
            $collection = $this->cron->pagination($request);
            $filters = $this->cron->getFilters($request);
            // $columns = $this->cron->sortColumns($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);

            return view('cron::backend.index', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'activeMenuId'));
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
                $perPage = getPerPageForModule(config("cron.cache.name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config("cron.cache.name"), $request);
            $statusOptions = $this->cron->getStatusOptions(true);
            $filters = $this->cron->getFilters($request);
            $collection = $this->cron->pagination($request);
            // $columns = $this->cron->sortColumns($request);
            $activeMenuId = getActiveMenuId($request, 'admin.cron.index');
            $columns = getColumnObject()->getColumns($activeMenuId);

            $content = view('cron::backend.partials.grid', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'activeMenuId'));

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString()
                ],
                'message' => $request->get('message'),
            ]);
        } catch (\Exception $e) {
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
    function create(CronRepository $cron)
    {
        try {
            $allModules = Module::toCollection()->toArray();
            $moduleName[''] = "--select--";
            foreach ($allModules as $module) {
                $moduleName[$module['alias']] = $module['name'];
            }

            return view('cron::backend.create', compact('cron', 'moduleName'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.cron.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CreateRequest $request, CronRepository $cron)
    {
        try {
            $params = $request->all();
            $params['cron']['status'] = (isset($params['cron']['status'])) ? config('core.enabled') : config('core.disabled');

            if (isset($params['cron']['command'])) {
                \Artisan::call('module:cronmake-command', ['name' => $params['cron']['command'], 'module' => $params['cron']['module'], 'description' => $params['cron']['description']]);

                $filePath  = __DIR__ . '/../../../Console/Kernel.php';
                $data = "\Modules\\" . Str::studly($params['cron']['module']) . "\Console\\" . Str::studly($params['cron']['command']) . "::class,";
                if (is_file($filePath)) {
                    $filecontent = file_get_contents($filePath);
                    $filecontent = str_replace('protected $commands = [', 'protected $commands = [' . "\n\t\t" . $data, $filecontent);
                    file_put_contents($filePath, $filecontent);
                }

                $filePath  = __DIR__ . '/../../../Providers/CronServiceProvider.php';
                if (is_file($filePath)) {
                    $filecontent = file_get_contents($filePath);
                    $filecontent = str_replace('$this->commands([', '$this->commands([' . "\n\t\t\t" . $data, $filecontent);
                    file_put_contents($filePath, $filecontent);
                }
            }
            $params['cron']['command'] = $params['cron']['module'] . ":" . $params['cron']['command'];
            $cron = $this->cron->create($params['cron']);

            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.cron.edit', updateUrlParams([$cron->id]))->with("success", trans("cron::cron.messages.created_success"));
            }
            return redirect()->route('admin.cron.index', updateUrlParams())->with("success", trans("cron::cron.messages.created_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.cron.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit(Request $request, CronRepository $cron, CronScheduleRepository $cronSchedule)
    {
        try {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("cron::cron.messages.data_invalid"));
            }
            $cronRepository = $cron;
            $cron = $this->cron->find($id);
            if (!$cron) {
                throw new \Exception(trans("cron::cron.messages.data_invalid"));
            }
            $allModules = Module::toCollection()->toArray();
            $moduleName[''] = "--select--";
            foreach ($allModules as $module) {
                $moduleName[$module['alias']] = $module['name'];
            }
            $commandArr = explode(":", $cron->command, 2);
            $module = $commandArr[0];
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule('cron_schedule_' . $id, $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            $filterSessionKey = config("cron.cache.entity_corn_schedule") . "_" . $id;
            $statusOptions = $cronSchedule->getStatusOptions();
            $collection = $cronSchedule->pagination($request, $filterSessionKey);
            $filters = $cronSchedule->getFilters($request, $filterSessionKey);
            $columns = $cronSchedule->sortColumns($request);

            return view('cron::backend.edit', compact('moduleName', 'module', 'cron', 'cronRepository', 'collection', 'columns', 'filters', 'request', 'statusOptions', 'id'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.cron.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function scheduleFilters(Request $request)
    {
        try {
            $id = $request->id;
            $cronSchedule = app(CronScheduleRepository::class);
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule('cron_schedule_' . $id, $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            $filterSessionKey = config("cron.cache.entity_corn_schedule") . "_" . $id;
            $statusOptions = $cronSchedule->getStatusOptions();
            $collection = $cronSchedule->pagination($request, $filterSessionKey);
            $filters = $cronSchedule->getFilters($request, $filterSessionKey);
            $columns = $cronSchedule->sortColumns($request);

            $content = view('cron::backend.partials.scheduled-grid', compact('collection', 'columns', 'filters', 'request', 'statusOptions', 'id'));

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
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
                throw new \Exception(trans("cron::cron.messages.data_invalid"));
            }
            $params = $request->all();
            $cron = $this->cron->find($id);
            if ($cron->description !== $params['cron']['description']) {
                $commandArr = explode(":", $cron->command, 2);
                $module = Str::studly($commandArr[0]);
                $command = $commandArr[1];

                $filePath = __DIR__ . '/../../../../' . $module . '/Console/' . Str::studly($command) . '.php';
                $data = $params['cron']['description'];
                if (is_file($filePath)) {
                    $filecontent = file_get_contents($filePath);
                    $filecontent = str_replace($cron->description, $data, $filecontent);
                    file_put_contents($filePath, $filecontent);
                }
            }
            if (!$cron) {
                throw new \Exception(trans("cron::cron.messages.data_invalid"));
            }
            $params['cron']['status'] = (isset($params['cron']['status'])) ? config('core.enabled') : config('core.disabled');
            $this->cron->update($cron, $params['cron']);

            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.cron.edit', updateUrlParams([$id]))->with("success", trans("cron::cron.messages.updated_success"));
            }
            return redirect()->route('admin.cron.index', updateUrlParams())->with("success", trans("cron::cron.messages.updated_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.cron.edit', updateUrlParams([$id]))->with("error", $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function delete(Request $request)
    {
        try {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("cron::cron.messages.data_invalid"));
            }
            $cron = $this->cron->find($id);

            $commandArr = explode(":", $cron->command, 2);
            $module = Str::studly($commandArr[0]);
            $command = $commandArr[1];
            $filePath  = __DIR__ . '/../../../Console/Kernel.php';
            $data = "\Modules\\" . $module . "\Console\\" . Str::studly($command) . "::class,";
            if (is_file($filePath)) {
                $filecontent = file_get_contents($filePath);
                $filecontent = str_replace("\n\t\t" . $data, '', $filecontent);
                file_put_contents($filePath, $filecontent);
            }

            $filePath  = __DIR__ . '/../../../Providers/CronServiceProvider.php';
            if (is_file($filePath)) {
                $filecontent = file_get_contents($filePath);
                $filecontent = str_replace("\n\t\t\t" . $data, '', $filecontent);
                file_put_contents($filePath, $filecontent);
            }

            $filePath = __DIR__ . '/../../../../' . $module . '/Console/' . Str::studly($command) . '.php';
            if (is_file($filePath)) {
                unlink($filePath);
            }

            if (!$cron) {
                throw new \Exception(trans("cron::cron.messages.data_invalid"));
            }
            $this->cron->destroy($cron);
            return redirect()->route('admin.cron.index', updateUrlParams())->with("success", trans("cron::cron.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.cron.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $this->cron->destroyMultiple($request);
            return redirect()->route('admin.cron.index', updateUrlParams())->with("success", trans("cron::cron.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.cron.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function scheduleDelete(Request $request)
    {
        try {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("cron::cron.messages.data_invalid"));
            }
            $cronSchedule = app(CronScheduleRepository::class);
            $_cronSchedule = $cronSchedule->find($id);
            if (!$_cronSchedule) {
                throw new \Exception(trans("cron::cron.messages.data_invalid"));
            }
            $cronId = $_cronSchedule->cron_id;
            $cronSchedule->destroy($_cronSchedule);
            return redirect()->route('admin.cron.index', updateUrlParams([$cronId]))->with("success", trans("cron::cron_schedule.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.cron.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    public function updateStatus(Request $request)
    {
        if ($request->get('id')) {
            $id = $request->get('id');
            $status = $request->get('status');
            $cronRow = $this->cron->find($id);
            $status = ($status == 1) ? 1 : 2;
            $params = array('status' => $status);
            $this->cron->update($cronRow, $params);
        }
        $gridRequest = new Request();
        $gridRequest->merge([
            'active_menu_id' => $request->get('active_menu_id'),
            'message' => trans("core::core.messages.status_change_success")
        ]);
        return $this->filters($gridRequest);
    }

    public function runCron(Request $request)
    {
        try {
            $command = $request->command;
            if (!$command) {
                throw new \Exception(trans("cron::cron.messages.data_invalid"));
            }
            \Artisan::call($command);
            \Artisan::call('cache:clear');
            return redirect()->route('admin.cron.index', updateUrlParams())->with("success", trans("cron::cron.messages.run_cron"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.cron.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }
}
