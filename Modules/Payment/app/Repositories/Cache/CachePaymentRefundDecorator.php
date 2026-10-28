<?php

namespace Modules\Payment\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Payment\Repositories\PaymentRefundRepository;

/**
 * Refunds change through RefundService and webhooks, so nothing here is cached.
 */
class CachePaymentRefundDecorator extends BaseCacheDecorator implements PaymentRefundRepository
{
    public function __construct(PaymentRefundRepository $refund)
    {
        parent::__construct();
        $this->entityName = config('payment.cache.payment_refund');
        $this->repository = $refund;
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
