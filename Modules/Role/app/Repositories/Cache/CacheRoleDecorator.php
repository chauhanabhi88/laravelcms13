<?php

namespace Modules\Role\Repositories\Cache;

use Modules\Role\Repositories\RoleRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheRoleDecorator extends BaseCacheDecorator implements RoleRepository
{
    public function __construct(RoleRepository $role)
    {
        parent::__construct();
        $this->entityName = \Config::get("role.name");
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
        return $this->remember(function () use ($request) {
            return $this->repository->pagination($request);
        });
    }

    public function filter($request)
    {
        return $this->remember(function () use ($request) {
            return $this->repository->filter($request);
        });
    }

    public function getModulePermissions()
    {
        return $this->remember(function () {
            return $this->repository->getModulePermissions();
        });
    }
}
