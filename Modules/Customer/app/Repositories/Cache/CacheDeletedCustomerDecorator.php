<?php

namespace Modules\Customer\Repositories\Cache;

use Modules\Customer\Repositories\DeletedCustomerRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheDeletedCustomerDecorator extends BaseCacheDecorator implements DeletedCustomerRepository
{
    public function __construct(DeletedCustomerRepository $deletedCustomer)
    {
        parent::__construct();
        $this->entityName = config("customer.cache.deleted_customer_name");
        $this->repository = $deletedCustomer;
    }

    public function sortColumns($request)
    {
        return $this->repository->sortColumns($request);
        /*return $this->remember(function () {
            
        });*/
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

    public function getLoginUserInfo() 
    {
        return $this->remember(function()  {
            return $this->repository->getLoginUserInfo();
        });
    }

    public function onlyTrashedData($id) 
    {
        return $this->remember(function() use ($id)  {
            return $this->repository->onlyTrashedData($id);
        });
    }

}