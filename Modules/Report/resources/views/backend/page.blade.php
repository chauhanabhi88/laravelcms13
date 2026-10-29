{{-- A report page (plan M19 §7): tabs, filter bar, KPI tiles, charts (each with a table view) and tables, with export. --}}
@extends('theme::layouts.backend.master')

@section('title')
{{ trans('report::report.reports.'.$report) }} · {{ trans('report::report.title') }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-7">
            <h4 class="page-title">{{ trans('report::report.reports.'.$report) }}</h4>
            <p class="rpt-muted mb-0">{{ trans('report::report.intro.'.$report) }}</p>
        </div>
        <div class="col-sm-5 btn-right">
            @if ($canExport)
                <div class="float-right rpt-export" data-report-export>
                    <span class="rpt-muted mr-2">{{ trans('report::report.export.label') }}</span>
                    @foreach (config('report.exports.formats', ['csv']) as $format)
                        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.report.export_start', updateUrlParams([[...$query, 'report' => $report, 'format' => $format]])) }}">
                            <i class="mdi mdi-download" aria-hidden="true"></i> {{ trans('report::report.export.'.$format) }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@stop

@section('content')
<div class="rpt-root">
    @include('report::backend.partials.tabs')
    @include('report::backend.partials.filters')

    @foreach ($warnings as $warning)
        <div class="alert alert-warning" role="status" data-report-warning="{{ $warning }}">{{ trans('report::report.warnings.'.$warning, ['days' => config('report.max_range_days')]) }}</div>
    @endforeach
    @if (! empty($notice))
        <div class="alert alert-info" role="status">{{ trans('report::report.notices.'.$notice) }}</div>
    @endif

    <p class="rpt-period" data-report-period>
        <strong>{{ $range->label() }}</strong>
        @if ($range->compare())
            <span class="rpt-muted">· {{ trans('report::report.compare_modes.'.$range->compareMode) }}: {{ $range->compare()->label() }}</span>
        @endif
    </p>

    @if (! empty($tiles))
        @include('report::backend.partials.tiles', ['tiles' => $tiles])
    @endif

    @if (! empty($sortOptions))
        <div class="rpt-sorts" role="group" aria-label="{{ trans('report::report.sorts.label') }}">
            <span class="rpt-muted mr-2">{{ trans('report::report.sorts.label') }}</span>
            @foreach ($sortOptions as $option)
                <a href="{{ route('admin.report.'.$report, updateUrlParams([[...$query, 'sort' => $option]])) }}"
                   class="btn btn-sm {{ $sort === $option ? 'btn-primary' : 'btn-outline-secondary' }}"
                   @if ($sort === $option) aria-current="true" @endif>{{ trans('report::report.sorts.'.$option) }}</a>
            @endforeach
        </div>
    @endif

    @if (! empty($charts))
        <div class="row">
            @foreach ($charts as $item)
                <div class="{{ ! empty($item['wide']) ? 'col-12' : 'col-lg-6' }} grid-margin stretch-card">
                    @include('report::backend.partials.chart', ['chart' => $item['chart'], 'title' => $item['title']])
                </div>
            @endforeach
        </div>
    @endif

    @foreach ($tables ?? [] as $item)
        <div class="card rpt-card grid-margin" data-report-table="{{ $item['title'] }}">
            <div class="card-body">
                <div class="rpt-card__head">
                    <h4 class="card-title mb-0">{{ trans('report::report.tables.'.$item['title']) }}</h4>
                    @if (! empty($item['link']) && \Illuminate\Support\Facades\Gate::allows($item['link']))
                        <a class="rpt-link" href="{{ route($item['link'], updateUrlParams([$range->toQuery()])) }}">{{ trans('report::report.tables.view_all') }}</a>
                    @endif
                </div>
                @include('report::backend.partials.table', ['table' => $item['table']])
            </div>
        </div>
    @endforeach
</div>
@stop
