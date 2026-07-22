<?php

namespace Modules\Banner\Repositories\Cache;

use Modules\Banner\Repositories\BannerRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheBannerDecorator extends BaseCacheDecorator implements BannerRepository
{
    public function __construct(BannerRepository $banner)
    {
        parent::__construct();
        $this->entityName = config("banner.cache.name");
        $this->repository = $banner;
    }

    public function sortColumns($request)
    {
        return $this->repository->sortColumns($request);
        
    }

    public function getFilters($request, $statusOptions, $bannerGroups)
    {
        return $this->repository->getFilters($request, $statusOptions, $bannerGroups);
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

    public function getBannerByCode($code)
    {
        return $this->remember(function() use ($code) {
            return $this->repository->getBannerByCode($code);
        });
    }
}
