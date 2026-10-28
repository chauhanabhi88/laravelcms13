<?php

namespace Modules\Payment\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Payment\Models\PaymentReconItem;
use Modules\Payment\Repositories\PaymentReconRepository;

/**
 * Reconciliation rows are written by the daily job, so nothing here is cached.
 */
class CachePaymentReconDecorator extends BaseCacheDecorator implements PaymentReconRepository
{
    public function __construct(PaymentReconRepository $recon)
    {
        parent::__construct();
        $this->entityName = config('payment.cache.payment_recon');
        $this->repository = $recon;
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

    public function resolve(PaymentReconItem $item, int $adminId, ?string $note): PaymentReconItem
    {
        $this->flushCacheFor($this->entityName);

        return $this->repository->resolve($item, $adminId, $note);
    }
}
