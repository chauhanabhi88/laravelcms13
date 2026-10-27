<?php

namespace Modules\Seller\Repositories\Cache;

use Illuminate\Support\Collection;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Repositories\SellerBankAccountRepository;

/**
 * Pass-through: verification status changes asynchronously (webhook), so reads stay live.
 */
class CacheSellerBankAccountDecorator extends BaseCacheDecorator implements SellerBankAccountRepository
{
    public function __construct(SellerBankAccountRepository $bankAccount)
    {
        parent::__construct();
        $this->entityName = config('seller.cache.bank_account');
        $this->repository = $bankAccount;
    }

    public function forSeller(int $sellerId): Collection
    {
        return $this->repository->forSeller($sellerId);
    }

    public function primaryForSeller(int $sellerId): ?SellerBankAccount
    {
        return $this->repository->primaryForSeller($sellerId);
    }
}
