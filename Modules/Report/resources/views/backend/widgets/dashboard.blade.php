{{-- Dashboard home widget (Modules\Dashboard\Contracts\DashboardWidget): marketplace KPIs of the last 30 days and the GMV trend. --}}
<div class="rpt-root" data-report-widget>
    <div class="rpt-card__head mb-3">
        <div>
            <h4 class="card-title mb-0">{{ trans('report::report.widget.title') }}</h4>
            <span class="rpt-muted small">{{ $reportRange->label() }} · {{ trans('report::report.compare_modes.previous') }}</span>
        </div>
        <a class="rpt-link" href="{{ route('admin.report.dashboard', updateUrlParams()) }}">{{ trans('report::report.widget.link') }}</a>
    </div>

    @include('report::backend.partials.tiles', ['tiles' => $reportTiles])

    <div class="row">
        <div class="col-12 grid-margin stretch-card">
            @include('report::backend.partials.chart', ['chart' => $reportGmvChart, 'title' => 'gmv'])
        </div>
    </div>
</div>
