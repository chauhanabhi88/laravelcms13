<?php

namespace Modules\User\Repositories\Cache;

use Modules\User\Repositories\DeletedUserRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheDeletedUserDecorator extends BaseCacheDecorator implements DeletedUserRepository
{
    public function __construct(DeletedUserRepository $deleted_user)
    {
        parent::__construct();
        $this->entityName = \config("user.cache.deleted_user_name");
        $this->repository = $deleted_user;
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

}
