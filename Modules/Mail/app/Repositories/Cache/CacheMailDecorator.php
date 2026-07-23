<?php

namespace Modules\Mail\Repositories\Cache;

use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Mail\Repositories\MailRepository;

class CacheMailDecorator extends BaseCacheDecorator implements MailRepository
{
    public function __construct(MailRepository $mail)
    {
        parent::__construct();
        $this->entityName = \Config::get('mail.name');
        $this->repository = $mail;
    }

    public function getFilters($request, $statusOptions)
    {
        return $this->repository->getFilters($request, $statusOptions);
    }

    public function pagination($request)
    {
        return $this->remember(function () use ($request) {
            return $this->repository->pagination($request);
        });
    }

    public function filter($request)
    {
        return $this->remember(function () use ($request) {
            return $this->repository->filter($request);
        });
    }
}
