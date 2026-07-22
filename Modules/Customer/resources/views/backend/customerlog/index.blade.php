@extends('theme::layouts.backend.master')

@section('title')
{{ trans("customer::customer_online_offline.titles.customer_online_logs") }}
@endsection

@section('content-header')
<!-- Content Header (Page header) -->

<div class="card">
  <div class="card-body">
    <div class="page-title-header row d-none d-sm-flex">
      <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
          <h4 class="page-title">{{ trans("customer::customer_online_offline.titles.customer_online_logs") }}</h4>
        </div>
        <div class="col-sm-6">
          <div class="float-right">
            @can('admin.customerLog.index')
            <button type="button" class="btn btn-danger btn-sm au-btn-icon" id="refresh-grid" onclick="setLocation('{{ route('admin.customerLog.index', updateUrlParams()) }}')"><i class="fa fa-ban"></i>{{ trans('customer::customer_online_offline.buttons.refresh') }}</button>
            @endcan
          </div>
        </div>
      </div>
    </div>
    <!-- /.content-header -->
    @stop
    @section('content')

    <div class="row">
      <div class="col-12">
        <div id="collection">
          @include('customer::backend.customerlog.partials.grid')
        </div>
      </div>
    </div>
  </div>
</div>

@stop