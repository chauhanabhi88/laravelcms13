<?php

namespace Modules\Mail\Repositories\Cache;

use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Mail\Repositories\MailLogRepository;

class CacheMailLogDecorator extends BaseCacheDecorator implements MailLogRepository
{
    public function __construct(MailLogRepository $mailLog)
    {
        parent::__construct();
        $this->entityName = config('mail.cache.mail_log');
        $this->repository = $mailLog;
    }

    public function getFilters($request)
    {
        return $this->repository->getFilters($request);
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
