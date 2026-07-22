<?php

namespace Modules\Column\Repositories\Cache;

use Modules\Column\Repositories\ColumnRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheColumnDecorator extends BaseCacheDecorator implements ColumnRepository
{
    public function __construct(ColumnRepository $column)
    {
        parent::__construct();
        $this->entityName = \config("column.cache.name");
        $this->repository = $column;
    }

    public function sortColumns()
    {
        return $this->repository->sortColumns();

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
}
