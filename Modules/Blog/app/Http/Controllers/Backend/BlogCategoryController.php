<?php

namespace Modules\Blog\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Blog\Models\BlogCategory;
use Modules\Blog\Repositories\BlogCategoryRepository;
use Modules\Blog\Http\Requests\CreateBlogCategoryRequest;
use Modules\Blog\Http\Requests\UpdateBlogCategoryRequest;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Menu\Models\Menu;

class BlogCategoryController extends BackendController
{
    /**
     * @var BlogCategoryRepository
     */
    private $blog_category;

    /**
      * @var UserEntity
     */
    private $blog_categoryEntity;

    public function __construct(BlogCategoryRepository $blog_categoryRepo, BlogCategory $blog_category)
    {
        parent::__construct();

        $this->blog_category = $blog_categoryRepo;
        $this->blog_categoryEntity = $blog_category;
    }
    /**
     * Display a listing of the resource.
      * @return Response
     */
    public function index(Request $request)
    {
        try
        {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("blog.cache.blog_category_name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            // $columns = $this->blog_category->sortColumns($request);
            $statusOptions = $this->blog_category->getStatusOptions(true);
            $collection = $this->blog_category->pagination($request);
            $filters = $this->blog_category->getFilters($request, $statusOptions);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);
            return view('blog::backend.blog_category.index', compact('request', 'collection', 'columns', 'filters','statusOptions','activeMenuId'));
        }
       catch (\Throwable $e) {
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
                $perPage = getPerPageForModule(config("blog.cache.blog_category_name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config("blog.cache.blog_category_name"), $request);
            // $columns = $this->blog_category->sortColumns($request);
             $statusOptions = $this->blog_category->getStatusOptions(true);
            $filters = $this->blog_category->getFilters($request, $statusOptions);
            $collection = $this->blog_category->pagination($request);
            $activeMenuId = getActiveMenuId($request, 'admin.blog_category.index');
            $columns = getColumnObject()->getColumns($activeMenuId);
            
            $content = view('blog::backend.blog_category.partials.grid', compact('request', 'collection', 'columns', 'filters','statusOptions','activeMenuId'));
            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString()
                ],
                'message' => $request->get('message')
            ]);
        } catch (Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    private function requireAsstes()
    {
        $this->getAssetManager()->addAsset("modules/pages/js/summernote.min.js");
        $this->getAssetManager()->addAsset("modules/pages/css/summernote.css");
        $this->getAssetManager()->addAsset("modules/theme/backend/js/jquery.slug.js");
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        try
        {
            $this->requireAsstes();
            $languageOptions = getLanguageOptions();
            return view('blog::backend.blog_category.create' , compact("languageOptions"));
        }
         catch (\Throwable $e)
        {
            return redirect()->route('admin.blog_category.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CreateBlogCategoryRequest $request)
    {
        try
        {
            $params = $request->all();

            $languageOptions = $this->getLanguageOptions();
            if(isset($languageOptions) && !empty($languageOptions)) {
                foreach ($languageOptions as $key => $language) {
                    $params[$key]['description'] = $this->replaceSummernoteImageContent($params[$key]['description'], strtolower(config('blog.blog_category_name')));
                }
            }

			$params["status"] = (!empty( $params["status"] )) ? "1" : "2";
            $blog_category = $this->blog_category->create($params);
            if(isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.blog_category.edit', updateUrlParams([$blog_category->id]))->with("success", trans("blog::blog_category.messages.created_success"));
            }
            return redirect()->route('admin.blog_category.index',updateUrlParams())->with("success", trans("blog::blog_category.messages.created_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog_category.create',updateUrlParams())->with("error", $e->getMessage());
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
                throw new \Exception(trans("blog::blog_category.messages.data_invalid"));
            }
            $blog_category = $this->blog_category->find($id);
            if(!$blog_category) {
                throw new \Exception(trans("blog::blog_category.messages.data_invalid"));
            }
			$this->requireAsstes();
            $languageOptions = getLanguageOptions();
            $statusOptions = $this->blog_category->getStatusOptions();
            return view('blog::backend.blog_category.edit', compact('blog_category', 'languageOptions', 'statusOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog_category.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(UpdateBlogCategoryRequest $request)
    {
        try
        {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("blog::blog_category.messages.data_invalid"));
            }
            $params = $request->all();
            $blog_category = $this->blog_category->find($id);
            if(!$blog_category) {
                throw new \Exception(trans("blog::blog_category.messages.data_invalid"));
            }
			$params["status"] = (!empty( $params["status"] )) ? "1" : "2";
            $languageOptions = $this->getLanguageOptions();
            if(isset($languageOptions) && !empty($languageOptions)) {
                foreach ($languageOptions as $key => $language) {
                    $params[$key]['description'] = $this->replaceSummernoteImageContent($params[$key]['description'], strtolower(config('blog.blog_category_name')));
                }
            }
            $this->blog_category->update($blog_category, $params);
            if(isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.blog_category.edit', updateUrlParams([$id]))->with("success", trans("blog::blog_category.messages.updated_success"));
            }
            return redirect()->route('admin.blog_category.index',updateUrlParams())->with("success", trans("blog::blog_category.messages.updated_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog_category.edit', updateUrlParams([$id]))->with("error", $e->getMessage());
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
            $this->blog_category->deleteRecord($request);
            return redirect()->route('admin.blog_category.index',updateUrlParams())->with("success", trans("blog::blog_category.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog_category.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request){
        try {
            $this->blog_category->destroyMultiple($request);
            return redirect()->route('admin.blog_category.index',updateUrlParams())->with("success", trans("blog::blog_category.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog_category.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

     public function updateStatus(Request $request){
        if($request->get('id')){
            $id = $request->get('id');
            $status = $request->get('status');
            $blog_categoryRow = $this->blog_category->find($id);
           $status = ($status == 1)? config('core.enabled') : config('core.disabled');
            $params = array('status'=>$status);
            $this->blog_category->update($blog_categoryRow, $params);
        }
        $gridRequest = new Request();
        $gridRequest->merge([
            'active_menu_id' => $request->get('active_menu_id'),
            'message' => trans("core::core.messages.status_change_success")
        ]);
        return $this->filters($gridRequest);
    }
}
