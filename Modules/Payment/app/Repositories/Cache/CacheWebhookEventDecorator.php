<?php

namespace Modules\Payment\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Payment\Repositories\WebhookEventRepository;

/**
 * Webhook rows are written by the webhook controller and job, so nothing here is cached.
 */
class CacheWebhookEventDecorator extends BaseCacheDecorator implements WebhookEventRepository
{
    public function __construct(WebhookEventRepository $webhookEvent)
    {
        parent::__construct();
        $this->entityName = config('payment.cache.webhook_event');
        $this->repository = $webhookEvent;
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
