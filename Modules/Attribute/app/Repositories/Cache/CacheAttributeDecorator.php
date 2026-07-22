<?php

namespace Modules\Attribute\Repositories\Cache;

use Modules\Attribute\Repositories\AttributeRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheAttributeDecorator extends BaseCacheDecorator implements AttributeRepository
{
    public function __construct(AttributeRepository $attribute)
    {
        parent::__construct();
        $this->entityName = config("attribute.name");
        $this->repository = $attribute;
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
        return $this->remember(function() use ($request) {
            return $this->repository->pagination($request);
        });
    }

    public function filter($request)
    {
        return $this->remember(function() use ($request) {
            return $this->repository->filter($request);
        });
    }


    public function getInputTypeOptions($flag = false)
    {
        return $this->remember(function() use ($flag) {
            return $this->repository->getInputTypeOptions($flag);
        });
    }
    public function getAttributeData($code,$flag = true){
        return $this->remember(function() use ($code) {
            return $this->repository->getAttributeData($code);
        });
    }
}
