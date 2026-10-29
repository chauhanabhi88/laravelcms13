<?php

declare(strict_types=1);

namespace Modules\Promotion\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Promotion\Services\PromotionReportService;

/**
 * Promotion report (plan M18 §6): per coupon / offer the orders, usage, discount cost, revenue
 * and AOV in a date range; the AOV of orders with a promotion against those without (uplift);
 * flash-deal units and revenue.
 */
class PromotionReportController extends BackendController
{
    public function __construct(private readonly PromotionReportService $reports)
    {
        parent::__construct();
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : Carbon::today()->endOfDay();
        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : $to->copy()->subDays(29)->startOfDay();

        return view('promotion::backend.report.index', [
            'from' => $from,
            'to' => $to,
            'coupons' => $this->reports->coupons($from, $to),
            'uplift' => $this->reports->uplift($from, $to),
            'deals' => $this->reports->flashDeals($from, $to),
        ]);
    }
}
