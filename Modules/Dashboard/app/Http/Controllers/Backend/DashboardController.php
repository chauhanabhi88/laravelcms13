<?php

namespace Modules\Dashboard\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Dashboard\Services\DashboardStatsService;

use Illuminate\Support\Facades\Crypt;

class DashboardController extends BackendController
{
    /**
     * @var DashboardStatsService
     */
    private $stats;

    public function __construct(DashboardStatsService $stats)
    {
        parent::__construct();
        $this->stats = $stats;
        $this->getAssetManager()->addAssets([
            'modules/dashboard/css/backend/dashboard.css',
            'modules/theme/backend/staradmin/vendors/chart.js/Chart.min.js',
            'modules/dashboard/js/backend/dashboard.js',
        ]);
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        try {
            $statCards = $this->stats->getStatCards();
            $monthlyTrend = $this->stats->getMonthlyTrend();
            $contentStatus = $this->stats->getContentStatus();
            $recentContacts = $this->stats->getRecentContacts();
            $recentCustomers = $this->stats->getRecentCustomers();

            return view('dashboard::backend.index', compact('statCards', 'monthlyTrend', 'contentStatus', 'recentContacts', 'recentCustomers'));
        } catch (\Throwable $e) {
            return view('dashboard::backend.index', [
                'statCards' => [],
                'monthlyTrend' => ['labels' => [], 'datasets' => []],
                'contentStatus' => ['labels' => [], 'data' => [], 'colors' => [], 'total' => 0],
                'recentContacts' => collect(),
                'recentCustomers' => collect(),
            ])->with('error', $e->getMessage());
        }
    }

    public function clearCache()
    {
        try {
            \Artisan::call('cache:clear');
            $this->stats->forget();
            return redirect()->back()->with('success', trans('dashboard::dashboard.messages.cache_clear_successfully'));
        } catch (\Throwable $e) {
            return redirect()->back()->with("error", $e->getMessage());
        }
    }
}
