<?php

namespace Modules\LaravelPWA\Repositories\Cache;

use Modules\LaravelPWA\Repositories\LaravelPWARepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheLaravelPWADecorator extends BaseCacheDecorator implements LaravelPWARepository
{
    public function __construct(LaravelPWARepository $laravelpwa)
    {
        parent::__construct();
        $this->entityName = \config("laravelpwa.name");
        $this->repository = $laravelpwa;
    }

    public function sortColumns()
    {
        return $this->remember(function () {
            return $this->repository->sortColumns();
        });
    }

    public function getFilters($request)
    {
        return $this->remember(function () use ($request) {
            return $this->repository->getFilters($request);
        });
    }

    public function pagination($request)
    {
        return $this->remember(function() use ($request) {
            return $this->repository->pagination($request);
        });
    }
}
