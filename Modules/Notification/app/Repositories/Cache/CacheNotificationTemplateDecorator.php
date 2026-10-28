<?php

namespace Modules\Notification\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Notification\Repositories\NotificationTemplateRepository;

class CacheNotificationTemplateDecorator extends BaseCacheDecorator implements NotificationTemplateRepository
{
    public function __construct(NotificationTemplateRepository $notificationTemplate)
    {
        parent::__construct();
        $this->entityName = config('notification.cache.notification_template');
        $this->repository = $notificationTemplate;
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
