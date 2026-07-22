<?php

namespace Modules\Menu\Repositories\Cache;

use Modules\Menu\Repositories\MenuRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheMenuDecorator extends BaseCacheDecorator implements MenuRepository
{
    public function __construct(MenuRepository $menu)
    {
        parent::__construct();
        $this->entityName = \config("menu.name");
        $this->repository = $menu;
    }

    public function sortColumns()
    {
        return $this->repository->sortColumns();
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
 
    public function buildMenu($menu, $statusOptions,$parentId = 0)
    {
        return $this->repository->buildMenu($menu, $statusOptions,$parentId );
    }

    public function _getChildMenu($menu, $cnt)
    {
        return $this->repository->_getChildMenu($menu, $cnt);
    }

    public function checkChildsMenu($childMenu = [], $permissions = [])
    {
        return $this->repository->checkChildsMenu($childMenu, $permissions);
    }
    public function getResources($object = null)
    {
        return $this->remember(function () use ($object) {
            return $this->repository->getResources($object);
        });
    }


    public function getMenu($roleId = null)
    {
        // return $this->remember(function () use ($roleId) {
            return $this->repository->getMenu($roleId);
        // });
    }
}
