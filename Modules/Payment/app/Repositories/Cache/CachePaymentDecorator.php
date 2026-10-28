<?php

namespace Modules\Payment\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Payment\Repositories\PaymentRepository;

/**
 * Payments change through PaymentService (callbacks, webhooks, the expiry sweep), not this
 * repository, so nothing here is cached: every read goes to the database.
 */
class CachePaymentDecorator extends BaseCacheDecorator implements PaymentRepository
{
    public function __construct(PaymentRepository $payment)
    {
        parent::__construct();
        $this->entityName = config('payment.cache.payment');
        $this->repository = $payment;
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
