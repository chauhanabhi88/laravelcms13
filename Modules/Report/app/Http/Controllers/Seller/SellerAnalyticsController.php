<?php

declare(strict_types=1);

namespace Modules\Report\Http\Controllers\Seller;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Report\Services\KpiService;
use Modules\Report\Services\LeaderboardService;
use Modules\Report\Services\LiveInsights;
use Modules\Report\Support\Format;
use Modules\Report\Support\ReportFilters;
use Modules\Report\Support\ReportRange;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;

/**
 * Supplier analytics (FR-RPT-006): the seller's own sales trend, KPIs against the previous
 * period, top products and return reasons. Everything is read through the seller's grain, so
 * nothing of another seller can show.
 */
class SellerAnalyticsController extends SellerPanelController
{
    private const TILES = [
        ['gmv', Format::MONEY, true],
        ['orders', Format::COUNT, true],
        ['units', Format::COUNT, true],
        ['aov', Format::MONEY, true],
        ['return_rate', Format::PERCENT, false],
        ['rto_rate', Format::PERCENT, false],
        ['cancellation_rate', Format::PERCENT, false],
    ];

    public function __construct(
        private readonly KpiService $kpis,
        private readonly LeaderboardService $leaderboards,
        private readonly LiveInsights $insights,
    ) {}

    public function index(Request $request): View
    {
        $presets = (array) config('report.seller_presets', ['last_7', 'last_30', 'last_90']);
        $range = ReportRange::fromInput(['preset' => (string) $request->query('preset', 'last_30')], 'last_30', $presets);
        $sellerId = $this->seller()->id;
        $summary = $this->kpis->summary($range, ReportFilters::forSeller($sellerId), self::TILES);

        return view('report::seller.analytics', [
            'range' => $range,
            'presets' => $presets,
            'tiles' => $summary['tiles'],
            'trend' => array_column($summary['tiles'], 'sparkline', 'key')['gmv'],
            'trendDates' => array_keys($summary['daily']),
            'products' => $this->leaderboards->products($range, 'units', 10, $sellerId),
            'reasons' => $this->insights->returnReasons($range, $sellerId),
        ]);
    }
}
