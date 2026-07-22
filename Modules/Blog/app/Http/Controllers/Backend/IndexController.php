<?php

namespace Modules\Blog\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Blog\Models\Blog;
use Modules\Blog\Repositories\BlogRepository;
use Modules\Blog\Http\Requests\CreateRequest;
use Modules\Blog\Http\Requests\UpdateRequest;
use Modules\Core\Http\Controllers\BackendController;

class IndexController extends BackendController
{
    /**
     * @var BlogRepository
     */
    private $blog;

    /**
      * @var UserEntity
     */
    private $blogEntity;

    public function __construct(BlogRepository $blogRepo, Blog $blog)
    {
        parent::__construct();

        $this->blog = $blogRepo;
        $this->blogEntity = $blog;
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
                $perPage = getPerPageForModule('Blog', $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            $columns = $this->blog->sortColumns();
            $collection = $this->blog->pagination($request);
            $filters = $this->blog->getFilters($request);
            $statusOptions = $this->blog->getStatusOptions(true);
            
            return view('blog::backend.index', compact('request', 'collection', 'columns', 'filters','statusOptions'));
        }
        catch (\Throwable $e) {
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
                $perPage = getPerPageForModule('Blog', $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            $columns = $this->blog->sortColumns();
            $filters = $this->blog->getFilters($request);
            $collection = $this->blog->pagination($request);
            $statusOptions = $this->blog->getStatusOptions(true);
            
            $content = view('blog::backend.partials.grid', compact('request', 'collection', 'columns', 'filters','statusOptions'));
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

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        try
        {

            return view('blog::backend.create' );
        }
        catch (\Throwable $e)
        {
            return redirect()->route('admin.blog.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CreateRequest $request)
    {
        try
        {
            $params = $request->all();

            $blog = $this->blog->create($params['blog']);
            if(isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.blog.edit', updateUrlParams([$blog->id]))->with("success", trans("blog::blog.messages.created_success"));
            }
            return redirect()->route('admin.blog.index', updateUrlParams())->with("success", trans("blog::blog.messages.created_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog.create', updateUrlParams())->with("error", $e->getMessage());
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
                throw new \Exception(trans("blog::blog.messages.data_invalid"));
            }
            $blog = $this->blog->find($id);
            if(!$blog) {
                throw new \Exception(trans("blog::blog.messages.data_invalid"));
            }

            return view('blog::backend.edit', compact('blog' ));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog.index', updateUrlParams())->with("error", $e->getMessage());
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
        try
        {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("blog::blog.messages.data_invalid"));
            }
            $params = $request->all();
            $blog = $this->blog->find($id);
            if(!$blog) {
                throw new \Exception(trans("blog::blog.messages.data_invalid"));
            }

            $this->blog->update($blog, $params['blog']);
            if(isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.blog.edit', updateUrlParams([$id]))->with("success", trans("blog::blog.messages.updated_success"));
            }
            return redirect()->route('admin.blog.index', updateUrlParams())->with("success", trans("blog::blog.messages.updated_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog.edit', updateUrlParams([$id]))->with("error", $e->getMessage());
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
                throw new \Exception(trans("blog::blog.messages.data_invalid"));
            }
            $blog = $this->blog->find($id);
            if(!$blog) {
                throw new \Exception(trans("blog::blog.messages.data_invalid"));
            }

            $this->blog->destroy($blog);
            return redirect()->route('admin.blog.index', updateUrlParams())->with("success", trans("blog::blog.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request){
        try {
            $this->blog->destroyMultiple($request);
            return redirect()->route('admin.blog.index', updateUrlParams())->with("success", trans("blog::blog.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

     public function updateStatus(Request $request){
        if($request->get('id')){
            $id = $request->get('id');
            $status = $request->get('status');
            $blogRow = $this->blog->find($id);
            $status = ($status == 1)? config('core.enabled') : config('core.disabled');
            $params = array('status'=>$status);
            $this->blog->update($blogRow, $params);
        }
        $request = new Request();
        $request->merge(['message' => trans("core::core.messages.status_change_success")]);
        return $this->filters($request);
    }
}
