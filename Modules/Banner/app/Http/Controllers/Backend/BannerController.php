<?php

namespace Modules\Banner\Http\Controllers\Backend;

use Config;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Banner\Http\Requests\Banner\CreateRequest;
use Modules\Banner\Http\Requests\Banner\UpdateRequest;
use Modules\Banner\Models\Banner;
use Modules\Banner\Repositories\BannerGroupRepository;
use Modules\Banner\Repositories\BannerRepository;
use Modules\Core\Http\Controllers\BackendController;

class BannerController extends BackendController
{
    /**
     * @var BannerRepository
     */
    private $banner;

    /**
     * @var BannerEntity
     */
    private $bannerEntity;

    public function __construct(BannerRepository $banner, Banner $bannerEntity, BannerGroupRepository $bannerGroup)
    {
        parent::__construct();
        $this->banner = $banner;
        $this->bannerEntity = $bannerEntity;
        $this->bannerGroup = $bannerGroup;
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
                $perPage = getPerPageForModule('banner', $request->get('per_page'));
                $request->merge(['per_page' => $perPage]);
            }

            $statusOptions = $this->banner->getStatusOptions(true);
            $bannerGroups = $this->bannerGroup->getBannerGroups(true);
            $collection = $this->banner->pagination($request);
            $filters = $this->banner->getFilters($request, $statusOptions, $bannerGroups);
            // $columns = $this->banner->sortColumns($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);

