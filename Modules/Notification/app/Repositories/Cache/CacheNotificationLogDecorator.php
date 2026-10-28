<?php

namespace Modules\Notification\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Notification\Repositories\NotificationLogRepository;

/**
 * Log rows are written by queue workers straight through the model, so nothing here is
 * cached: every read goes to the database.
 */
class CacheNotificationLogDecorator extends BaseCacheDecorator implements NotificationLogRepository
{
    public function __construct(NotificationLogRepository $notificationLog)
    {
        parent::__construct();
        $this->entityName = config('notification.cache.notification_log');
        $this->repository = $notificationLog;
    }

    public function find($id)
    {
        return $this->repository->find($id);
    }

    public function getFilters(Request $request, array $options): array
    {
        return $this->repository->getFilters($request, $options);
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->repository->pagination($request);
    }

    public function filter(Request $request): Builder
    {
        return $this->repository->filter($request);
    }
}
