<?php

namespace Modules\Customer\Repositories\Cache;

use Modules\Customer\Repositories\CustomerGroupRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheCustomerGroupDecorator extends BaseCacheDecorator implements CustomerGroupRepository
{
    public function __construct(CustomerGroupRepository $customerGroup)
    {
        parent::__construct();
        $this->entityName = config("customer.cache.customer_group_name");
        $this->repository = $customerGroup;
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
        return $this->remember(function () use($request) {
            return $this->repository->pagination($request);
        });
    }

    public function filter($request)
    {
        return $this->remember(function () use($request) {
            return $this->repository->filter($request);
        });
    }
}
