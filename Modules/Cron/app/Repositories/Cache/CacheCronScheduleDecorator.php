<?php

namespace Modules\Cron\Repositories\Cache;

use Illuminate\Http\Request;
use Modules\Cron\Repositories\CronScheduleRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheCronScheduleDecorator extends BaseCacheDecorator implements CronScheduleRepository
{
    public function __construct(CronScheduleRepository $cronSchedule)
    {
        parent::__construct();
        $this->entityName = \Config::get("cron.cache.entity_corn_schedule");
        $this->repository = $cronSchedule;
    }

    public function sortColumns($request,$isMassDelete = true)
    {
        return $this->repository->sortColumns($request,$isMassDelete);
    }

    public function getFilters($request, $sessionKey = null)
    {
        return $this->repository->getFilters($request, $sessionKey = null);
    }

    public function pagination(Request $request, $sessionKey = null)
    {
        return $this->remember(function() use ($request, $sessionKey) {
            return $this->repository->pagination($request, $sessionKey);
        });
    }

    public function filter(Request $request, $sessionKey = null)
    {
        return $this->remember(function() use ($request, $sessionKey) {
            return $this->repository->filter($request, $sessionKey);
        });
    }

}
