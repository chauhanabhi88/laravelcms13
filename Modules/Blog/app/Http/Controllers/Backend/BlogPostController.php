<?php

namespace Modules\Blog\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Blog\Models\BlogPost;
use Modules\Blog\Repositories\BlogPostRepository;
use Modules\Blog\Repositories\BlogPostCategoryRelationRepository;
use Modules\Blog\Repositories\BlogCategoryRepository;
use Modules\Blog\Http\Requests\CreateBlogPostRequest;
use Modules\Blog\Http\Requests\UpdateBlogPostRequest;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Menu\Models\Menu;

class BlogPostController extends BackendController
{
    /**
     * @var BlogPostRepository
     */ 
    private $blog_post;

    /**
      * @var UserEntity
     */
    private $blog_postEntity;
    private $blog_category;
    private $blog_post_catRepo;

    public function __construct(BlogPostCategoryRelationRepository $blog_post_catRepo, BlogPostRepository $blog_postRepo, BlogPost $blog_post, BlogCategoryRepository $blog_catRepo)
    {
        parent::__construct();

        $this->blog_post = $blog_postRepo;
        $this->blog_postEntity = $blog_post;
        $this->blog_category = $blog_catRepo;
        $this->blog_post_catRepo = $blog_post_catRepo;
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
                $perPage = getPerPageForModule(config("blog.cache.blog_post_name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            // $columns = $this->blog_post->sortColumns($request);
            $statusOptions = $this->blog_post->getStatusOptions(true);
            $collection = $this->blog_post->pagination($request);
            $filters = $this->blog_post->getFilters($request, $statusOptions);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);
            
            return view('blog::backend.blog_post.index', compact('request', 'collection', 'columns', 'filters','statusOptions','activeMenuId'));
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
                $perPage = getPerPageForModule(config("blog.cache.blog_post_name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config("blog.cache.blog_post_name"), $request);
            // $columns = $this->blog_post->sortColumns($request);
            $statusOptions = $this->blog_post->getStatusOptions(true);
            $filters = $this->blog_post->getFilters($request, $statusOptions);
            $collection = $this->blog_post->pagination($request);
            $activeMenuId = getActiveMenuId($request, 'admin.blog_post.index');
            $columns = getColumnObject()->getColumns($activeMenuId);
            
            $content = view('blog::backend.blog_post.partials.grid', compact('request', 'collection', 'columns', 'filters','statusOptions','activeMenuId'));
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
        $this->getAssetManager()->addAsset("modules/theme/backend/select2/css/select2.min.css");
        $this->getAssetManager()->addAsset("modules/theme/backend/select2-bootstrap4-theme/select2-bootstrap4.min.css");
        $this->getAssetManager()->addAsset("modules/theme/backend/select2/js/select2.full.min.js");
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
            $imageTypes = (!empty(settings("blog", "image_type")))?settings("blog", "image_type"):"jpeg,jpg,png";
            $imageTypes = explode(",", $imageTypes);
            $imageTypes = "." . implode(",.", $imageTypes);
            $languageOptions = getLanguageOptions();
            $blogCategories = $this->blog_category->getAllBlogCategory();
            
            return view('blog::backend.blog_post.create' , compact("imageTypes","languageOptions", "blogCategories"));
        }
         catch (\Throwable $e)
        {
            return redirect()->route('admin.blog_post.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CreateBlogPostRequest $request)
    {
        try
        {
            $params = $request->all();

            $languageOptions = $this->getLanguageOptions();
            if(isset($languageOptions) && !empty($languageOptions)) {
                foreach ($languageOptions as $key => $language) {
                    $params[$key]['content'] = $this->replaceSummernoteImageContent($params[$key]['content'], strtolower(config('blog.blog_post_name')));
                    $params[$key]['short_content'] = $this->replaceSummernoteImageContent($params[$key]['short_content'], strtolower(config('blog.blog_post_name')));
                }
            }

            if ($request->file("image")) {
                    $imageUploadParams = array(
                    "module_name" => \Config::get("blog.name") . "/" . \Config::get("blog.blog_post_name"),
                        "dbfield" => "image",
                        "thumbnail" => true,
                        "thumbnail_height" => 100,
                        "thumbnail_width" => 100,
                );
                $formData = $this->blog_post->setUploadParams($imageUploadParams)->uploadImage($request);
                $params["image"] = $formData["image"];
            }
            if($params["post_date"]){
                $params["post_date"] = date_format(date_create_from_format(config("core.encrypt.php_datepicker_format"), $params["post_date"]), "Y-m-d");
            }
            $params["is_featured"] = (!empty( $params["is_featured"] )) ? "1" : "2";
            $params["status"] = (!empty( $params["status"] )) ? "1" : "2";

            $blog_post = $this->blog_post->create($params);

            $blog_categories = (isset($params['blog_categories'])) ? $params['blog_categories'] : [];
            if (isset($blog_categories) && !empty($blog_categories)) {
                foreach ($blog_categories as $value) {
                    $blog_category[] = [
                        'post_id' => $blog_post->id,
                        'category_id' => $value
                    ];
                }
            }
            if ($blog_category) {
                $this->blog_post_catRepo->insert($blog_category);
            }

            if(isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.blog_post.edit', updateUrlParams([$blog_post->id]))->with("success", trans("blog::blog_post.messages.created_success"));
            }
            return redirect()->route('admin.blog_post.index',updateUrlParams())->with("success", trans("blog::blog_post.messages.created_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog_post.create',updateUrlParams())->with("error", $e->getMessage());
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
            $this->requireAsstes();
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("blog::blog_post.messages.data_invalid"));
            }
            $blog_post = $this->blog_post->find($id);
            if(!$blog_post) {
                throw new \Exception(trans("blog::blog_post.messages.data_invalid"));
            }
            $imageTypes = (!empty(settings("blog", "image_type")))?settings("blog", "image_type"):"jpeg,jpg,png";
            $imageTypes = explode(",", $imageTypes);
            $imageTypes = "." . implode(",.", $imageTypes);
            $languageOptions = getLanguageOptions();
            $blogCategories = $this->blog_category->getAllBlogCategory();
            $selectedPostCategory = $this->blog_post_catRepo->getAllPostCategories($id);
            $statusOptions = $this->blog_post->getStatusOptions();
            return view('blog::backend.blog_post.edit', compact('selectedPostCategory', 'blogCategories', 'blog_post', 'languageOptions' , "imageTypes", "statusOptions"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog_post.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(UpdateBlogPostRequest $request)
    {
        try
        {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("blog::blog_post.messages.data_invalid"));
            }
            $params = $request->all();
            //dd($params);
            $blog_post = $this->blog_post->find($id);
            if(!$blog_post) {
                throw new \Exception(trans("blog::blog_post.messages.data_invalid"));
            }
            $languageOptions = $this->getLanguageOptions();
            if(isset($languageOptions) && !empty($languageOptions)) {
                foreach ($languageOptions as $key => $language) {
                    $params[$key]['content'] = $this->replaceSummernoteImageContent($params[$key]['content'], strtolower(config('blog.blog_post_name')));
                    $params[$key]['short_content'] = $this->replaceSummernoteImageContent($params[$key]['short_content'], strtolower(config('blog.blog_post_name')));
                }
            }
            if (!empty($params["remove_image"])) {
                $imageRemoveParams = array(
                    "module_name" => \Config::get("blog.name") . "/" . \Config::get("blog.blog_post_name"),
                        "dbfield" => "image",
                );
                $this->blog_post->setUploadParams($imageRemoveParams)->setModel($blog_post)->removeFile($blog_post->image,strtolower(\Config::get("blog.name") . "/" . \Config::get("blog.blog_post_name")));
                $params["image"] = null;
            }

            if ($request->file("image")) {
                if (isset($blog_post->image)) {
                    $imageRemoveParams = array(
                        "module_name" => \Config::get("blog.name") . "/" . \Config::get("blog.blog_post_name"),
                        "dbfield" => "image",
                    );
                    $this->blog_post->setUploadParams($imageRemoveParams)->setModel($blog_post)->removeFile($blog_post->image,strtolower(\Config::get("blog.name") . "/" . \Config::get("blog.blog_post_name")));
                    $params["blog_post"]["image"] = null;					
                }
                $imageUploadParams = array(
                    "module_name" => \Config::get("blog.name") . "/" . \Config::get("blog.blog_post_name"),
                    "dbfield" => "image",
                    "thumbnail" => true,
                    "thumbnail_height" => 100,
                    "thumbnail_width" => 100,
                );
                $formData = $this->blog_post->setUploadParams($imageUploadParams)->uploadImage($request);
                $params["image"] = $formData["image"];
            }
            if($params["post_date"]){
                $params["post_date"] = date_format(date_create_from_format(config("core.encrypt.php_datepicker_format"),$params["post_date"]), "Y-m-d");
            }
            $params["is_featured"] = (!empty( $params["is_featured"] )) ? "1" : "2";
            $params["status"] = (!empty( $params["status"] )) ? "1" : "2";

            $this->blog_post->update($blog_post, $params);

            $blog_categories = (isset($params['blog_categories'])) ? $params['blog_categories'] : [];
            $blog_category = [];
            if (isset($blog_categories) && !empty($blog_categories)) {
                $blog_category = $this->blog_post_catRepo->checkCategories($id, $blog_categories);
            }
            if ($blog_category) {
                $this->blog_post_catRepo->insert($blog_category);
            }

            if(isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.blog_post.edit', updateUrlParams([$id]))->with("success", trans("blog::blog_post.messages.updated_success"));
            }
            return redirect()->route('admin.blog_post.index',updateUrlParams())->with("success", trans("blog::blog_post.messages.updated_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog_post.edit', updateUrlParams([$id]))->with("error", $e->getMessage());
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
            $this->blog_post->deleteRecord($request);
            return redirect()->route('admin.blog_post.index',updateUrlParams())->with("success", trans("blog::blog_post.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog_post.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request){
        try {
            $this->blog_post->destroyMultiple($request);
            return redirect()->route('admin.blog_post.index',updateUrlParams())->with("success", trans("blog::blog_post.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog_post.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

     public function updateStatus(Request $request){
        if($request->get('id')){
            $id = $request->get('id');
            $status = $request->get('status');
            $blog_postRow = $this->blog_post->find($id);
           $status = ($status == 1)? config('core.enabled') : config('core.disabled');
            $params = array('status'=>$status);
            $this->blog_post->update($blog_postRow, $params);
        }
        $gridRequest = new Request();
        $gridRequest->merge([
            'active_menu_id' => $request->get('active_menu_id'),
            'message' => trans("core::core.messages.status_change_success")
        ]);
        return $this->filters($gridRequest);
    }
}
