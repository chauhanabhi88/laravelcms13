<?php

namespace Modules\Directory\Repositories\Cache;

use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Directory\Repositories\DirectoryCountryRepository;

class CacheDirectoryCountryDecorator extends BaseCacheDecorator implements DirectoryCountryRepository
{
    public function __construct(DirectoryCountryRepository $directory)
    {
        parent::__construct();
        $this->entityName = \config('directory.cache.country');
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

    public function getCountryOptions($flag = false, $active = false)
    {
        return $this->remember(function () use ($flag, $active) {
            return $this->repository->getCountryOptions($flag, $active);
        });
    }

    public function getAllowedCountries($flag = false, $backend = false)
    {
        return $this->remember(function () use ($flag, $backend) {
            return $this->repository->getAllowedCountries($flag, $backend);
        });
    }

    public function getAllowedCountriesCode()
    {
        return $this->remember(function () {
            return $this->repository->getAllowedCountriesCode();
        });
    }

    public function getCountryList()
    {
        return $this->remember(function () {
            return $this->repository->getCountryList();
        });
    }

    public function getCountryNameByCode($code, $display)
    {
        return $this->remember(function () use ($code, $display) {
            return $this->repository->getCountryNameByCode($code, $display);
        });
    }
}
