<?php

namespace Modules\Banner\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Banner\Http\Requests\BannerGroup\CreateRequest;
use Modules\Banner\Http\Requests\BannerGroup\UpdateRequest;
use Modules\Banner\Models\BannerGroup;
use Modules\Banner\Repositories\BannerGroupRepository;
use Modules\Core\Http\Controllers\BackendController;

class BannerGroupController extends BackendController
{
    /**
     * @var BannerGroupRepository
     */
    private $bannerGroup;

    /**
     * @var BannerGroupEntity
     */
    private $bannerGroupEntity;

    public function __construct(BannerGroupRepository $bannerGroup, BannerGroup $bannerGroupEntity)
    {
        parent::__construct();
        $this->bannerGroup = $bannerGroup;
        $this->bannerGroupEntity = $bannerGroupEntity;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule('banner_group', $request->get('per_page'));
                $request->merge(['per_page' => $perPage]);
            }
            $statusOptions = $this->bannerGroup->getStatusOptions(true);
            $collection = $this->bannerGroup->pagination($request);
            $filters = $this->bannerGroup->getFilters($request, $statusOptions);
            // $columns = $this->bannerGroup->sortColumns($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);

            return view('banner::backend.banner_group.index', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config('banner.cache.banner_group_name'), $request->get('per_page'));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config('banner.cache.banner_group_name'), $request);
            $statusOptions = $this->bannerGroup->getStatusOptions(true);
            $collection = $this->bannerGroup->pagination($request);
            $filters = $this->bannerGroup->getFilters($request, $statusOptions);
            // $columns = $this->bannerGroup->sortColumns($request);
            $activeMenuId = getActiveMenuId($request, 'admin.bannergroup.index');
            $columns = getColumnObject()->getColumns($activeMenuId);
            $content = view('banner::backend.banner_group.partials.grid', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'activeMenuId'));

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString(),
                ],
                'message' => $request->get('message'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        try {
            $languageOptions = $this->getLanguageOptions();
            $statusOptions = $this->bannerGroup->getStatusOptions(true);

            return view('banner::backend.banner_group.create', compact('statusOptions', 'languageOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(CreateRequest $request)
    {
        try {
            $saveAndContinue = $request['snc'];
            $bannerGroupData = $request->all();
            $bannerGroupData['status'] = (isset($bannerGroupData['status'])) ? config('core.enabled') : config('core.disabled');

            $bannergroup = $this->bannerGroup->create($bannerGroupData);

            if ($saveAndContinue) {
                return redirect()->route('admin.bannergroup.edit', updateUrlParams([$bannergroup->id]))->with('success', trans('banner::banner_group.messages.created_success'));
            }

            return redirect()->route('admin.bannergroup.index', updateUrlParams())->with('success', trans('banner::banner_group.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.bannergroup.create', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request)
    {
        try {
            $id = $request->id;
            if (! $id) {
                throw new \Exception(trans('banner::banner_group.messages.data_invalid'));
            }
            $bannerGroup = $this->bannerGroup->find($id);
            if (! $bannerGroup) {
                throw new \Exception(trans('banner::banner_group.messages.data_invalid'));
            }
            $languageOptions = $this->getLanguageOptions();
            $statusOptions = $this->bannerGroup->getStatusOptions();

            return view('banner::backend.banner_group.edit', compact('bannerGroup', 'statusOptions', 'languageOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.bannergroup.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(UpdateRequest $request)
    {
        try {
            $id = $request->id;
            if (! $id) {
                throw new \Exception(trans('banner::banner_group.messages.data_invalid'));
            }
            $saveAndContinue = $request['snc'];
            $bannerGroup = $this->bannerGroup->find($id);
            if (! $bannerGroup) {
                throw new \Exception(trans('banner::banner_group.messages.data_invalid'));
            }
            $bannerGroupData = $request->all();
            $bannerGroupData['status'] = (isset($bannerGroupData['status'])) ? config('core.enabled') : config('core.disabled');
            $this->bannerGroup->update($bannerGroup, $bannerGroupData);
            if (isset($saveAndContinue) && $saveAndContinue) {
                return redirect()->route('admin.bannergroup.edit', updateUrlParams([$id]))->with('success', trans('banner::banner_group.messages.updated_success'));
            }

            return redirect()->route('admin.bannergroup.index', updateUrlParams())->with('success', trans('banner::banner_group.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.bannergroup.edit', updateUrlParams([$id]))->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function delete(Request $request)
    {
        try {
            $this->bannerGroup->deleteRecord($request);

            return redirect()->route('admin.bannergroup.index', updateUrlParams())->with('success', trans('banner::banner_group.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.bannergroup.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $this->bannerGroup->destroyMultiple($request);

            return redirect()->route('admin.bannergroup.index', updateUrlParams())->with('success', trans('banner::banner_group.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.bannergroup.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * change status from grid
     */
    public function updateStatus(Request $request)
    {
        if ($request->get('id')) {
            $id = $request->get('id');
            $status = $request->get('status');
            $bannerGroupRow = $this->bannerGroup->find($id);
            if ($bannerGroupRow) {
                $status = ($status == 1) ? config('core.enabled') : config('core.disabled');
                $params = ['status' => $status];
                $this->bannerGroup->update($bannerGroupRow, $params);
            }
        }
        $gridRequest = new Request;
        $gridRequest->merge([
            'active_menu_id' => $request->get('active_menu_id'),
            'message' => trans('core::core.messages.status_change_success'),
        ]);

        return $this->filters($gridRequest);
    }
}
