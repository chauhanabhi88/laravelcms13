<?php

namespace Modules\Role\Repositories\Cache;

use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Role\Repositories\RoleRepository;

class CacheRoleDecorator extends BaseCacheDecorator implements RoleRepository
{
    public function __construct(RoleRepository $role)
    {
        parent::__construct();
        $this->entityName = \Config::get('role.name');
        $this->repository = $role;
    }

    public function getRoleOptions($flag = false)
    {
        return $this->repository->getRoleOptions($flag);
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
        // Not cached: a per-user, per-filter grid result is written far more
        // often than it is read (see BaseCacheDecorator::paginate()).
        return $this->repository->pagination($request);
    }

    public function filter($request)
    {
        // Not cached: returns an unexecuted Builder, which cannot be
        // serialised (see BaseCacheDecorator::allWithBuilder()).
        return $this->repository->filter($request);
    }

    public function destroyMultiple($request, $removeFile = false, $notDeleteIds = [])
    {
        $this->flushCacheFor($this->entityName);

        return $this->repository->destroyMultiple($request, $removeFile, $notDeleteIds);
    }

    public function getModulePermissions()
    {
        return $this->remember(function () {
            return $this->repository->getModulePermissions();
        });
    }
}
