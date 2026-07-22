@extends('theme::layouts.backend.master')

@section('title')
{{ trans("customer::customer.titles.deleted_customer_list") }}
@endsection

@section('content-header')
<!-- Content Header (Page header) -->
<div class="card">
  <div class="card-body">
    <div class="page-title-header row d-none d-sm-flex">
      <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
          <h4 class="page-title">{{ trans("customer::customer.titles.deleted_customer_list") }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
          <div class="float-right">
              @include('core::partials.grid-panel-toggles')
            @can('admin.deletedCustomer.mass_delete')
            <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.deletedCustomer.mass_delete', updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</button>
            @endcan
            @can('admin.customer.mass_restore')
            <button type="button" class="btn btn-primary btn-fw" id="restore" data-message="{{ trans('customer::customer.messages.restore_modal') }}" data-action-target="{{ route('admin.customer.mass_restore',updateUrlParams()) }}">{{ trans('user::deleted_user.buttons.restore') }}</button>
            @endcan
          </div>
        </div>
      </div>
    </div>

    <!-- /.content-header -->
    @stop
    @section('content')
    <!-- Main content -->
    <!-- /.row -->
    <div class="row">
      <div class="col-12">
        <div id="collection">
          @include('customer::backend.partials.deletedcustomergrid')
        </div>
      </div>
    </div>
  </div>
</div>
<!-- /.container-fluid -->
@include('core::partials.delete-modal')
@include('core::partials.restore-modal')


@stop