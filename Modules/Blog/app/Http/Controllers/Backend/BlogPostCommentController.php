<?php

namespace Modules\Blog\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Modules\Blog\Repositories\BlogPostCommentRepository;
use Modules\Blog\Repositories\BlogPostRepository;
use Modules\Customer\Repositories\CustomerRepository;
use Modules\Core\Http\Controllers\BackendController;
use Illuminate\Support\Facades\Auth;
use Modules\Menu\Models\Menu;

class BlogPostCommentController extends BackendController
{
    private $blog_post_commentRepo;
    private $customerRepo;
    private $blog_postRepo;

    public function __construct(BlogPostRepository $blog_postRepo, BlogPostCommentRepository $blog_post_commentRepo, CustomerRepository $customerRepo)
    {
        parent::__construct();

        $this->blog_post_commentRepo = $blog_post_commentRepo;
        $this->customerRepo = $customerRepo;
        $this->blog_postRepo = $blog_postRepo;
    }

    public function index(Request $request)
    {
        try
        {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("blog.cache.blog_post_comment_name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            // $columns = $this->blog_post_commentRepo->sortColumns($request);
            $statusOptions = $this->blog_post_commentRepo->getCommentStatusOption(true);
            $collection = $this->blog_post_commentRepo->pagination($request);
            $filters = $this->blog_post_commentRepo->getFilters($request, $statusOptions);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);
            return view('blog::backend.blog_post_comment.index', compact('request', 'collection', 'columns', 'filters','statusOptions','activeMenuId'));
        }
        catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    public function filters(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("blog.cache.blog_post_comment_name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config("blog.cache.blog_post_comment_name"), $request);
            // $columns = $this->blog_post_commentRepo->sortColumns($request);
            $statusOptions = $this->blog_post_commentRepo->getCommentStatusOption(true);
            $collection = $this->blog_post_commentRepo->pagination($request);
            $filters = $this->blog_post_commentRepo->getFilters($request, $statusOptions);
            $activeMenuId = getActiveMenuId($request, 'admin.blog_post_comment.index');
            $columns = getColumnObject()->getColumns($activeMenuId);
            $content = view('blog::backend.blog_post_comment.partials.grid', compact('request', 'collection', 'columns', 'filters','statusOptions','activeMenuId'));
           
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

    public function edit(Request $request) {
        try {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("blog::blog_post_comment.messages.data_invalid"));
            }
            $comment = $this->blog_post_commentRepo->find($id);
            $customerName = $this->customerRepo->getAllCustomerName($comment->customer_id);
            $postTitle = $this->blog_postRepo->getAllPostTitle($comment->post_id);
            $statusOptions = $this->blog_post_commentRepo->getCommentStatusOption();
            // dd($statusOptions[$comment->status]);
            return view('blog::backend.blog_post_comment.edit', compact('statusOptions', 'comment', 'customerName', 'postTitle'));

        } catch (\Throwable $e) {
            return redirect()->route('admin.blog_post_comment.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    public function update(Request $request) {
        try
        {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("blog::blog_post_comment.messages.data_invalid"));
            }
            $params = $request->all();
            $comment = $this->blog_post_commentRepo->find($id);
            if(!$comment) {
                throw new \Exception(trans("blog::blog_post_comment.messages.data_invalid"));
            }
            $params['comment']['admin_id'] = Auth::id();
            
            $this->blog_post_commentRepo->update($comment,  $params['comment']);
            if(isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.blog_post_comment.edit', updateUrlParams([$id]))->with("success", trans("blog::blog_post_comment.messages.updated_success"));
            }
            return redirect()->route('admin.blog_post_comment.index',updateUrlParams())->with("success", trans("blog::blog_post_comment.messages.updated_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog_post_comment.edit', updateUrlParams([$id]))->with("error", $e->getMessage());
        }
    }

    public function delete(Request $request) {
        try {          
            $this->blog_post_commentRepo->deleteRecord($request);
            return redirect()->route('admin.blog_post_comment.index',updateUrlParams())->with("success", trans("blog::blog_post.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog_post_comment.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    public function massDelete(Request $request){
        try {
            $this->blog_post_commentRepo->destroyMultiple($request);
            return redirect()->route('admin.blog_post_comment.index',updateUrlParams())->with("success", trans("blog::blog_post.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.blog_post_comment.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

}