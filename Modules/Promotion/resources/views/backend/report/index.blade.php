{{-- Promotion report: coupon / offer performance, AOV uplift and flash-deal sales in a date range. --}}
@extends('theme::layouts.backend.master')

@section('title')
{{ trans('promotion::report.title') }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('promotion::report.title') }}</h4>
        </div>
    </div>
</div>
@stop

@section('content')
<form method="GET" action="{{ route('admin.promotion_report.index', updateUrlParams()) }}" class="card card-info card-outline">
    <div class="card-body form-row align-items-end">
        <div class="form-group col-md-3">
            <label for="from">{{ trans('promotion::report.filters.from') }}</label>
            <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" class="form-control">
        </div>
        <div class="form-group col-md-3">
            <label for="to">{{ trans('promotion::report.filters.to') }}</label>
            <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" class="form-control">
        </div>
        <div class="form-group col-md-3">
            <button type="submit" class="btn btn-primary btn-fw">{{ trans('promotion::report.filters.apply') }}</button>
        </div>
    </div>
</form>

<div class="row">
    <div class="col-md-3 grid-margin">
        <div class="card"><div class="card-body" data-report="with">
            <p class="text-muted mb-1">{{ trans('promotion::report.summary.with') }}</p>
            <h4>{{ trans('promotion::report.summary.orders', ['count' => $uplift['with']['orders']]) }}</h4>
            <p class="mb-0">{{ trans('promotion::report.summary.aov', ['amount' => $uplift['with']['aov']->format()]) }}</p>
        </div></div>
    </div>
    <div class="col-md-3 grid-margin">
        <div class="card"><div class="card-body" data-report="without">
            <p class="text-muted mb-1">{{ trans('promotion::report.summary.without') }}</p>
            <h4>{{ trans('promotion::report.summary.orders', ['count' => $uplift['without']['orders']]) }}</h4>
            <p class="mb-0">{{ trans('promotion::report.summary.aov', ['amount' => $uplift['without']['aov']->format()]) }}</p>
        </div></div>
    </div>
    <div class="col-md-3 grid-margin">
        <div class="card"><div class="card-body" data-report="uplift">
            <p class="text-muted mb-1">{{ trans('promotion::report.summary.uplift') }}</p>
            <h4>{{ $uplift['uplift_percent'] !== null ? ($uplift['uplift_percent'] > 0 ? '+' : '').$uplift['uplift_percent'].' %' : '—' }}</h4>
            @if ($uplift['uplift_percent'] === null)
                <p class="mb-0 text-muted">{{ trans('promotion::report.summary.uplift_none') }}</p>
            @endif
        </div></div>
    </div>
    <div class="col-md-3 grid-margin">
        <div class="card"><div class="card-body" data-report="discount">
            <p class="text-muted mb-1">{{ trans('promotion::report.summary.discount') }}</p>
            <h4>{{ $uplift['discount']->format() }}</h4>
        </div></div>
    </div>
</div>

<div class="card card-info card-outline">
    <div class="card-header"><strong>{{ trans('promotion::report.coupons.title') }}</strong></div>
    <div class="card-body table-responsive">
        @if ($coupons === [])
            <p class="text-muted mb-0">{{ trans('promotion::report.coupons.none') }}</p>
        @else
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>{{ trans('promotion::report.coupons.code') }}</th>
                        <th>{{ trans('promotion::report.coupons.name') }}</th>
                        <th>{{ trans('promotion::report.coupons.kind') }}</th>
                        <th>{{ trans('promotion::report.coupons.funded_by') }}</th>
                        <th class="text-right">{{ trans('promotion::report.coupons.orders') }}</th>
                        <th class="text-right">{{ trans('promotion::report.coupons.customers') }}</th>
                        <th class="text-right">{{ trans('promotion::report.coupons.discount') }}</th>
                        <th class="text-right">{{ trans('promotion::report.coupons.revenue') }}</th>
                        <th class="text-right">{{ trans('promotion::report.coupons.aov') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($coupons as $row)
                        <tr data-report-coupon="{{ $row['code'] }}">
                            <td><strong>{{ $row['code'] }}</strong></td>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['is_auto'] ? trans('promotion::report.coupons.auto') : trans('promotion::report.coupons.code_kind') }}</td>
                            <td>{{ \Modules\Promotion\Support\PromotionOptions::label('funding', $row['funded_by']) }}</td>
                            <td class="text-right">{{ $row['orders'] }}</td>
                            <td class="text-right">{{ $row['customers'] }}</td>
                            <td class="text-right">{{ $row['discount']->format(true) }}</td>
                            <td class="text-right">{{ $row['revenue']->format(true) }}</td>
                            <td class="text-right">{{ $row['aov']->format(true) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<div class="card card-info card-outline">
    <div class="card-header"><strong>{{ trans('promotion::report.deals.title') }}</strong></div>
    <div class="card-body table-responsive">
        @if ($deals === [])
            <p class="text-muted mb-0">{{ trans('promotion::report.deals.none') }}</p>
        @else
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>{{ trans('promotion::report.deals.name') }}</th>
                        <th>{{ trans('promotion::report.deals.window') }}</th>
                        <th class="text-right">{{ trans('promotion::report.deals.units') }}</th>
                        <th class="text-right">{{ trans('promotion::report.deals.revenue') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deals as $row)
                        <tr data-report-deal="{{ $row['deal_id'] }}">
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['starts_at'] }} – {{ $row['ends_at'] }}</td>
                            <td class="text-right">{{ $row['units'] }}</td>
                            <td class="text-right">{{ $row['revenue']->format(true) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@stop
