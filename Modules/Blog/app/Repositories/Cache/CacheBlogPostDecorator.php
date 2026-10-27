<?php

namespace Modules\Blog\Repositories\Cache;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Blog\Models\BlogPost;
use Modules\Blog\Repositories\BlogPostRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheBlogPostDecorator extends BaseCacheDecorator implements BlogPostRepository
{
    public function __construct(BlogPostRepository $blog_post)
    {
        parent::__construct();
        $this->entityName = \config('blog.cache.blog_post_name');
        $this->repository = $blog_post;
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

    public function getAllPostTitle($id = null, $flag = false)
    {
        return $this->remember(function () use ($id, $flag) {
            return $this->repository->getAllPostTitle($id, $flag);
        });
    }

    public function getPublishedPosts(array $filters, int $perPage, int $page = 1): LengthAwarePaginator
    {
        return $this->remember(function () use ($filters, $perPage, $page) {
            return $this->repository->getPublishedPosts($filters, $perPage, $page);
        });
    }

    public function findPublishedBySlug(string $slug): ?BlogPost
    {
        return $this->remember(function () use ($slug) {
            return $this->repository->findPublishedBySlug($slug);
        });
    }

    public function getFeaturedPosts(int $limit): Collection
    {
        return $this->remember(function () use ($limit) {
            return $this->repository->getFeaturedPosts($limit);
        });
    }

    public function getRelatedPosts(BlogPost $post, int $limit): Collection
    {
        return $this->remember(function () use ($post, $limit) {
            return $this->repository->getRelatedPosts($post, $limit);
        });
    }
}
