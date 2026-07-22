@extends('theme::layouts.backend.master')

@section('title')
{{ trans("contact::contact.titles.contact_list") }}
@endsection

@section('content-header')
<div class="card">
  <div class="card-body">
    <div class="page-title-header row d-none d-sm-flex">
      <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
          <h4 class="page-title">{{ trans("contact::contact.titles.contacts") }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
          <div class="float-right">
            @can('admin.contact.mass_delete')
            <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.contact.mass_delete', updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</button>
            @endcan
            @can('admin.contact.export')
            <button type="button" class="btn btn-info btn-fw" id="export">{{ trans("contact::contact.buttons.export") }} </button>
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
          @include('contact::backend.partials.grid')
        </div>
      </div>
    </div>
  </div>
</div>

@include('core::partials.delete-modal')

@stop

@push('js-stack')
<script type="text/javascript">
  jQuery(document).ready(function() {
    jQuery("#export").click(function() {
      $("#search_frm").attr("action", "{{ route('admin.contact.export',updateUrlParams()) }}");

      $("#search_frm").submit();
      $("#search_frm").attr("action", "{{ route('admin.contact.filters',updateUrlParams()) }}");
    });

  });
</script>
@endpush