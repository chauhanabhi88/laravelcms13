<?php

namespace Modules\ImportReport\Repositories\Cache;

use Modules\ImportReport\Repositories\ImportReportRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheImportReportDecorator extends BaseCacheDecorator implements ImportReportRepository
{
    public function __construct(ImportReportRepository $importreport)
    {
        parent::__construct();
        $this->entityName = \config("importreport.cache.name");
        $this->repository = $importreport;
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
        return $this->remember(function() use ($request) {
            return $this->repository->pagination($request);
        });
    }
}
