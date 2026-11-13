<?php

namespace Modules\Banner\Repositories\Cache;

use Modules\Banner\Repositories\BannerGroupRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheBannerGroupDecorator extends BaseCacheDecorator implements BannerGroupRepository
{
    public function __construct(BannerGroupRepository $bannerGroup)
    {
        parent::__construct();
        $this->entityName = config('banner.cache.banner_group_name');
        $this->repository = $bannerGroup;
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
        // Paginators are not on Core's CacheClassPolicy list and admin grids must be live.
        return $this->repository->pagination($request);
    }

    public function filter($request)
    {
        // Returns an unresolved Builder - never cached (see BaseCacheDecorator::allWithBuilder()).
        return $this->repository->filter($request);
    }

    /**
     * Get Banners By Group
     *
     * @param  $code  Banner Group Code
     * @param  $locale
     *                 return $slides
     */
    public function getBannersByGroup($code, $locale)
    {
        return $this->remember(function () use ($code, $locale) {
            return $this->repository->getBannersByGroup($code, $locale);
        });
    }

    public function getBannerGroups($flag = false)
    {
        return $this->remember(function () use ($flag) {
            return $this->repository->getBannerGroups($flag);
        });
    }
}
