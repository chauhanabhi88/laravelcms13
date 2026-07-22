<?php
namespace Modules\Pages\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Pages\Models\Pages;
use Modules\Pages\Repositories\PagesRepository;
use Modules\Pages\Http\Requests\UpdateRequest;
use Modules\Pages\Http\Requests\CreateRequest;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Menu\Models\Menu;
 
class PagesController extends BackendController
{

    protected $page = null;
    protected $pageEntity = null;

    public function __construct(PagesRepository $page, Pages $pageEntity){
        parent::__construct();
        $this->page = $page;
        $this->pageEntity = $pageEntity;
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("pages.name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            $languageOptions = getLanguageOptions(true);
            $statusOptions = $this->page->getStatusOptions(true);
            $collection = $this->page->pagination($request);
            $filters = $this->page->getFilters($request, $languageOptions, $statusOptions);
            // $columns = $this->page->sortColumns($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);

            return view('pages::backend.index', compact('request', 'collection', 'columns', 'filters', 'statusOptions','languageOptions','activeMenuId'));
        }  catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index',updateUrlParams())->with("error", $e->getMessage());
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
                $perPage = getPerPageForModule(config("pages.name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config("pages.name"), $request);
            $languageOptions = getLanguageOptions(true);
            $statusOptions = $this->page->getStatusOptions(true);
            $filters = $this->page->getFilters($request, $languageOptions, $statusOptions);
            $collection = $this->page->pagination($request);
            // $columns = $this->page->sortColumns($request);
            $activeMenuId = getActiveMenuId($request, 'admin.page.index');
            $columns = getColumnObject()->getColumns($activeMenuId);

            $content = view('pages::backend.partials.grid', compact('request', 'collection', 'columns', 'filters', 'statusOptions','languageOptions','activeMenuId'));

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
    function create(PagesRepository $page)
    {
        try {
            $languageOptions = getLanguageOptions();
            $this->getAssetManager()->addAsset("modules/theme/backend/js/jquery.slug.js");
            $this->getAssetManager()->addAsset('modules/pages/js/summernote.min.js');
            $this->getAssetManager()->addAsset('modules/pages/css/summernote.css');
            return view('pages::backend.create', compact('page','languageOptions'));
        }catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CreateRequest $request, PagesRepository $page)
    {
        try {
            $params = $request->all();
            $languageOptions = $this->getLanguageOptions();
            if(isset($languageOptions) && !empty($languageOptions)) {
                foreach ($languageOptions as $key => $language) {
                    $params[$key]['body'] = $this->replaceSummernoteImageContent($params[$key]['body'], strtolower(config('pages.name')));
                }
            }

            $params['status'] = (isset($params['status']))?config('core.enabled'):config('core.disabled');
            $languageOptions = getLanguageOptions();
            $page = $this->page->create($params, $ignorefields = ['body']);
            if(isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.page.edit', updateUrlParams([$page->id]))->with("success", trans("pages::pages.messages.created_success"));
            }
            return redirect()->route('admin.page.index',updateUrlParams())->with("success", trans("pages::pages.messages.created_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.page.create',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit(Request $request, PagesRepository $page)
    {
        try {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("pages::pages.messages.data_invalid"));
            }
            $languageOptions = getLanguageOptions();
            $this->getAssetManager()->addAsset("modules/theme/backend/js/jquery.slug.js");
            $this->getAssetManager()->addAsset('modules/pages/js/summernote.min.js');
            $this->getAssetManager()->addAsset('modules/pages/css/summernote.css');
            $pageRepository = $page;
            $statusOptions = $pageRepository->getStatusOptions();
            $page = $this->page->find($id);
            if(!$page) {
                throw new \Exception(trans("pages::pages.messages.data_invalid"));
            }
            return view('pages::backend.edit', compact('page', 'pageRepository','languageOptions','statusOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.page.index',updateUrlParams())->with("error", $e->getMessage());
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
                throw new \Exception(trans("pages::pages.messages.data_invalid"));
            }
            $params = $request->all();
            $page = $this->page->find($id);
            if(!$page) {
                throw new \Exception(trans("pages::pages.messages.data_invalid"));
            }
            $params['status'] = (isset($params['status']))?config('core.enabled'):config('core.disabled');

            $languageOptions = $this->getLanguageOptions();
            if(isset($languageOptions) && !empty($languageOptions)) {
                foreach ($languageOptions as $key => $language) {
                    $params[$key]['body'] = $this->replaceSummernoteImageContent($params[$key]['body'], strtolower(config('pages.name')));
                }
            }
    
            $this->page->update($page, $params, $ignorefields = ['body']);
            if(isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.page.edit', updateUrlParams([$id]))->with("success", trans("pages::pages.messages.updated_success"));
            }
            return redirect()->route('admin.page.index',updateUrlParams())->with("success", trans("pages::pages.messages.updated_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.page.edit', updateUrlParams([$id]))->with("error", $e->getMessage());
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
            $this->page->deleteRecord($request);
            return redirect()->route('admin.page.index',updateUrlParams())->with("success", trans("pages::pages.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.page.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $this->page->destroyMultiple($request);
            return redirect()->route('admin.page.index',updateUrlParams())->with("success", trans("pages::pages.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.page.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }
    public function updateStatus(Request $request){
        if($request->get('id'))
        {
            $id = $request->get('id');
            $status = $request->get('status');
            $pageRow = $this->page->find($id);
            $status = ($status == 1)?1:2;
            $params = array('status'=>$status);
            $this->page->update($pageRow, $params);
        }
        $gridRequest = new Request();
        $gridRequest->merge([
            'active_menu_id' => $request->get('active_menu_id'),
            'message' => trans("core::core.messages.status_change_success")
        ]);
        return $this->filters($gridRequest);
    }
}
