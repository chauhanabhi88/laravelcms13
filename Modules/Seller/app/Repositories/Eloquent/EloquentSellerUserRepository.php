<?php

namespace Modules\Seller\Repositories\Eloquent;

use Illuminate\Support\Collection;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Repositories\SellerUserRepository;

class EloquentSellerUserRepository extends EloquentBaseRepository implements SellerUserRepository
{
    public function forSeller(int $sellerId): Collection
    {
        return $this->model->newQuery()
            ->where('seller_id', $sellerId)
            ->orderByRaw('role = ? desc', [SellerUser::ROLE_OWNER])
            ->orderBy('name')
            ->get();
    }
}
