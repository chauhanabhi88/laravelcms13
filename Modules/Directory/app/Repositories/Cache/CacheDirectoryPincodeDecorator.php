<?php

namespace Modules\Directory\Repositories\Cache;

use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Directory\Repositories\DirectoryPincodeRepository;

class CacheDirectoryPincodeDecorator extends BaseCacheDecorator implements DirectoryPincodeRepository
{
    public function __construct(DirectoryPincodeRepository $pincode)
    {
        parent::__construct();
        $this->entityName = config('directory.cache.pincode');
        $this->repository = $pincode;
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
        // Not cached, for the reasons given on BaseCacheDecorator::paginate().
        return $this->repository->pagination($request);
    }

    public function findByPincode(string $pincode): ?DirectoryPincode
    {
        return $this->remember(fn () => $this->repository->findByPincode($pincode));
    }

    public function updateFlags(string $pincode, array $flags): int
    {
        $this->flushCacheFor($this->entityName);

        return $this->repository->updateFlags($pincode, $flags);
    }

    public function getStateOptions(bool $withPlaceholder = false): array
    {
        return $this->remember(fn () => $this->repository->getStateOptions($withPlaceholder));
    }

    public function getZoneOptions(bool $withPlaceholder = false): array
    {
        return $this->repository->getZoneOptions($withPlaceholder);
    }
}
