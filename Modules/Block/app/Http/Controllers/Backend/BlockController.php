<?php

namespace Modules\Block\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Block\Http\Requests\CreateRequest;
use Modules\Block\Http\Requests\UpdateRequest;
use Modules\Block\Models\Block;
use Modules\Block\Repositories\BlockRepository;
use Modules\Core\Http\Controllers\BackendController;

class BlockController extends BackendController
{
    /**
     * @var BlockRepository
     */
    private $block;

    /**
     * @var UserEntity
     */
    private $blockEntity;

    public function __construct(BlockRepository $block, Block $blockEntity)
    {
        parent::__construct();

        $this->block = $block;
        $this->blockEntity = $blockEntity;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            $perPage = getPerPageForModule(config('block.name'), $request->get('per_page'));
            $request->merge(['per_page' => $perPage]);
            $languageOptions = getLanguageOptions(true);
            $statusOptions = $this->block->getStatusOptions(true);

            $collection = $this->block->pagination($request);
            $filters = $this->block->getFilters($request, $languageOptions, $statusOptions);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);

            return view('block::backend.index', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'languageOptions', 'activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
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
            $perPage = getPerPageForModule(config('block.name'), $request->get('per_page'));
            $request->merge(['per_page' => $perPage]);
            setFilterSession(config('block.name'), $request);
            $languageOptions = getLanguageOptions(true);
            $statusOptions = $this->block->getStatusOptions(true);
            $filters = $this->block->getFilters($request, $languageOptions, $statusOptions);
            $collection = $this->block->pagination($request);
            $activeMenuId = getActiveMenuId($request, 'admin.block.index');
            $columns = getColumnObject()->getColumns($activeMenuId);

            $content = view('block::backend.partials.grid', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'languageOptions', 'activeMenuId'));

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
            $languageOptions = getLanguageOptions();
            $this->_assetManager->addAsset('modules/theme/backend/js/jquery.slug.js');
            $this->_assetManager->addAsset('modules/pages/js/summernote.min.js');
            $this->_assetManager->addAsset('modules/pages/css/summernote.css');
            $statusOptions = $this->block->getStatusOptions(true);

            return view('block::backend.create', compact('statusOptions', 'languageOptions'));
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
            $params = $request->all();
            $params['is_enabled'] = (isset($params['is_enabled'])) ? config('core.enabled') : config('core.disabled');
            $languageOptions = $this->getLanguageOptions();
            if (isset($languageOptions) && ! empty($languageOptions)) {
                foreach ($languageOptions as $key => $language) {
                    $params[$key]['content'] = $this->replaceSummernoteImageContent($params[$key]['content'], strtolower(config('block.name')));
                }
            }
            $block = $this->block->create($params, ['content']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.block.edit', updateUrlParams([$block->id]))->with('success', trans('block::block.messages.created_success'));
            }

            return redirect()->route('admin.block.index', updateUrlParams())->with('success', trans('block::block.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.block.create', updateUrlParams())->with('error', $e->getMessage());
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
                throw new \Exception(trans('block::block.messages.data_invalid'));
            }
            $languageOptions = getLanguageOptions();
            $block = $this->block->find($id);
            if (! $block) {
                throw new \Exception(trans('block::block.messages.data_invalid'));
            }
            $this->_assetManager->addAsset('modules/theme/backend/js/jquery.slug.js');
            $this->_assetManager->addAsset('modules/pages/js/summernote.min.js');
            $this->_assetManager->addAsset('modules/pages/css/summernote.css');

            $statusOptions = $this->block->getStatusOptions();

            return view('block::backend.edit', compact('block', 'statusOptions', 'languageOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.block.index', updateUrlParams())->with('error', $e->getMessage());
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
                throw new \Exception(trans('block::block.messages.data_invalid'));
            }
            $languageOptions = getLanguageOptions();
            $params = $request->all();
            $block = $this->block->find($id);
            if (! $block) {
                throw new \Exception(trans('block::block.messages.data_invalid'));
            }

            $languageOptions = $this->getLanguageOptions();
            if (isset($languageOptions) && ! empty($languageOptions)) {
                foreach ($languageOptions as $key => $language) {
                    $params[$key]['content'] = $this->replaceSummernoteImageContent($params[$key]['content'], strtolower(config('block.name')));
                }
            }
            $params['is_enabled'] = (isset($params['is_enabled'])) ? config('core.enabled') : config('core.disabled');
            $this->block->update($block, $params, ['content']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.block.edit', updateUrlParams([$id]))->with('success', trans('block::block.messages.updated_success'));
            }

            return redirect()->route('admin.block.index', updateUrlParams())->with('success', trans('block::block.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.block.edit', updateUrlParams([$id]))->with('error', $e->getMessage());
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
            $this->block->deleteRecord($request);

            return redirect()->route('admin.block.index', updateUrlParams())->with('success', trans('block::block.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.block.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $this->block->destroyMultiple($request);

            return redirect()->route('admin.block.index', updateUrlParams())->with('success', trans('block::block.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.block.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function updateStatus(Request $request)
    {
        try {
            if ($request->get('id')) {
                $id = $request->get('id');
                $status = $request->get('status');
                $blockRow = $this->block->find($id);
                if (! $blockRow) {
                    throw new \Exception(trans('block::block.messages.data_invalid'));
                }
                $status = ($status == 1) ? config('core.enabled') : config('core.disabled');
                $params = ['is_enabled' => $status];
                $this->block->update($blockRow, $params);
            }
            $gridRequest = new Request;
            $gridRequest->merge([
                'active_menu_id' => $request->get('active_menu_id'),
                'message' => trans('core::core.messages.status_change_success'),
            ]);

            return $this->filters($gridRequest);
        } catch (\Throwable $e) {
            $gridRequest = new Request;
            $gridRequest->merge([
                'active_menu_id' => $request->get('active_menu_id'),
                'message' => $e->getMessage(),
            ]);

            return $this->filters($gridRequest);
        }
    }
}
