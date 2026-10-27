<?php

namespace Modules\Blog\Repositories\Cache;

use Illuminate\Support\Collection;
use Modules\Blog\Models\BlogPostComment;
use Modules\Blog\Repositories\BlogPostCommentRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheBlogPostCommentDecorator extends BaseCacheDecorator implements BlogPostCommentRepository
{
    public function __construct(BlogPostCommentRepository $blog_post_comment)
    {
        parent::__construct();
        $this->entityName = \config('blog.cache.blog_post_comment_name');
        $this->repository = $blog_post_comment;
    }

    public function sortColumns($request)
    {
        return $this->repository->sortColumns($request);

    }

    public function getFilters($request, $statusOptions)
    {
        return $this->repository->getFilters($request, $statusOptions);
    }

    public function pagination($request)
    {
        return $this->remember(function () use ($request) {
            return $this->repository->pagination($request);
        });
    }

    public function filter($request)
    {
        return $this->remember(function () use ($request) {
            return $this->repository->filter($request);
        });
    }

    public function getCommentStatusOption($flag = false)
    {
        return $this->remember(function () use ($flag) {
            return $this->repository->getCommentStatusOption($flag);
        });
    }

    public function getApprovedForPost(int $postId): Collection
    {
        return $this->remember(function () use ($postId) {
            return $this->repository->getApprovedForPost($postId);
        });
    }

    public function createPendingComment(int $postId, int $customerId, array $data): BlogPostComment
    {
        $comment = $this->repository->createPendingComment($postId, $customerId, $data);
        $this->flushCacheFor($this->entityName);

        return $comment;
    }
}
