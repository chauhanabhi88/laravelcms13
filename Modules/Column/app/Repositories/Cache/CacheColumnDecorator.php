<?php

namespace Modules\Column\Repositories\Cache;

use Modules\Column\Repositories\ColumnRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheColumnDecorator extends BaseCacheDecorator implements ColumnRepository
{
    public function __construct(ColumnRepository $column)
    {
        parent::__construct();
        $this->entityName = \config('column.cache.name');
        $this->repository = $column;
    }

    public function sortColumns()
    {
        return $this->repository->sortColumns();

    }

    public function getFilters($request, $yesNoOptions, $menuOptions)
    {
        return $this->repository->getFilters($request, $yesNoOptions, $menuOptions);
    }

    public function pagination($request)
    {
        return $this->remember(function () use ($request) {
            return $this->repository->pagination($request);
        });
    }

    public function getMenuOptions()
    {
        return $this->remember(function () {
            return $this->repository->getMenuOptions();
        });
    }
}
