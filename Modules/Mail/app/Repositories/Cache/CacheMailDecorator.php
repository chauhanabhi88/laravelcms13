<?php

namespace Modules\Mail\Repositories\Cache;

use Modules\Mail\Repositories\MailRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheMailDecorator extends BaseCacheDecorator implements MailRepository
{
    public function __construct(MailRepository $mail)
    {
        parent::__construct();
        $this->entityName = \Config::get("mail.name");
        $this->repository = $mail;
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
}
