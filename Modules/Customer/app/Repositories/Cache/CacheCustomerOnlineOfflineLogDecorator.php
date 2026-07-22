<?php

namespace Modules\Customer\Repositories\Cache;

use Modules\Customer\Repositories\CustomerOnlineOfflineLogRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheCustomerOnlineOfflineLogDecorator extends BaseCacheDecorator implements CustomerOnlineOfflineLogRepository
{
    public function __construct(CustomerOnlineOfflineLogRepository $customerActiveInactiveLog)
    {
        parent::__construct();
        $this->entityName = config("customer.cache.name");
        $this->repository = $customerActiveInactiveLog;
    }

    public function sortColumns($request)
    {
        return $this->repository->sortColumns($request);
    }

    public function getFilters($request)
    {
        return $this->repository->getFilters($request);
    }

    public function getStatusOptions($flag = false)
    {
        return $this->repository->getStatusOptions($flag);
    }


    public function pagination($request)
    {
        // return $this->remember(function() use ($request) {
        return $this->repository->pagination($request);
        // });
    }

    public function filter($request)
    {
        // return $this->remember(function () use ($request) {
            return $this->repository->filter($request);
        // });
    }
}
