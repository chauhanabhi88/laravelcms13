<?php

namespace Modules\Seller\Repositories;

use Illuminate\Support\Collection;
use Modules\Core\Repositories\BaseRepository;
use Modules\Seller\Models\SellerBankAccount;

interface SellerBankAccountRepository extends BaseRepository
{
    /**
     * A seller's bank accounts, the primary first, then newest.
     *
     * @return Collection<int, SellerBankAccount>
     */
    public function forSeller(int $sellerId): Collection;

    public function primaryForSeller(int $sellerId): ?SellerBankAccount;
}
