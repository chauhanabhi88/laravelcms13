<?php

namespace Modules\Pages\Repositories\Cache;

use Modules\Pages\Repositories\PagesRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CachePagesDecorator extends BaseCacheDecorator implements PagesRepository
{
    public function __construct(PagesRepository $block)
    {
        parent::__construct();
        $this->entityName = \Config::get("pages.name");
        $this->repository = $block;
    }

    public function sortColumns($request)
    {
        return $this->repository->sortColumns($request);
    }

    public function getFilters($request, $languageOptions, $statusOptions)
    {
        return $this->repository->getFilters($request, $languageOptions, $statusOptions);
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
