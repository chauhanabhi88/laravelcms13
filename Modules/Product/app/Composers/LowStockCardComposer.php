<?php

declare(strict_types=1);

namespace Modules\Product\Composers;

use Illuminate\Contracts\View\View;
use Modules\Product\Repositories\SellerCatalogRepository;
use Modules\Seller\Support\CurrentSeller;

/**
 * Supplier dashboard card "Low stock: n SKUs" (FR-SCT-007), linking to the inventory filter.
 */
class LowStockCardComposer
{
    public function __construct(
        protected SellerCatalogRepository $catalog,
        protected CurrentSeller $current,
    ) {}

    public function compose(View $view): void
    {
        $user = $this->current->user();

        $view->with([
            'lowStockCount' => $user !== null ? $this->catalog->lowStockCount($user->seller_id) : 0,
            'lowStockVisible' => $user !== null && $user->canAccessArea('catalog'),
        ]);
    }
}
