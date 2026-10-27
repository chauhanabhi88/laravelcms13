<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Seller;

use Illuminate\View\View;

/**
 * Supplier dashboard shell. KPI tiles show placeholders until orders (M14), payouts (M17)
 * and ratings (M16) exist; the notice panel reflects the seller's status.
 */
class DashboardController extends SellerPanelController
{
    public function index(): View
    {
        $seller = $this->seller()->loadMissing('primaryBankAccount', 'defaultWarehouse');

        return view('seller::seller.dashboard.index', [
            'seller' => $seller,
            'sellerStoreName' => $seller->displayName(),
            'user' => $this->sellerUser(),
            'bankAccount' => $seller->primaryBankAccount,
            'warehouse' => $seller->defaultWarehouse,
        ]);
    }
}
