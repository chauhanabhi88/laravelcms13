<?php

namespace Modules\Cron\Repositories\Cache;

use Illuminate\Http\Request;
use Modules\Cron\Repositories\CronRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheCronDecorator extends BaseCacheDecorator implements CronRepository
{
    public function __construct(CronRepository $cron)
    {
        parent::__construct();
        $this->entityName = \Config::get("cron.cache.name");
        $this->repository = $cron;
    }

    public function sortColumns($request)
    {
        return $this->repository->sortColumns($request); 
    }

    public function getFilters($request)
    {
        return $this->repository->getFilters($request);
    }

    public function pagination(Request $request)
    {
        return $this->remember(function() use ($request) {
            return $this->repository->pagination($request);
        });
    }

    public function filter(Request $request)
    {
        return $this->remember(function() use ($request) {
            return $this->repository->filter($request);
        });
    }
}