<?php

namespace Modules\Dashboard\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Blog\Models\BlogPost;
use Modules\Contact\Models\Contact;
use Modules\Customer\Models\Customer;
use Modules\Pages\Models\Pages;
use Modules\User\Models\User;
use Nwidart\Modules\Facades\Module;

/**
 * Collects every number the backend dashboard renders.
 *
 * Each module is checked with isModuleEnabled() before it is queried so a
 * disabled module simply drops its widget instead of breaking the page.
 */
class DashboardStatsService
{
    /**
     * Minutes the aggregated counters / chart series are kept in cache.
     */
    protected $cacheMinutes = 5;

    /**
     * Number of months plotted on the trend chart.
     */
    protected $trendMonths = 12;

    /**
     * Counter tiles rendered on the top row.
     *
     * @return array
     */
    public function getStatCards()
    {
        return Cache::remember('dashboard.stat_cards', now()->addMinutes($this->cacheMinutes), function () {
            $cards = [];

            if ($this->isModuleEnabled('Customer')) {
                $cards[] = [
                    'key' => 'customers',
                    'label' => trans('dashboard::dashboard.labels.total_customers'),
                    'count' => Customer::whereNull('deleted_at')->count(),
                    'today' => Customer::whereDate('created_at', today())->whereNull('deleted_at')->count(),
                    'icon' => 'mdi mdi-account-multiple',
                    'color' => 'primary',
                    'route' => 'admin.customer.index',
                ];
            }

            if ($this->isModuleEnabled('Contact')) {
                $cards[] = [
                    'key' => 'contacts',
                    'label' => trans('dashboard::dashboard.labels.total_enquiries'),
                    'count' => Contact::count(),
                    'today' => Contact::whereDate('created_at', today())->count(),
                    'icon' => 'mdi mdi-email-outline',
                    'color' => 'success',
                    'route' => 'admin.contact.index',
                ];
            }

            if ($this->isModuleEnabled('Blog')) {
                $cards[] = [
                    'key' => 'blog_posts',
                    'label' => trans('dashboard::dashboard.labels.total_posts'),
                    'count' => BlogPost::count(),
                    'today' => BlogPost::where('status', config('core.enabled'))->count(),
                    'today_label' => trans('dashboard::dashboard.labels.published'),
                    'icon' => 'mdi mdi-file-document-box',
                    'color' => 'warning',
                    'route' => 'admin.blog_post.index',
                ];
            }

            if ($this->isModuleEnabled('Pages')) {
                $cards[] = [
                    'key' => 'pages',
                    'label' => trans('dashboard::dashboard.labels.total_pages'),
                    'count' => Pages::count(),
                    'today' => Pages::where('status', config('core.enabled'))->count(),
                    'today_label' => trans('dashboard::dashboard.labels.published'),
                    'icon' => 'mdi mdi-file-multiple',
                    'color' => 'info',
                    'route' => 'admin.page.index',
                ];
            }

            if ($this->isModuleEnabled('User')) {
                $cards[] = [
                    'key' => 'users',
                    'label' => trans('dashboard::dashboard.labels.total_admins'),
                    'count' => User::whereNull('deleted_at')->count(),
                    'today' => User::where('status', config('core.enabled'))->whereNull('deleted_at')->count(),
                    'today_label' => trans('dashboard::dashboard.labels.active'),
                    'icon' => 'mdi mdi-shield-account',
                    'color' => 'danger',
                    'route' => 'admin.user.index',
                ];
            }

            return $cards;
        });
    }

    /**
     * Customers vs. enquiries created per month for the last year.
     *
     * @return array
     */
    public function getMonthlyTrend()
    {
        return Cache::remember('dashboard.monthly_trend', now()->addMinutes($this->cacheMinutes), function () {
            $months = $this->getTrendMonths();
            $datasets = [];

            if ($this->isModuleEnabled('Customer')) {
                $datasets[] = [
                    'label' => trans('dashboard::dashboard.labels.customers'),
                    'color' => '#4b49ac',
                    'data' => $this->countPerMonth(Customer::query(), $months),
                ];
            }

            if ($this->isModuleEnabled('Contact')) {
                $datasets[] = [
                    'label' => trans('dashboard::dashboard.labels.enquiries'),
                    'color' => '#1bcfb4',
                    'data' => $this->countPerMonth(Contact::query(), $months),
                ];
            }

            return [
                'labels' => array_values(array_map(function ($month) {
                    return $month->format('M Y');
                }, $months)),
                'datasets' => $datasets,
            ];
        });
    }

    /**
     * Enabled / disabled split of the publishable content, for the doughnut.
     *
     * @return array
     */
    public function getContentStatus()
    {
        return Cache::remember('dashboard.content_status', now()->addMinutes($this->cacheMinutes), function () {
            $enabled = 0;
            $disabled = 0;

            if ($this->isModuleEnabled('Blog')) {
                $enabled += BlogPost::where('status', config('core.enabled'))->count();
                $disabled += BlogPost::where('status', config('core.disabled'))->count();
            }

            if ($this->isModuleEnabled('Pages')) {
                $enabled += Pages::where('status', config('core.enabled'))->count();
                $disabled += Pages::where('status', config('core.disabled'))->count();
            }

            return [
                'labels' => [
                    trans('core::core.options.status.enable'),
                    trans('core::core.options.status.disable'),
                ],
                'data' => [$enabled, $disabled],
                'colors' => ['#1bcfb4', '#fe7c96'],
                'total' => $enabled + $disabled,
            ];
        });
    }

    /**
     * Latest contact enquiries for the activity table.
     *
     * @param  int  $limit
     * @return Collection
     */
    public function getRecentContacts($limit = 5)
    {
        if (! $this->isModuleEnabled('Contact')) {
            return collect();
        }

        return Contact::orderBy('created_at', 'desc')->limit($limit)->get();
    }

    /**
     * Latest registered customers for the activity table.
     *
     * @param  int  $limit
     * @return Collection
     */
    public function getRecentCustomers($limit = 5)
    {
        if (! $this->isModuleEnabled('Customer')) {
            return collect();
        }

        return Customer::orderBy('created_at', 'desc')->limit($limit)->get();
    }

    /**
     * Drop every cached dashboard figure (called after "clear cache").
     *
     * @return void
     */
    public function forget()
    {
        foreach (['stat_cards', 'monthly_trend', 'content_status'] as $key) {
            Cache::forget('dashboard.'.$key);
        }
    }

    /**
     * One Carbon instance per month on the trend axis, oldest first.
     *
     * @return array
     */
    protected function getTrendMonths()
    {
        $months = [];
        $cursor = Carbon::now()->startOfMonth()->subMonths($this->trendMonths - 1);

        for ($i = 0; $i < $this->trendMonths; $i++) {
            $months[$cursor->format('Y-m')] = $cursor->copy();
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * Group a query by created_at month and align it with $months.
     *
     * @param  Builder  $query
     * @return array
     */
    protected function countPerMonth($query, array $months)
    {
        $first = reset($months);

        $rows = $query->where('created_at', '>=', $first)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as aggregate")
            ->groupBy('period')
            ->pluck('aggregate', 'period');

        $series = [];
        foreach (array_keys($months) as $period) {
            $series[] = (int) $rows->get($period, 0);
        }

        return $series;
    }

    /**
     * @param  string  $name
     * @return bool
     */
    protected function isModuleEnabled($name)
    {
        $module = Module::find($name);

        return $module && $module->isEnabled();
    }
}
