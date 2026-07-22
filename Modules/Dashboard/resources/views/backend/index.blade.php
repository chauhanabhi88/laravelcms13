@extends('theme::layouts.backend.master')
@section('title')
{{ trans("dashboard::dashboard.titles.head_title") }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
  <div class="page-header col-sm-12 d-flex pb-4 pt-2">
    <div class="col-sm-6">
      <h4 class="page-title">{{ trans("dashboard::dashboard.titles.dashboard") }}</h4>
      <p class="dash-subtitle mb-0">{{ trans("dashboard::dashboard.messages.welcome_user", ["name" => auth()->user()->name ?? '']) }}</p>
    </div>
    <div class="col-sm-6 btn-right">
      <div class="float-right">
        @can('admin.dashboard.clear_all_cache')
        <a href="{{ route('admin.dashboard.clear_all_cache', updateUrlParams()) }}" class="btn btn-outline-secondary btn-fw">
          <i class="mdi mdi-broom"></i> {{ trans('dashboard::dashboard.labels.clear_all_cache') }}
        </a>
        @endcan
      </div>
    </div>
  </div>
</div>
@stop

@section('content')

@include('dashboard::backend.partials.stat_cards')

<div class="row">
  <div class="col-lg-8 grid-margin stretch-card">
    <div class="card dash-card">
      <div class="card-body">
        <h4 class="card-title">{{ trans("dashboard::dashboard.titles.growth_overview") }}</h4>
        <p class="card-description">{{ trans("dashboard::dashboard.messages.growth_overview_hint") }}</p>
        <div class="dash-chart-wrapper">
          <canvas id="dashboardTrendChart"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4 grid-margin stretch-card">
    <div class="card dash-card">
      <div class="card-body">
        <h4 class="card-title">{{ trans("dashboard::dashboard.titles.content_status") }}</h4>
        <p class="card-description">{{ trans("dashboard::dashboard.messages.content_status_hint") }}</p>
        @if(($contentStatus['total'] ?? 0) > 0)
        <div class="dash-chart-wrapper dash-chart-wrapper--doughnut">
          <canvas id="dashboardStatusChart"></canvas>
        </div>
        <ul class="dash-legend">
          @foreach($contentStatus['labels'] as $index => $label)
          <li>
            <span class="dash-legend__dot" style="background-color: {{ $contentStatus['colors'][$index] }}"></span>
            <span class="dash-legend__label">{{ $label }}</span>
            <span class="dash-legend__value">{{ number_format($contentStatus['data'][$index]) }}</span>
          </li>
          @endforeach
        </ul>
        @else
        <p class="dash-empty">{{ trans("dashboard::dashboard.messages.no_records") }}</p>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-6 grid-margin stretch-card">
    @include('dashboard::backend.partials.recent_contacts')
  </div>
  <div class="col-lg-6 grid-margin stretch-card">
    @include('dashboard::backend.partials.recent_customers')
  </div>
</div>

<script type="application/json" id="dashboard-chart-data">
  @json(['trend' => $monthlyTrend, 'status' => $contentStatus])
</script>
@stop
