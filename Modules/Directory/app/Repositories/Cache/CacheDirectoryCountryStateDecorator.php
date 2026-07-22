<?php

namespace Modules\Directory\Repositories\Cache;

use Modules\Directory\Repositories\DirectoryCountryStateRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheDirectoryCountryStateDecorator extends BaseCacheDecorator implements DirectoryCountryStateRepository
{
    public function __construct(DirectoryCountryStateRepository $directory)
    {
        parent::__construct();
        $this->entityName = \config("directory.cache.state");
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

    public function getStateOptions($flag = false, $countryCode = [])
    {
        return $this->remember(function () use ($flag, $countryCode) {
            return $this->repository->getStateOptions($flag, $countryCode);
        });
    }

    // public function getAllCountryCity($flag = false)
    // {
    //     return $this->remember(function () use ($flag) {
    //         return $this->repository->getAllCountryCity($flag = false);
    //     });
    // }

    // public function getCountryCities($country,$flag = false)
    // {
    //     return $this->remember(function () use ($country,$flag) {
    //         return $this->repository->getCountryCities($country,$flag = false);
    //     });
    // }
}
