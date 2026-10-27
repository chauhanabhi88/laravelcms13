<?php

namespace Modules\Blog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Blog\Repositories\BlogCategoryRepository;
use Modules\Blog\Repositories\BlogPostCommentRepository;
use Modules\Blog\Repositories\BlogPostRepository;
use Modules\Blog\Transformers\BlogCategoryResource;
use Modules\Blog\Transformers\BlogCommentResource;
use Modules\Blog\Transformers\BlogPostDetailResource;
use Modules\Blog\Transformers\BlogPostResource;

/**
 * Unauthenticated, read-only blog API. Only enabled and published content.
 */
class PublicBlogController extends Controller
{
    public const MAX_PER_PAGE = 50;

    public function __construct(
        protected BlogPostRepository $posts,
        protected BlogCategoryRepository $categories,
        protected BlogPostCommentRepository $comments,
    ) {}

    /**
     * GET posts?q=&category=&per_page=&page=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(self::MAX_PER_PAGE, max(1, (int) $request->query('per_page', config('blog.front.per_page'))));

        $posts = $this->posts->getPublishedPosts(
            [
                'q' => trim((string) $request->query('q')) ?: null,
                'category' => $request->query('category') ? (string) $request->query('category') : null,
            ],
            $perPage,
            max(1, (int) $request->query('page', 1)),
        );
        $posts->withPath($request->url())->appends($request->except('page'));

        return BlogPostResource::collection($posts)->additional(['success' => true]);
    }

    public function show(Request $request): BlogPostDetailResource|JsonResponse
    {
        $post = $this->posts->findPublishedBySlug((string) $request->route('slug'));

        if (! $post) {
            return $this->notFound();
        }

        return (new BlogPostDetailResource($post))->additional(['success' => true]);
    }

    public function comments(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $post = $this->posts->findPublishedBySlug((string) $request->route('slug'));

        if (! $post) {
            return $this->notFound();
        }

        return BlogCommentResource::collection($this->comments->getApprovedForPost($post->id))->additional(['success' => true]);
    }

    public function categories(): AnonymousResourceCollection
    {
        return BlogCategoryResource::collection($this->categories->getActiveCategoriesWithPostCounts())->additional(['success' => true]);
    }

    protected function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => trans('core::core.messages.route_not_found')], 404);
    }
}
