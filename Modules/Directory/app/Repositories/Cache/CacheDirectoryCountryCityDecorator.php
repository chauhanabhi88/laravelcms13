<?php

namespace Modules\Directory\Repositories\Cache;

use Modules\Directory\Repositories\DirectoryCountryCityRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheDirectoryCountryCityDecorator extends BaseCacheDecorator implements DirectoryCountryCityRepository
{
    public function __construct(DirectoryCountryCityRepository $directory)
    {
        parent::__construct();
        $this->entityName = \config("directory.cache.city");
        $this->repository = $directory;
    }

    public function export($request)
    {
        return $this->remember(function () use ($request) {
            return $this->repository->export($request);
        });
    }

    public function getSampleImportData()
    {
        return $this->repository->getSampleImportData();
    }


    public function getAllCountryCity($flag = false)
    {
        return $this->remember(function () use ($flag) {
            return $this->repository->getAllCountryCity($flag = false);
        });
    }

    public function getCountryCities($country, $flag = false)
    {
        return $this->remember(function () use ($country, $flag) {
            return $this->repository->getCountryCities($country, $flag = false);
        });
    }

    public function getCityNameById($id)
    {
        return $this->remember(function () use ($id) {
            return $this->repository->getCityNameById($id);
        });
    }
}
