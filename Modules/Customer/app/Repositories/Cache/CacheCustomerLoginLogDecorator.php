<?php

namespace Modules\Customer\Repositories\Cache;

use Modules\Customer\Repositories\CustomerLoginLogRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheCustomerLoginLogDecorator extends BaseCacheDecorator implements CustomerLoginLogRepository
{
    public function __construct(CustomerLoginLogRepository $customerLoginLog)
    {
        parent::__construct();
        $this->entityName = config("customer.cache.customer_login_log");
        $this->repository = $customerLoginLog;
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
        return $this->repository->pagination($request);
    }

    public function filter($request)
    {
        return $this->repository->filter($request);
    }

    public function export($request)
    {
        return $this->repository->export($request);
    }
}