            return view('banner::backend.banner.index', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'bannerGroups', 'activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config('banner.cache.name'), $request->get('per_page'));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config('banner.cache.name'), $request);
            $statusOptions = $this->banner->getStatusOptions(true);
            $bannerGroups = $this->bannerGroup->getBannerGroups(true);
            $collection = $this->banner->pagination($request);
            $filters = $this->banner->getFilters($request, $statusOptions, $bannerGroups);
            // $columns = $this->banner->sortColumns($request);
            $activeMenuId = getActiveMenuId($request, 'admin.banner.index');
            $columns = getColumnObject()->getColumns($activeMenuId);
            $content = view('banner::backend.banner.partials.grid', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'bannerGroups', 'activeMenuId'));

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
    public function create(Request $request)
    {
        try {
            $languageOptions = $this->getLanguageOptions();
            $statusOptions = $this->banner->getStatusOptions(true);
            $bannerGroups = $this->bannerGroup->getBannerGroups(true);
            $uploadLimit = settings('banner', 'max_upload_size');
            $imageTypes = $this->getImageTypes();

            return view('banner::backend.banner.create', compact('statusOptions', 'bannerGroups', 'imageTypes', 'uploadLimit', 'languageOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    private function getImageTypes()
    {
        $imageTypes = settings('banner', 'image_type');
        $imageTypes = explode(',', $imageTypes);
        $imageTypes = '.'.implode(',.', $imageTypes);

        return $imageTypes;
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
            $formData = $request->all();
            $formData['status'] = (isset($formData['status'])) ? config('core.enabled') : config('core.disabled');
            if (isset($formData['is_featured']) && $formData['is_featured']) {
                $formData['is_featured'] = config('core.yes');
            } else {
                $formData['is_featured'] = config('core.no');
            }

            if ($request->file('image')) {
                $imageUploadParams = [

                    'module_name' => Config::get('banner.name'),
                    'dbfield' => 'image',
                    'thumbnail' => true,
                    'thumbnail_size' => 100,
                ];
                $params = $this->banner->setUploadParams($imageUploadParams)->uploadImage($request);

                $formData['image'] = $params['image'];
                $banner = $this->banner->create($formData);
            } else {
                $banner = $this->banner->create($formData);
            }
            if ($request['snc']) {
                return redirect()->route('admin.banner.edit', updateUrlParams([$banner->id]))->with('success', trans('banner::banner.messages.created_success'));
            }

            return redirect()->route('admin.banner.index', updateUrlParams())->with('success', trans('banner::banner.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.banner.create', updateUrlParams())->with('error', $e->getMessage());
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
                throw new \Exception(trans('banner::banner.messages.data_invalid'));
            }
            $banner = $this->banner->find($id);
            if (! $banner) {
                throw new \Exception(trans('banner::banner.messages.data_invalid'));
            }
            $languageOptions = $this->getLanguageOptions();
            $statusOptions = $this->banner->getStatusOptions();
            $bannerGroups = $this->bannerGroup->getBannerGroups(true);
            $uploadLimit = settings('banner', 'max_upload_size');
            $imageTypes = $this->getImageTypes();

            return view('banner::backend.banner.edit', compact('banner', 'statusOptions', 'bannerGroups', 'uploadLimit', 'imageTypes', 'languageOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.banner.index', updateUrlParams())->with('error', $e->getMessage());
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
                throw new \Exception(trans('banner::banner.messages.data_invalid'));
            }
            $banner = $this->banner->find($id);
            if (! $banner) {
                throw new \Exception(trans('banner::banner.messages.data_invalid'));
            }
            $params = $request->all();
            $params['status'] = (isset($params['status'])) ? config('core.enabled') : config('core.disabled');
            if (isset($params['is_featured']) && $params['is_featured']) {
                $params['is_featured'] = config('core.yes');
            } else {
                $params['is_featured'] = config('core.no');
            }
            if (isset($params['remove_banner']) && $params['remove_banner']) {
                $imageRemoveParams = [
                    'module_name' => strtolower(Config::get('banner.name')),
                    'dbfield' => 'image',
                ];
                $this->banner->setUploadParams($imageRemoveParams)->setModel($banner)->removeFile($banner->image, 'Banner');
                $params['image'] = null;
            }
            $request->merge($params);
            if ($request->file('image')) {
                $imageUploadParams = [
                    'module_name' => strtolower(Config::get('banner.name')),
                    'dbfield' => 'image',
                    'thumbnail' => true,
                    'thumbnail_width' => 100,
                    'thumbnail_height' => 100,
                ];
                $params = $this->banner->setUploadParams($imageUploadParams)->setModel($banner)->uploadImage($request);
                $params['image'] = $params['image'];
                $banner = $this->banner->update($banner, $params);
            } else {
                if ((! $request->get('remove_banner')) && $request['banner_image'] == null) {
                    unset($params['banner']);
                }
                $banner = $this->banner->update($banner, $params);
            }
            if ($request['snc']) {
                return redirect()->route('admin.banner.edit', updateUrlParams([$id]))->with('success', trans('banner::banner.messages.updated_success'));
            }

            return redirect()->route('admin.banner.index', updateUrlParams())->with('success', trans('banner::banner.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.banner.edit', updateUrlParams([$id]))->with('error', $e->getMessage());
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
            $imageRemoveParams = [
                'module_name' => strtolower(Config::get('banner.name')),
                'dbfield' => 'image',
            ];
            $this->banner->deleteRecord($request, $imageRemoveParams);

            return redirect()->route('admin.banner.index', updateUrlParams())->with('success', trans('banner::banner.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.banner.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $imageRemoveParams = [
                'module_name' => strtolower(Config::get('banner.name')),
                'dbfield' => 'image',
            ];
            $this->banner->setUploadParams($imageRemoveParams)->destroyMultiple($request, true);

            return redirect()->route('admin.banner.index', updateUrlParams())->with('success', trans('banner::banner.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.banner.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function updateStatus(Request $request)
    {
        if ($request->get('id')) {
            $id = $request->get('id');
            $status = $request->get('status');
            $bannerRow = $this->banner->find($id);
            if ($bannerRow) {
                $status = ($status == 1) ? config('core.enabled') : config('core.disabled');
                $params = ['status' => $status];
                $this->banner->update($bannerRow, $params);
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
