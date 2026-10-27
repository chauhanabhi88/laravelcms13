<?php

namespace Modules\Blog\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Blog\Models\BlogCategory;
use Modules\Blog\Repositories\BlogCategoryRepository;
use Modules\Blog\Repositories\BlogPostCommentRepository;
use Modules\Blog\Repositories\BlogPostRepository;
use Modules\Core\Http\Controllers\FrontendController;

class BlogController extends FrontendController
{
    public function __construct(
        protected BlogPostRepository $posts,
        protected BlogCategoryRepository $categories,
        protected BlogPostCommentRepository $comments,
    ) {
        parent::__construct();
    }

    /**
     * All published posts, optionally filtered by ?q= and ?category=.
     */
    public function index(Request $request): View|JsonResponse
    {
        $category = null;

        if ($slug = $request->query('category')) {
            $category = $this->categories->findActiveBySlug((string) $slug);
        }

        return $this->listing($request, $category);
    }

    public function category(Request $request): View|JsonResponse
    {
        $category = $this->categories->findActiveBySlug((string) $request->route('slug'));

        abort_if(! $category, 404);

        return $this->listing($request, $category);
    }

    public function show(Request $request): View
    {
        $post = $this->posts->findPublishedBySlug((string) $request->route('slug'));

        abort_if(! $post, 404);

        return view('blog::frontend.show', [
            'post' => $post,
            'related' => $this->posts->getRelatedPosts($post, (int) config('blog.front.related_limit')),
            'comments' => $this->comments->getApprovedForPost($post->id),
        ]);
    }

    /**
     * Full page normally; {html, next_page_url} for the "load more" fetch.
     */
    protected function listing(Request $request, ?BlogCategory $category = null): View|JsonResponse
    {
        $search = trim((string) $request->query('q')) ?: null;

        $posts = $this->posts->getPublishedPosts(
            ['q' => $search, 'category' => $category?->slug],
            (int) config('blog.front.per_page'),
            max(1, (int) $request->query('page', 1)),
        );
        $posts->withPath($request->url())->appends($request->except('page'));

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('blog::frontend.partials.post-cards', ['posts' => $posts->items()])->render(),
                'next_page_url' => $posts->nextPageUrl(),
            ]);
        }

        return view('blog::frontend.index', [
            'posts' => $posts,
            'category' => $category,
            'search' => $search,
            'categories' => $this->categories->getActiveCategoriesWithPostCounts()
                ->filter(fn (BlogCategory $item) => $item->published_posts_count > 0),
            'isFiltered' => $search !== null || $category !== null,
        ]);
    }
}
