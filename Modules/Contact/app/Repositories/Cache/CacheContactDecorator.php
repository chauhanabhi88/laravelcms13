<?php

namespace Modules\Contact\Repositories\Cache;

use Modules\Contact\Repositories\ContactRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheContactDecorator extends BaseCacheDecorator implements ContactRepository
{
    public function __construct(ContactRepository $contact)
    {
        parent::__construct();
        $this->entityName = \config('contact.name');
        $this->repository = $contact;
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

    public function export($request)
    {
        return $this->repository->export($request);
    }
}
