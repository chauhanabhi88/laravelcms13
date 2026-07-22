@extends('theme::layouts.backend.master')
@section('title')
{{ trans("customer::customer_group.titles.customergroup_head") }}
@endsection
@section('content-header')
<!-- Content Header (Page header) -->
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans("customer::customer_group.titles.customergroup_head") }}</h4>
                </div>
                <div class="col-sm-6">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                        @can('admin.customer.group.mass_delete')
                        <button type="button" class="btn btn-danger btn-fw" id="mass-delete" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.customer.group.mass_delete', updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</i></button>
                        @endcan
                        @can('admin.customer.group.create')
                        <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.customer.group.create',updateUrlParams()) }}')">{{ trans("core::core.buttons.create") }}</button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        @stop
        @section('content')
        <div class="row">
            <div class="col-md-12">
                <div id="collection">
                    @include('customer::backend.customer_group.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
@stop

@push('js-stack')
<script type="text/javascript">
    $('body').tooltip({
        selector: '[data-toggle="tooltip"]'
    });
    var status;
    jQuery(document).on('change', '.status', function() {
        var id = $(this).attr('data-id');
        // status = ($(this).is(':checked')) ? 1 : 2;
        status = ($(this).is(':checked')) ? "{{ config('core.enabled') }}" : "{{ config('core.disabled') }}";
        $(".tooltip").remove();
        customObj.setUrl('{{route("admin.customer.group.update_is_default", updateUrlParams())}}').setMethod("POST").setParams({
            'is_default': status,
            'id': id,
            '_token': '{{csrf_token()}}'
        }).getContent();
    });
</script>
@endpush