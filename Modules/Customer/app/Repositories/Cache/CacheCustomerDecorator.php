<?php

namespace Modules\Customer\Repositories\Cache;

use Modules\Customer\Repositories\CustomerRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheCustomerDecorator extends BaseCacheDecorator implements CustomerRepository
{
    public function __construct(CustomerRepository $customer)
    {
        parent::__construct();
        $this->entityName = config("customer.cache.name");
        $this->repository = $customer;
    }

    public function sortColumns($request)
    {

        return $this->repository->sortColumns($request);
      
    }

    public function getFilters($request, $statusOptions)
    {
        return $this->repository->getFilters($request, $statusOptions);
    }

    public function pagination($request)
    {
        return $this->repository->pagination($request);
    }

    public function filter($request)
    {
        return $this->repository->filter($request);
    }

    public function getLoginUserInfo() 
    {
        return $this->remember(function()  {
            return $this->repository->getLoginUserInfo();
        });
    }

    public function getAllCustomerName($id = null, $flag = false) {
        return $this->remember(function() use ($id, $flag)  {
            return $this->repository->getAllCustomerName($id, $flag);
        });
    }
}