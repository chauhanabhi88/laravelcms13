<?php

declare(strict_types=1);

namespace Modules\Order\Composers;

use Illuminate\Contracts\View\View;
use Modules\Order\Repositories\SellerOrderDeskRepository;
use Modules\Seller\Support\CurrentSeller;
use Throwable;

/**
 * Real order KPIs for the supplier dashboard (M03 placeholders, plan M14 §6) and the quality
 * card: $orderKpis on seller::seller.dashboard.index, $quality on the quality widget.
 */
class SellerDashboardComposer
{
    public function __construct(
        private readonly SellerOrderDeskRepository $desk,
        private readonly CurrentSeller $current,
    ) {}

    public function compose(View $view): void
    {
        $seller = $this->current->seller();

        if ($seller === null) {
            return;
        }

        try {
            if ($view->name() === 'order::seller.dashboard.quality') {
                $view->with('quality', $this->desk->qualitySummary($seller->id));

                return;
            }

            $view->with('orderKpis', $this->desk->dashboardCounts($seller->id));
        } catch (Throwable $e) {
            // The dashboard keeps its placeholders rather than failing.
            report($e);
        }
    }
}
