<?php

namespace Modules\Dashboard\Http\Controllers\Backend;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Dashboard\Contracts\DashboardWidget;
use Modules\Dashboard\Services\DashboardStatsService;

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
     *
     * @return Response
     */
    public function index()
    {
        $widgets = $this->getWidgets();

        try {
            $statCards = $this->stats->getStatCards();
            $monthlyTrend = $this->stats->getMonthlyTrend();
            $contentStatus = $this->stats->getContentStatus();
            $recentContacts = $this->stats->getRecentContacts();
            $recentCustomers = $this->stats->getRecentCustomers();

            return view('dashboard::backend.index', compact('widgets', 'statCards', 'monthlyTrend', 'contentStatus', 'recentContacts', 'recentCustomers'));
        } catch (\Throwable $e) {
            return view('dashboard::backend.index', [
                'widgets' => $widgets,
                'statCards' => [],
                'monthlyTrend' => ['labels' => [], 'datasets' => []],
                'contentStatus' => ['labels' => [], 'data' => [], 'colors' => [], 'total' => 0],
                'recentContacts' => collect(),
                'recentCustomers' => collect(),
            ])->with('error', $e->getMessage());
        }
    }

    /**
     * Widgets other modules tagged as DashboardWidget that the admin may see, rendered
     * above the CMS widgets. A failing widget is reported and left out.
     *
     * @return array<int, array{view: string, data: array}>
     */
    protected function getWidgets()
    {
        $widgets = [];

        foreach (app()->tagged(DashboardWidget::TAG) as $widget) {
            try {
                if (! Gate::allows($widget->permission())) {
                    continue;
                }

                $widgets[] = ['view' => $widget->view(), 'data' => $widget->data()];
                $this->getAssetManager()->addAssets($widget->assets());
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $widgets;
    }

    public function clearCache()
    {
        try {
            \Artisan::call('cache:clear');
            $this->stats->forget();

            return redirect()->back()->with('success', trans('dashboard::dashboard.messages.cache_clear_successfully'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
