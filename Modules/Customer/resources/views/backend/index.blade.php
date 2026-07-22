@extends('theme::layouts.backend.master')

@section('title')
{{ trans("customer::customer.titles.customer_list") }}
@endsection

@section('content-header')
<!-- Content Header (Page header) -->

<div class="card">
  <div class="card-body">
    <div class="page-title-header row d-none d-sm-flex">
      <div class="col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
          <h4 class="page-title">{{ trans("customer::customer.titles.customers") }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
          <div class="float-right">
            @can('admin.customer.mass_delete')
            <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.customer.mass_delete', updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</i></button>
            @endcan
            @can('admin.customer.create')
            <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.customer.create', updateUrlParams()) }}')">{{ trans("core::core.buttons.create") }}</button>
            @endcan
          </div>
        </div>
      </div>
    </div>
    <!-- /.content-header -->
    @stop

    @section('content')
    <!-- /row -->
    <div class="row">
      <div class="col-12">
        <div id="collection">
          @include('customer::backend.partials.grid')
        </div>
      </div>
    </div>
  </div>
</div>
@include('core::partials.delete-modal')

@stop

@push('js-stack')
<script type="text/javascript">
  var status;
  jQuery(document).on('change', '.status', function() {
    var id = $(this).attr('data-id');
    status = ($(this).is(':checked')) ? "{{ config('core.enabled') }}" : "{{ config('core.disabled') }}";
    $(".tooltip").remove();
    customObj.setUrl('{{route("admin.customer.update_status", updateUrlParams())}}').setMethod("POST").setParams({
      'status': status,
      'id': id,
      'active_menu_id': jQuery('#active_menu_id').val(),
      '_token': '{{csrf_token()}}'
    }).getContent();
  });
</script>
@endpush
