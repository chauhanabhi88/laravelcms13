<?php

namespace Modules\Cron\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Cron\Repositories\CronScheduleRepository;

class CronScheduleController extends BackendController
{
    protected $cronSchedule = null;

    public function __construct(CronScheduleRepository $cronSchedule)
    {
        parent::__construct();
        $this->cronSchedule = $cronSchedule;
    }

    public function index(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config('cron.cache.entity_corn_schedule'), $request->get('per_page'));
                $request->merge(['per_page' => $perPage]);
            }
            $statusOptions = $this->cronSchedule->getStatusOptions();
            $collection = $this->cronSchedule->pagination($request);
            $filters = $this->cronSchedule->getFilters($request);
            // $columns = $this->cronSchedule->sortColumns($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);

            return view('cron::backend.schedule.scheduleindex', compact('request', 'statusOptions', 'collection', 'columns', 'filters', 'activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.schedule.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config('cron.cache.entity_corn_schedule'), $request->get('per_page'));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config('cron.cache.entity_corn_schedule'), $request);
            $statusOptions = $this->cronSchedule->getStatusOptions(false);
            $collection = $this->cronSchedule->pagination($request);
            $filters = $this->cronSchedule->getFilters($request);
            // $columns =  $this->cronSchedule->sortColumns($request);
            $activeMenuId = getActiveMenuId($request, 'admin.schedule.index');
            $columns = getColumnObject()->getColumns($activeMenuId);

            $content = view('cron::backend.schedule.partials.grid', compact('collection', 'columns', 'filters', 'request', 'statusOptions', 'activeMenuId'));

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function delete(Request $request)
    {
        try {
            $this->cronSchedule->deleteRecord($request);

            return redirect()->route('admin.schedule.index', updateUrlParams())->with('success', trans('cron::cron_schedule.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.schedule.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function massDelete(Request $request)
    {
        try {

            $cron_id = $request->id;

            $this->cronSchedule->destroyMultiple($request);

            if ($cron_id != 'null') {
                return redirect()->route('admin.cron.edit', updateUrlParams([$cron_id]))->with('success', trans('cron::cron_schedule.messages.deleted_success'));
            }

            return redirect()->route('admin.schedule.index', updateUrlParams())->with('success', trans('cron::cron_schedule.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.schedule.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }
}
