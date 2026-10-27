<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Support\CurrentSeller;

/**
 * Base for supplier panel controllers: the signed-in seller user / seller and the
 * tenancy check for seller-owned records.
 */
abstract class SellerPanelController extends Controller
{
    protected function current(): CurrentSeller
    {
        return app(CurrentSeller::class);
    }

    protected function sellerUser(): SellerUser
    {
        $user = $this->current()->user();
        abort_if($user === null, 401);

        return $user;
    }

    protected function seller(): Seller
    {
        $seller = $this->current()->seller();
        abort_if($seller === null, 401);

        return $seller;
    }

    /**
     * 404 unless the record belongs to the signed-in seller (SellerOwnedPolicy).
     */
    protected function authorizeOwned(Model $model): void
    {
        Gate::forUser($this->sellerUser())->authorize('manage', $model);
    }
}
