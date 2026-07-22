<?php

namespace Modules\Blog\Repositories\Cache;

use Modules\Blog\Repositories\BlogRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheBlogDecorator extends BaseCacheDecorator implements BlogRepository
{
    public function __construct(BlogRepository $blog)
    {
        parent::__construct();
        $this->entityName = \config("blog.cache.name");
        $this->repository = $blog;
    }

    public function sortColumns($request)
    {
        return $this->repository->sortColumns($request);

    }

    public function getFilters($request)
    {
        return $this->repository->getFilters($request);
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
}
