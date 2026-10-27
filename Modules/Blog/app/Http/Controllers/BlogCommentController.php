<?php

namespace Modules\Blog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Blog\Http\Requests\StoreBlogCommentRequest;
use Modules\Blog\Repositories\BlogPostCommentRepository;
use Modules\Blog\Repositories\BlogPostRepository;
use Modules\Core\Http\Controllers\FrontendController;

class BlogCommentController extends FrontendController
{
    public function __construct(
        protected BlogPostRepository $posts,
        protected BlogPostCommentRepository $comments,
    ) {
        parent::__construct();
    }

    /**
     * Logged-in customers submit a comment; it stays pending until an admin approves it.
     */
    public function store(StoreBlogCommentRequest $request): JsonResponse|RedirectResponse
    {
        $post = $this->posts->findPublishedBySlug((string) $request->route('slug'));

        abort_if(! $post, 404);

        $postUrl = frontRoute('blog.show', [$post->slug]).'#comments';

        try {
            $this->comments->createPendingComment($post->id, (int) Auth::guard('customer')->id(), $request->validated());
        } catch (\Throwable $e) {
            Log::error($e);

            if ($request->expectsJson()) {
                return response()->json(['message' => trans('blog::blog_front.messages.comment_failed')], 500);
            }

            return redirect($postUrl)->withInput()->with('error', trans('blog::blog_front.messages.comment_failed'));
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => trans('blog::blog_front.messages.comment_pending')], 201);
        }

        return redirect($postUrl)->with('success', trans('blog::blog_front.messages.comment_pending'));
    }
}
