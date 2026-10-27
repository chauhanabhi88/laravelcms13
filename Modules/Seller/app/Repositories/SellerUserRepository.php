<?php

namespace Modules\Seller\Repositories;

use Illuminate\Support\Collection;
use Modules\Core\Repositories\BaseRepository;
use Modules\Seller\Models\SellerUser;

interface SellerUserRepository extends BaseRepository
{
    /**
     * A seller's logins, owners first.
     *
     * @return Collection<int, SellerUser>
     */
    public function forSeller(int $sellerId): Collection;
}
