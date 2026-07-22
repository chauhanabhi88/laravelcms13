<?php

namespace Modules\Blog\Repositories\Cache;

use Modules\Blog\Repositories\BlogCategoryRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheBlogCategoryDecorator extends BaseCacheDecorator implements BlogCategoryRepository
{
    public function __construct(BlogCategoryRepository $blog_category)
    {
        parent::__construct();
        $this->entityName = \config("blog.cache.blog_category_name");
        $this->repository = $blog_category;
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

    public function getAllBlogCategory($flag = false) {
        return $this->remember(function() use ($flag) {
            return $this->repository->getAllBlogCategory($flag);
        });
    }
}
