<?php

namespace Modules\Blog\Repositories\Cache;

use Modules\Blog\Repositories\BlogPostRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheBlogPostDecorator extends BaseCacheDecorator implements BlogPostRepository
{
    public function __construct(BlogPostRepository $blog_post)
    {
        parent::__construct();
        $this->entityName = \config("blog.cache.blog_post_name");
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
        return $this->remember(function() use ($request) {
            return $this->repository->pagination($request);
        });
    }

    public function filter($request)
    {
        return $this->remember(function() use ($request) {
            return $this->repository->filter($request);
        });
    }

    public function getAllPostTitle($id = null, $flag = false) {
        return $this->remember(function() use ($id, $flag)  {
            return $this->repository->getAllPostTitle($id, $flag);
        });
    }
}
