<?php

namespace Modules\Seller\Repositories\Eloquent;

use Illuminate\Support\Collection;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Repositories\SellerBankAccountRepository;

class EloquentSellerBankAccountRepository extends EloquentBaseRepository implements SellerBankAccountRepository
{
    public function forSeller(int $sellerId): Collection
    {
        return $this->model->newQuery()
            ->where('seller_id', $sellerId)
            ->orderBy('is_primary')
            ->orderByDesc('id')
            ->get();
    }

    public function primaryForSeller(int $sellerId): ?SellerBankAccount
    {
        return $this->model->newQuery()
            ->where('seller_id', $sellerId)
            ->where('is_primary', config('core.yes'))
            ->latest('id')
            ->first();
    }
}
