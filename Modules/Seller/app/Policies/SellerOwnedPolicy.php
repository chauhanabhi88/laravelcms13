<?php

declare(strict_types=1);

namespace Modules\Seller\Policies;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Modules\Seller\Models\SellerUser;

/**
 * One policy for every model with a seller_id: a supplier panel user manages only their
 * own seller's records. Another seller's record answers 404, so ids are not confirmed.
 *
 * Registered per model in SellerServiceProvider; later modules register their seller-owned
 * models (products, orders…) the same way. Seller controllers call
 * $this->authorize('manage', $model) on the "seller" guard.
 */
class SellerOwnedPolicy
{
    public function manage(SellerUser $user, Model $model): Response
    {
        return (int) $model->getAttribute('seller_id') === $user->seller_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
